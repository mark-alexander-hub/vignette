<?php

/**
 * Vignette — DNS Records Module
 * Pulls A, AAAA, MX, TXT, CNAME, NS, SOA records using PHP's dns_get_record().
 * No API key required.
 */

class DnsModule {

    /**
     * Lookup all DNS records for a domain.
     */
    public function lookup(string $domain): array {
        $domain = $this->cleanDomain($domain);

        if (empty($domain)) {
            return ['error' => 'Invalid domain'];
        }

        $records = [
            'domain' => $domain,
            'a' => [],
            'aaaa' => [],
            'mx' => [],
            'txt' => [],
            'cname' => [],
            'ns' => [],
            'soa' => null,
        ];

        // Fetch each record type separately for reliability
        $types = [
            DNS_A => 'a',
            DNS_AAAA => 'aaaa',
            DNS_MX => 'mx',
            DNS_TXT => 'txt',
            DNS_CNAME => 'cname',
            DNS_NS => 'ns',
            DNS_SOA => 'soa',
        ];

        foreach ($types as $dnsType => $key) {
            $result = @dns_get_record($domain, $dnsType);
            if ($result === false) continue;

            foreach ($result as $rec) {
                switch ($key) {
                    case 'a':
                        $records['a'][] = $rec['ip'] ?? '';
                        break;
                    case 'aaaa':
                        $records['aaaa'][] = $rec['ipv6'] ?? '';
                        break;
                    case 'mx':
                        $records['mx'][] = [
                            'priority' => $rec['pri'] ?? 0,
                            'host' => $rec['target'] ?? '',
                        ];
                        break;
                    case 'txt':
                        $records['txt'][] = $rec['txt'] ?? '';
                        break;
                    case 'cname':
                        $records['cname'][] = $rec['target'] ?? '';
                        break;
                    case 'ns':
                        $records['ns'][] = $rec['target'] ?? '';
                        break;
                    case 'soa':
                        $records['soa'] = [
                            'mname' => $rec['mname'] ?? '',
                            'rname' => $rec['rname'] ?? '',
                            'serial' => $rec['serial'] ?? 0,
                            'refresh' => $rec['refresh'] ?? 0,
                            'retry' => $rec['retry'] ?? 0,
                            'expire' => $rec['expire'] ?? 0,
                            'minimum_ttl' => $rec['minimum-ttl'] ?? 0,
                        ];
                        break;
                }
            }
        }

        // Also check _dmarc subdomain for DMARC record
        $dmarcRecords = @dns_get_record('_dmarc.' . $domain, DNS_TXT);
        if ($dmarcRecords) {
            foreach ($dmarcRecords as $rec) {
                $txt = $rec['txt'] ?? '';
                if (stripos($txt, 'v=DMARC1') !== false) {
                    $records['txt'][] = $txt;
                }
            }
        }

        // Sort MX by priority
        usort($records['mx'], fn($a, $b) => $a['priority'] - $b['priority']);

        // Filter empty values
        $records['a'] = array_filter($records['a']);
        $records['aaaa'] = array_filter($records['aaaa']);
        $records['txt'] = array_filter($records['txt']);
        $records['cname'] = array_filter($records['cname']);
        $records['ns'] = array_filter($records['ns']);

        return $records;
    }

    /**
     * Normalize DNS data into Vignette's standard format.
     */
    public function normalize(array $data): array {
        if (isset($data['error'])) {
            return [
                'source' => 'dns',
                'status' => 'error',
                'error' => $data['error'],
                'data' => []
            ];
        }

        // Detect mail provider from MX records
        $mailProvider = $this->detectMailProvider($data['mx'] ?? []);

        // Detect CDN/hosting from A records and CNAME
        $hosting = $this->detectHosting($data['a'] ?? [], $data['cname'] ?? []);

        // Parse SPF, DKIM, DMARC from TXT records
        $security = $this->analyzeEmailSecurity($data['txt'] ?? []);

        // Detect verification records (Google, Facebook, etc.)
        $verifications = $this->detectVerifications($data['txt'] ?? []);

        return [
            'source' => 'dns',
            'status' => 'success',
            'data' => [
                'domain' => $data['domain'] ?? '',
                'a_records' => $data['a'] ?? [],
                'aaaa_records' => $data['aaaa'] ?? [],
                'mx_records' => $data['mx'] ?? [],
                'txt_records' => $data['txt'] ?? [],
                'cname_records' => $data['cname'] ?? [],
                'ns_records' => $data['ns'] ?? [],
                'soa' => $data['soa'] ?? null,
                'mail_provider' => $mailProvider,
                'hosting' => $hosting,
                'email_security' => $security,
                'verifications' => $verifications,
            ]
        ];
    }

