<?php

/**
 * Vignette — SSL Certificate Module
 * Extracts SSL/TLS certificate details from a domain.
 * No API key required — connects directly to the server.
 */

class SslModule {

    /**
     * Lookup SSL certificate for a domain.
     */
    public function lookup(string $domain): array {
        $domain = $this->cleanDomain($domain);

        if (empty($domain)) {
            return ['error' => 'Invalid domain'];
        }

        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'capture_peer_cert_chain' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $socket = @stream_socket_client(
            "ssl://{$domain}:443",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$socket) {
            return ['error' => "SSL connection failed: {$errstr}"];
        }

        $params = stream_context_get_params($socket);
        fclose($socket);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return ['error' => 'No certificate returned'];
        }

        $certData = openssl_x509_parse($cert);
        if (!$certData) {
            return ['error' => 'Failed to parse certificate'];
        }

        // Get certificate chain info
        $chain = $params['options']['ssl']['peer_certificate_chain'] ?? [];
        $chainInfo = [];
        foreach ($chain as $i => $chainCert) {
            $parsed = openssl_x509_parse($chainCert);
            if ($parsed) {
                $chainInfo[] = [
                    'subject' => $parsed['subject']['CN'] ?? $parsed['subject']['O'] ?? 'Unknown',
                    'issuer' => $parsed['issuer']['CN'] ?? $parsed['issuer']['O'] ?? 'Unknown',
                ];
            }
        }

        // Get public key info
        $pubKey = openssl_pkey_get_details(openssl_pkey_get_public($cert));

        return [
            'domain' => $domain,
            'subject' => $certData['subject'] ?? [],
            'issuer' => $certData['issuer'] ?? [],
            'valid_from' => $certData['validFrom_time_t'] ?? 0,
            'valid_to' => $certData['validTo_time_t'] ?? 0,
            'serial' => $certData['serialNumberHex'] ?? '',
            'signature_algorithm' => $certData['signatureTypeSN'] ?? '',
            'san' => $this->extractSan($certData),
            'chain' => $chainInfo,
            'key_bits' => $pubKey['bits'] ?? 0,
            'key_type' => $this->keyTypeName($pubKey['type'] ?? -1),
        ];
    }

    /**
     * Normalize SSL data into Vignette's standard format.
     */
    public function normalize(array $data): array {
        if (isset($data['error'])) {
            return [
                'source' => 'ssl',
                'status' => 'error',
                'error' => $data['error'],
                'data' => []
            ];
        }

        $validFrom = $data['valid_from'] ?? 0;
        $validTo = $data['valid_to'] ?? 0;
        $now = time();

        $isExpired = $validTo < $now;
        $isExpiringSoon = !$isExpired && ($validTo - $now) < 30 * 86400;
        $daysRemaining = $isExpired ? 0 : (int) ceil(($validTo - $now) / 86400);

        // Determine cert type
        $issuerOrg = $data['issuer']['O'] ?? '';
        $issuerCn = $data['issuer']['CN'] ?? '';
        $certType = $this->detectCertType($data['subject'] ?? [], $issuerOrg);

        // Detect certificate authority
        $ca = $this->detectCA($issuerOrg, $issuerCn);

        // Extract SANs (other domains on same cert)
        $san = $data['san'] ?? [];

        return [
            'source' => 'ssl',
            'status' => 'success',
            'data' => [
                'domain' => $data['domain'] ?? '',
                'subject_cn' => $data['subject']['CN'] ?? '',
                'issuer_org' => $issuerOrg,
                'issuer_cn' => $issuerCn,
                'certificate_authority' => $ca,
                'cert_type' => $certType,
                'valid_from' => $validFrom ? date('Y-m-d', $validFrom) : '',
                'valid_to' => $validTo ? date('Y-m-d', $validTo) : '',
                'days_remaining' => $daysRemaining,
                'is_expired' => $isExpired,
                'is_expiring_soon' => $isExpiringSoon,
                'serial' => $data['serial'] ?? '',
                'signature_algorithm' => $data['signature_algorithm'] ?? '',
                'key_bits' => $data['key_bits'] ?? 0,
                'key_type' => $data['key_type'] ?? '',
                'san_domains' => $san,
                'san_count' => count($san),
                'chain_depth' => count($data['chain'] ?? []),
                'chain' => $data['chain'] ?? [],
            ]
        ];
    }

    /**
     * Extract Subject Alternative Names.
     */
    private function extractSan(array $certData): array {
        $san = [];
        $extensions = $certData['extensions'] ?? [];
        $sanStr = $extensions['subjectAltName'] ?? '';

        if ($sanStr) {
            preg_match_all('/DNS:([^,\s]+)/', $sanStr, $matches);
            $san = $matches[1] ?? [];
        }

        return array_values(array_unique($san));
    }

    /**
     * Detect certificate type (DV, OV, EV).
     */
    private function detectCertType(array $subject, string $issuerOrg): string {
        // EV certs have organization + jurisdiction info
        if (!empty($subject['O']) && !empty($subject['serialNumber'])) {
            return 'EV (Extended Validation)';
        }
        // OV certs have organization name
        if (!empty($subject['O'])) {
            return 'OV (Organization Validated)';
        }
        return 'DV (Domain Validated)';
    }

    /**
     * Detect Certificate Authority.
     */
    private function detectCA(string $issuerOrg, string $issuerCn): string {
        $combined = strtolower($issuerOrg . ' ' . $issuerCn);

        $cas = [
            "let's encrypt" => "Let's Encrypt",
            'letsencrypt' => "Let's Encrypt",
            'isrg' => "Let's Encrypt (ISRG)",
            'digicert' => 'DigiCert',
            'comodo' => 'Sectigo (Comodo)',
            'sectigo' => 'Sectigo',
            'globalsign' => 'GlobalSign',
            'cloudflare' => 'Cloudflare',
            'amazon' => 'Amazon Trust Services',
            'google trust' => 'Google Trust Services',
            'godaddy' => 'GoDaddy',
            'geotrust' => 'GeoTrust (DigiCert)',
            'thawte' => 'Thawte (DigiCert)',
            'rapidssl' => 'RapidSSL (DigiCert)',
            'entrust' => 'Entrust',
            'buypass' => 'Buypass',
            'certum' => 'Certum',
            'zerossl' => 'ZeroSSL',
            'ssl.com' => 'SSL.com',
            'microsoft' => 'Microsoft',
            'apple' => 'Apple',
            'baltimore' => 'Baltimore CyberTrust',
        ];

        foreach ($cas as $pattern => $name) {
            if (strpos($combined, $pattern) !== false) {
                return $name;
            }
        }

        return $issuerOrg ?: $issuerCn ?: 'Unknown';
    }

    /**
     * Get human-readable key type name.
     */
    private function keyTypeName(int $type): string {
        $types = [
            OPENSSL_KEYTYPE_RSA => 'RSA',
            OPENSSL_KEYTYPE_EC => 'EC (Elliptic Curve)',
            OPENSSL_KEYTYPE_DSA => 'DSA',
            OPENSSL_KEYTYPE_DH => 'DH',
        ];
        return $types[$type] ?? 'Unknown';
    }

    /**
     * Clean domain input.
     */
    private function cleanDomain(string $domain): string {
        $domain = trim(strtolower($domain));
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);
        return $domain;
    }
}
