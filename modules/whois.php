<?php

/**
 * Vignette — WHOIS Module
 * Domain registration lookup via public WHOIS data.
 * Uses the free whoisjson.com API (no key required, 100 req/day).
 */

class WhoisModule {

    private string $baseUrl = 'https://whoisjson.com/api/v1/whois';

    /**
     * Lookup WHOIS data for a domain.
     */
    public function lookup(string $domain): array {
        $domain = $this->cleanDomain($domain);

        if (!$this->isValidDomain($domain)) {
            return ['error' => 'Invalid domain name'];
        }

        // Try whoisjson.com API first
        $result = $this->queryWhoisJson($domain);
        if ($result !== null) {
            return $result;
        }

        // Fallback: use PHP socket-based WHOIS
        $result = $this->querySocket($domain);
        if ($result !== null) {
            return $result;
        }

        return ['error' => 'WHOIS lookup failed — no data returned'];
    }

    /**
     * Query whoisjson.com free API.
     */
    private function queryWhoisJson(string $domain): ?array {
        $url = $this->baseUrl . '?domain=' . urlencode($domain);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Vignette-OSINT-Platform'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 8
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200 || empty($response)) {
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data) || isset($data['error']) || isset($data['statusCode']) || isset($data['message'])) {
            return null;
        }

        return $data;
    }

    /**
     * Fallback: Direct WHOIS socket query.
     */
    private function querySocket(string $domain): ?array {
        $tld = $this->getTld($domain);
        $server = $this->getWhoisServer($tld);

        if (!$server) {
            return null;
        }

        $fp = @fsockopen($server, 43, $errno, $errstr, 10);
        if (!$fp) {
            return null;
        }

        stream_set_timeout($fp, 10);
        fwrite($fp, $domain . "\r\n");
        $raw = '';
        while (!feof($fp)) {
            $raw .= fgets($fp);
        }
        fclose($fp);

        if (empty(trim($raw))) {
            return null;
        }

        return $this->parseRawWhois($raw, $domain);
    }

    /**
     * Parse raw WHOIS text into structured data.
     */
    private function parseRawWhois(string $raw, string $domain): array {
        $data = [
            'domain_name' => $domain,
            'raw' => $raw,
        ];

        $patterns = [
            'registrar'       => '/Registrar:\s*(.+)/i',
            'creation_date'   => '/Creat(?:ion|ed)\s*Date:\s*(.+)/i',
            'expiration_date' => '/(?:Registry\s*)?Expir(?:ation|y)\s*Date:\s*(.+)/i',
            'updated_date'    => '/Updated?\s*Date:\s*(.+)/i',
            'registrant_name' => '/Registrant\s*Name:\s*(.+)/i',
            'registrant_org'  => '/Registrant\s*Organi[zs]ation:\s*(.+)/i',
            'registrant_country' => '/Registrant\s*Country:\s*(.+)/i',
            'name_servers'    => '/Name\s*Server:\s*(.+)/i',
            'status'          => '/(?:Domain\s*)?Status:\s*(.+)/i',
            'dnssec'          => '/DNSSEC:\s*(.+)/i',
        ];

        foreach ($patterns as $key => $pattern) {
            if ($key === 'name_servers' || $key === 'status') {
                // Collect all matches
                if (preg_match_all($pattern, $raw, $matches)) {
                    $data[$key] = array_map('trim', $matches[1]);
                }
            } else {
                if (preg_match($pattern, $raw, $match)) {
                    $data[$key] = trim($match[1]);
                }
            }
        }

        return $data;
    }

    /**
     * Normalize WHOIS data into Vignette's standard format.
     */
    public function normalize(array $data): array {
        if (isset($data['error'])) {
            return [
                'source' => 'whois',
                'status' => 'error',
                'error' => $data['error'],
                'data' => []
            ];
        }

        // Handle whoisjson.com format and raw socket format
        $domain = $data['domain_name'] ?? $data['domain'] ?? '';
        $registrar = $data['registrar'] ?? $data['registrar_name'] ?? '';
        $createdDate = $data['creation_date'] ?? $data['created'] ?? $data['create_date'] ?? '';
        $expiryDate = $data['expiration_date'] ?? $data['expires'] ?? $data['expiry_date'] ?? '';
        $updatedDate = $data['updated_date'] ?? $data['updated'] ?? $data['last_updated'] ?? '';
        $registrantOrg = $data['registrant_org'] ?? $data['registrant_name'] ?? $data['registrant'] ?? $data['registrant_organization'] ?? '';
        // Skip if it looks like a redacted/garbage value
        if (preg_match('/REDACTED|STREET|ADDRESS/i', $registrantOrg)) {
            $registrantOrg = '';
        }
        $registrantCountry = $data['registrant_country'] ?? $data['registrant_country_code'] ?? '';
        $nameServers = $data['name_servers'] ?? $data['nameservers'] ?? [];
        $status = $data['status'] ?? [];
        $dnssec = $data['dnssec'] ?? '';

        // Normalize name servers to array
        if (is_string($nameServers)) {
            $nameServers = array_filter(array_map('trim', explode(',', $nameServers)));
        }

        // Normalize status to array
        if (is_string($status)) {
            $status = [$status];
        }

        // Clean up dates
        $createdDate = $this->formatDate($createdDate);
        $expiryDate = $this->formatDate($expiryDate);
        $updatedDate = $this->formatDate($updatedDate);

        // Calculate domain age
        $domainAge = '';
        if ($createdDate) {
            $created = strtotime($createdDate);
            if ($created) {
                $years = (int) floor((time() - $created) / (365.25 * 86400));
                $domainAge = $years . ' year' . ($years !== 1 ? 's' : '');
            }
        }

        // Check if privacy-protected
        $isPrivate = $this->isPrivacyProtected($data);

        return [
            'source' => 'whois',
            'status' => 'success',
            'data' => [
                'domain' => strtolower($domain),
                'registrar' => $registrar,
                'created_date' => $createdDate,
                'expiry_date' => $expiryDate,
                'updated_date' => $updatedDate,
                'domain_age' => $domainAge,
                'registrant_org' => $registrantOrg,
                'registrant_country' => $registrantCountry,
                'name_servers' => array_values(array_unique(array_map('strtolower', $nameServers))),
                'status' => $status,
                'dnssec' => $dnssec,
                'is_privacy_protected' => $isPrivate,
            ]
        ];
    }

    /**
     * Detect if WHOIS data is privacy-protected.
     */
    private function isPrivacyProtected(array $data): bool {
        $raw = json_encode($data);
        $keywords = ['privacy', 'redacted', 'whoisguard', 'domains by proxy', 'contact privacy', 'withheld'];
        foreach ($keywords as $kw) {
            if (stripos($raw, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Format a date string to Y-m-d.
     */
    private function formatDate(string $date): string {
        if (empty($date)) return '';
        $ts = strtotime($date);
        return $ts ? date('Y-m-d', $ts) : $date;
    }

    /**
     * Strip protocol, path, and subdomains to get the registrable domain.
     */
    private function cleanDomain(string $domain): string {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);
        $domain = preg_replace('#^www\.#i', '', $domain);
        $domain = strtolower($domain);

        // Strip subdomains — keep only the registrable root domain
        $domain = $this->extractRootDomain($domain);

        return $domain;
    }

    /**
     * Extract the root registrable domain from a full hostname.
     * e.g. "prod.getafixapp.com" -> "getafixapp.com"
     *      "bbc.co.uk" -> "bbc.co.uk"
     */
    private function extractRootDomain(string $domain): string {
        $parts = explode('.', $domain);
        $count = count($parts);

        if ($count <= 2) {
            return $domain;
        }

        // Known two-part TLDs (country-code second-level domains)
        $twoPartTlds = [
            'co.uk', 'org.uk', 'me.uk', 'ac.uk', 'gov.uk',
            'co.nz', 'net.nz', 'org.nz',
            'co.za', 'org.za', 'web.za',
            'com.au', 'net.au', 'org.au',
            'com.br', 'net.br', 'org.br',
            'co.in', 'net.in', 'org.in',
            'co.jp', 'or.jp', 'ne.jp',
            'co.kr', 'or.kr',
            'com.mx', 'org.mx',
            'com.cn', 'net.cn', 'org.cn',
            'com.tw', 'org.tw',
            'com.hk', 'org.hk',
            'com.sg', 'org.sg',
            'com.ph', 'org.ph',
            'co.id', 'or.id',
            'com.my', 'org.my',
            'com.ar', 'org.ar',
            'com.co', 'org.co',
            'co.il', 'org.il',
            'com.tr', 'org.tr',
            'com.ua', 'org.ua',
            'com.ng', 'org.ng',
            'co.ke', 'or.ke',
        ];

        // Check if last two parts form a known two-part TLD
        $lastTwo = $parts[$count - 2] . '.' . $parts[$count - 1];
        if (in_array($lastTwo, $twoPartTlds)) {
            // Need 3 parts: domain + two-part TLD
            return ($count >= 3) ? implode('.', array_slice($parts, -3)) : $domain;
        }

        // Standard: keep last 2 parts (domain.tld)
        return implode('.', array_slice($parts, -2));
    }

    /**
     * Basic domain validation.
     */
    private function isValidDomain(string $domain): bool {
        return (bool) preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z]{2,})+$/', $domain);
    }

    /**
     * Extract TLD from domain.
     */
    private function getTld(string $domain): string {
        $parts = explode('.', $domain);
        return end($parts);
    }

    /**
     * Map TLD to WHOIS server.
     */
    private function getWhoisServer(string $tld): ?string {
        $servers = [
            // Generic
            'com'       => 'whois.verisign-grs.com',
            'net'       => 'whois.verisign-grs.com',
            'org'       => 'whois.pir.org',
            'info'      => 'whois.afilias.net',
            'biz'       => 'whois.biz',
            'name'      => 'whois.nic.name',
            'mobi'      => 'whois.afilias.net',
            'pro'       => 'whois.nic.pro',
            // Country codes
            'io'        => 'whois.nic.io',
            'co'        => 'whois.nic.co',
            'me'        => 'whois.nic.me',
            'us'        => 'whois.nic.us',
            'uk'        => 'whois.nic.uk',
            'de'        => 'whois.denic.de',
            'fr'        => 'whois.nic.fr',
            'nl'        => 'whois.sidn.nl',
            'au'        => 'whois.auda.org.au',
            'ca'        => 'whois.cira.ca',
            'eu'        => 'whois.eu',
            'ru'        => 'whois.tcinet.ru',
            'jp'        => 'whois.jprs.jp',
            'cn'        => 'whois.cnnic.cn',
            'in'        => 'whois.registry.in',
            'br'        => 'whois.registro.br',
            'it'        => 'whois.nic.it',
            'es'        => 'whois.nic.es',
            'pl'        => 'whois.dns.pl',
            'se'        => 'whois.iis.se',
            'no'        => 'whois.norid.no',
            'dk'        => 'whois.dk-hostmaster.dk',
            'fi'        => 'whois.fi',
            'be'        => 'whois.dns.be',
            'at'        => 'whois.nic.at',
            'ch'        => 'whois.nic.ch',
            'nz'        => 'whois.srs.net.nz',
            'za'        => 'whois.registry.net.za',
            'mx'        => 'whois.mx',
            'kr'        => 'whois.kr',
            'tw'        => 'whois.twnic.net.tw',
            'sg'        => 'whois.sgnic.sg',
            'hk'        => 'whois.hkirc.hk',
            'ph'        => 'whois.dot.ph',
            'id'        => 'whois.id',
            // New gTLDs
            'dev'       => 'whois.nic.google',
            'app'       => 'whois.nic.google',
            'page'      => 'whois.nic.google',
            'xyz'       => 'whois.nic.xyz',
            'tech'      => 'whois.nic.tech',
            'site'      => 'whois.nic.site',
            'online'    => 'whois.nic.online',
            'store'     => 'whois.nic.store',
            'club'      => 'whois.nic.club',
            'live'      => 'whois.nic.live',
            'cloud'     => 'whois.nic.cloud',
            'space'     => 'whois.nic.space',
            'fun'       => 'whois.nic.fun',
            'icu'       => 'whois.nic.icu',
            'top'       => 'whois.nic.top',
            'shop'      => 'whois.nic.shop',
            'blog'      => 'whois.nic.blog',
            'art'       => 'whois.nic.art',
            'design'    => 'whois.nic.design',
            'media'     => 'whois.nic.media',
            'agency'    => 'whois.nic.agency',
            'digital'   => 'whois.nic.digital',
            'network'   => 'whois.nic.network',
            'systems'   => 'whois.nic.systems',
            'solutions' => 'whois.nic.solutions',
            'world'     => 'whois.nic.world',
            'today'     => 'whois.nic.today',
            'life'      => 'whois.nic.life',
            'rocks'     => 'whois.nic.rocks',
            'ninja'     => 'whois.nic.ninja',
            'guru'      => 'whois.nic.guru',
            'email'     => 'whois.nic.email',
            'codes'     => 'whois.nic.codes',
            'tools'     => 'whois.nic.tools',
            'wiki'      => 'whois.nic.wiki',
            'ai'        => 'whois.nic.ai',
            'gg'        => 'whois.gg',
            'tv'        => 'whois.nic.tv',
            'cc'        => 'whois.nic.cc',
            // Adult
            'xxx'       => 'whois.nic.xxx',
            'adult'     => 'whois.nic.adult',
            'porn'      => 'whois.nic.porn',
            'sex'       => 'whois.nic.sex',
        ];

        if (isset($servers[$tld])) {
            return $servers[$tld];
        }

        // Fallback: query IANA to discover the WHOIS server for this TLD
        return $this->discoverWhoisServer($tld);
    }

    /**
     * Query IANA's root WHOIS to discover the server for an unknown TLD.
     */
    private function discoverWhoisServer(string $tld): ?string {
        $fp = @fsockopen('whois.iana.org', 43, $errno, $errstr, 5);
        if (!$fp) {
            return null;
        }

        fwrite($fp, $tld . "\r\n");
        $response = '';
        while (!feof($fp)) {
            $response .= fgets($fp);
        }
        fclose($fp);

        if (preg_match('/whois:\s*(.+)/i', $response, $match)) {
            $server = trim($match[1]);
            if (!empty($server)) {
                return $server;
            }
        }

        return null;
    }
}