    /**
     * Detect mail provider from MX records.
     */
    private function detectMailProvider(array $mxRecords): string {
        if (empty($mxRecords)) return 'None';

        $mxHosts = implode(' ', array_column($mxRecords, 'host'));
        $mxLower = strtolower($mxHosts);

        $providers = [
            'google.com' => 'Google Workspace',
            'googlemail.com' => 'Google Workspace',
            'outlook.com' => 'Microsoft 365',
            'protection.outlook.com' => 'Microsoft 365',
            'pphosted.com' => 'Proofpoint',
            'mimecast' => 'Mimecast',
            'zoho' => 'Zoho Mail',
            'protonmail' => 'ProtonMail',
            'icloud.com' => 'Apple iCloud',
            'yahoodns.net' => 'Yahoo Mail',
            'mailgun' => 'Mailgun',
            'sendgrid' => 'SendGrid',
            'amazonaws.com' => 'Amazon SES',
            'messagelabs' => 'Symantec',
            'barracuda' => 'Barracuda',
            'forcepoint' => 'Forcepoint',
            'sophos' => 'Sophos',
            'cloudflare' => 'Cloudflare Email',
            'fastmail' => 'Fastmail',
            'migadu' => 'Migadu',
            'titan.email' => 'Titan Email',
            'namecheap' => 'Namecheap Email',
            'hostinger' => 'Hostinger Email',
        ];

        foreach ($providers as $pattern => $name) {
            if (stripos($mxLower, $pattern) !== false) {
                return $name;
            }
        }

        return 'Custom / Self-hosted';
    }

    /**
     * Detect CDN or hosting provider from A records and CNAMEs.
     */
    private function detectHosting(array $aRecords, array $cnameRecords): array {
        $detected = [];
        $all = implode(' ', array_merge($aRecords, $cnameRecords));
        $allLower = strtolower($all);

        $providers = [
            'cloudflare' => 'Cloudflare',
            'akamai' => 'Akamai',
            'fastly' => 'Fastly',
            'amazonaws.com' => 'AWS',
            'cloudfront' => 'AWS CloudFront',
            'elb.amazonaws' => 'AWS ELB',
            'googleusercontent.com' => 'Google Cloud',
            'google.com' => 'Google',
            'azure' => 'Microsoft Azure',
            'trafficmanager.net' => 'Azure Traffic Manager',
            'netlify' => 'Netlify',
            'vercel' => 'Vercel',
            'herokuapp' => 'Heroku',
            'github.io' => 'GitHub Pages',
            'digitalocean' => 'DigitalOcean',
            'linode' => 'Linode/Akamai',
            'vultr' => 'Vultr',
            'hetzner' => 'Hetzner',
            'ovh' => 'OVH',
            'godaddy' => 'GoDaddy',
            'wpengine' => 'WP Engine',
            'squarespace' => 'Squarespace',
            'shopify' => 'Shopify',
            'wix' => 'Wix',
            'wordpress.com' => 'WordPress.com',
            'pantheon' => 'Pantheon',
            'kinsta' => 'Kinsta',
            'fly.io' => 'Fly.io',
            'render.com' => 'Render',
            'railway' => 'Railway',
        ];

        foreach ($providers as $pattern => $name) {
            if (stripos($allLower, $pattern) !== false) {
                $detected[] = $name;
            }
        }

        // Check IP ranges for known providers
        foreach ($aRecords as $ip) {
            $provider = $this->detectProviderByIp($ip);
            if ($provider && !in_array($provider, $detected)) {
                $detected[] = $provider;
            }
        }

        return array_unique($detected) ?: ['Unknown'];
    }

