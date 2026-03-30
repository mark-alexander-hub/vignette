<?php

/**
 * Vignette — VirusTotal Module
 * Domain and IP threat intelligence via VirusTotal v3 API.
 * API docs: https://docs.virustotal.com/reference/overview
 */

class VirusTotalModule {

    private string $apiKey;
    private string $baseUrl = 'https://www.virustotal.com/api/v3';

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Lookup threat data for a domain.
     */
    public function lookupDomain(string $domain): array {
        if (empty($this->apiKey)) {
            return ['error' => 'VirusTotal API key not configured — get one at virustotal.com/gui/join-us'];
        }

        $domain = trim(strtolower($domain));
        if (!preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/', $domain)) {
            return ['error' => 'Invalid domain format'];
        }

        $url = $this->baseUrl . '/domains/' . urlencode($domain);
        return $this->request($url);
    }

    /**
     * Lookup threat data for an IP address.
     */
    public function lookupIp(string $ip): array {
        if (empty($this->apiKey)) {
            return ['error' => 'VirusTotal API key not configured — get one at virustotal.com/gui/join-us'];
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['error' => 'Invalid IP address'];
        }

        $url = $this->baseUrl . '/ip_addresses/' . urlencode($ip);
        return $this->request($url);
    }

    /**
     * Normalize VirusTotal data into Vignette's standard format.
     */
    public function normalize(array $data, string $type = 'domain'): array {
        if (isset($data['error'])) {
            return [
                'source' => 'virustotal',
                'status' => 'error',
                'error' => $data['error'],
                'data' => []
            ];
        }

        $attributes = $data['data']['attributes'] ?? [];
        $stats = $attributes['last_analysis_stats'] ?? [];

        $malicious = (int)($stats['malicious'] ?? 0);
        $suspicious = (int)($stats['suspicious'] ?? 0);
        $harmless = (int)($stats['harmless'] ?? 0);
        $undetected = (int)($stats['undetected'] ?? 0);
        $totalEngines = $malicious + $suspicious + $harmless + $undetected;

        if ($type === 'ip') {
            return [
                'source' => 'virustotal',
                'status' => 'success',
                'data' => [
                    'ip' => $data['data']['id'] ?? '',
                    'reputation' => (int)($attributes['reputation'] ?? 0),
                    'malicious_count' => $malicious,
                    'suspicious_count' => $suspicious,
                    'harmless_count' => $harmless,
                    'undetected_count' => $undetected,
                    'total_engines' => $totalEngines,
                    'country' => $attributes['country'] ?? '',
                    'as_owner' => $attributes['as_owner'] ?? '',
                    'network' => $attributes['network'] ?? '',
                    'is_malicious' => $malicious > 0,
                ]
            ];
        }

        // Domain normalization (default)
        $categories = [];
        if (!empty($attributes['categories']) && is_array($attributes['categories'])) {
            $categories = $attributes['categories'];
        }

        $popularityRanks = [];
        if (!empty($attributes['popularity_ranks']) && is_array($attributes['popularity_ranks'])) {
            foreach ($attributes['popularity_ranks'] as $vendor => $info) {
                $popularityRanks[$vendor] = $info['rank'] ?? null;
            }
        }

        $lastAnalysisDate = '';
        if (!empty($attributes['last_analysis_date'])) {
            $lastAnalysisDate = date('Y-m-d H:i:s', (int)$attributes['last_analysis_date']);
        }

        $whoisDate = '';
        if (!empty($attributes['whois_date'])) {
            $whoisDate = date('Y-m-d H:i:s', (int)$attributes['whois_date']);
        }

        return [
            'source' => 'virustotal',
            'status' => 'success',
            'data' => [
                'domain' => $data['data']['id'] ?? '',
                'reputation' => (int)($attributes['reputation'] ?? 0),
                'malicious_count' => $malicious,
                'suspicious_count' => $suspicious,
                'harmless_count' => $harmless,
                'undetected_count' => $undetected,
                'total_engines' => $totalEngines,
                'categories' => $categories,
                'last_analysis_date' => $lastAnalysisDate,
                'registrar' => $attributes['registrar'] ?? '',
                'whois_date' => $whoisDate,
                'popularity_ranks' => $popularityRanks,
                'is_malicious' => $malicious > 0,
            ]
        ];
    }

    /**
     * Execute a cURL request to the VirusTotal API.
     */
    private function request(string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'x-apikey: ' . $this->apiKey,
                'User-Agent: Vignette-OSINT-Platform'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 8
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!empty($curlError)) {
            return ['error' => 'cURL error: ' . $curlError];
        }

        if ($status === 429) {
            return ['error' => 'VirusTotal rate limit exceeded — free tier allows 4 requests/minute. Wait and retry.'];
        }

        if ($status === 404) {
            return ['error' => 'Resource not found on VirusTotal'];
        }

        if ($status !== 200) {
            return ['error' => "VirusTotal API returned status $status"];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return ['error' => 'Failed to parse VirusTotal response'];
        }

        return $decoded;
    }
}