    /**
     * Detect provider by known IP ranges.
     */
    private function detectProviderByIp(string $ip): ?string {
        $octets = explode('.', $ip);
        if (count($octets) < 2) return null;

        $first = (int) $octets[0];
        $second = (int) $octets[1];

        // Cloudflare ranges
        if (in_array($first, [104, 172, 173]) || ($first === 103 && $second >= 21 && $second <= 22)) {
            return 'Cloudflare';
        }
        // Google Cloud
        if ($first === 34 || $first === 35) {
            return 'Google Cloud';
        }

        return null;
    }

    /**
     * Analyze email security from TXT records (SPF, DKIM, DMARC).
     */
    private function analyzeEmailSecurity(array $txtRecords): array {
        $security = [
            'spf' => null,
            'dmarc' => null,
            'has_dkim' => false,
            'score' => 0,
            'issues' => [],
        ];

        foreach ($txtRecords as $txt) {
            // SPF
            if (stripos($txt, 'v=spf1') === 0) {
                $security['spf'] = $txt;
                $security['score'] += 30;

                if (strpos($txt, '+all') !== false) {
                    $security['issues'][] = 'SPF uses +all (allows any sender — very weak)';
                } elseif (strpos($txt, '~all') !== false) {
                    $security['issues'][] = 'SPF uses ~all (softfail — messages may still be delivered)';
                    $security['score'] += 5;
                } elseif (strpos($txt, '-all') !== false) {
                    $security['score'] += 15;
                }
            }

            // DMARC (usually on _dmarc subdomain, but sometimes in root TXT)
            if (stripos($txt, 'v=DMARC1') !== false) {
                $security['dmarc'] = $txt;
                $security['score'] += 30;

                if (preg_match('/p=(\w+)/', $txt, $m)) {
                    if ($m[1] === 'none') {
                        $security['issues'][] = 'DMARC policy is "none" (monitoring only, no enforcement)';
                    } elseif ($m[1] === 'reject') {
                        $security['score'] += 15;
                    } elseif ($m[1] === 'quarantine') {
                        $security['score'] += 10;
                    }
                }
            }

            // DKIM selector hint
            if (stripos($txt, 'v=DKIM1') !== false || stripos($txt, 'k=rsa') !== false) {
                $security['has_dkim'] = true;
                $security['score'] += 20;
            }
        }

        if (!$security['spf']) {
            $security['issues'][] = 'No SPF record found — domain vulnerable to email spoofing';
        }
        if (!$security['dmarc']) {
            $security['issues'][] = 'No DMARC record found at root (check _dmarc subdomain)';
        }

        $security['score'] = min($security['score'], 100);

        return $security;
    }

    /**
     * Detect third-party service verifications from TXT records.
     */
    private function detectVerifications(array $txtRecords): array {
        $verifications = [];

        $patterns = [
            'google-site-verification' => 'Google Search Console',
            'facebook-domain-verification' => 'Facebook',
            'MS=' => 'Microsoft 365',
            'apple-domain-verification' => 'Apple',
            'adobe-idp-site-verification' => 'Adobe',
            'atlassian-domain-verification' => 'Atlassian',
            'stripe-verification' => 'Stripe',
            'hubspot' => 'HubSpot',
            'docusign' => 'DocuSign',
            'globalsign-domain-verification' => 'GlobalSign',
            'mailchimp' => 'Mailchimp',
            'postmark' => 'Postmark',
            'brevo-code' => 'Brevo',
            'sendinblue' => 'Brevo/Sendinblue',
            'klaviyo' => 'Klaviyo',
            'have-i-been-pwned' => 'Have I Been Pwned',
            'miro-verification' => 'Miro',
            'notion-site-verification' => 'Notion',
            'zoom' => 'Zoom',
            'canva-site-verification' => 'Canva',
        ];

        foreach ($txtRecords as $txt) {
            foreach ($patterns as $pattern => $service) {
                if (stripos($txt, $pattern) !== false) {
                    $verifications[] = $service;
                }
            }
        }

        return array_unique($verifications);
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
