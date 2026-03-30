<?php

/**
 * Vignette — HaveIBeenPwned Module
 * Checks email addresses against known data breaches.
 * API docs: https://haveibeenpwned.com/API/v3
 */

class HibpModule {

    private string $apiKey;
    private string $baseUrl = 'https://haveibeenpwned.com/api/v3';

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Check breaches for an email address.
     * Returns array of breach objects or empty array.
     */
    public function checkBreaches(string $email): array {
        if (empty($this->apiKey)) {
            return ['error' => 'HIBP API key not configured — get one at haveibeenpwned.com/API/Key'];
        }

        $url = $this->baseUrl . '/breachedaccount/' . urlencode($email) . '?truncateResponse=false';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'hibp-api-key: ' . $this->apiKey,
                'User-Agent: Vignette-OSINT-Platform'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 8
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 404) {
            return []; // No breaches found
        }

        if ($status === 429) {
            return ['error' => 'Rate limited — retry after delay'];
        }

        if ($status !== 200) {
            return ['error' => "HIBP API returned status $status"];
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Check if a password has been exposed in breaches (k-anonymity model).
     */
    public function checkPassword(string $password): int {
        $sha1 = strtoupper(sha1($password));
        $prefix = substr($sha1, 0, 5);
        $suffix = substr($sha1, 5);

        $url = "https://api.pwnedpasswords.com/range/{$prefix}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        foreach (explode("\n", $response) as $line) {
            $parts = explode(':', trim($line), 2);
            if (count($parts) !== 2) continue;
            if (strtoupper($parts[0]) === $suffix) {
                return (int)$parts[1];
            }
        }

        return 0;
    }

    /**
     * Normalize breach data into Vignette's standard format.
     */
    public function normalize(array $breaches): array {
        if (isset($breaches['error'])) {
            return [
                'source' => 'haveibeenpwned',
                'status' => 'error',
                'error' => $breaches['error'],
                'data' => []
            ];
        }

        $items = [];
        foreach ($breaches as $breach) {
            $items[] = [
                'name' => $breach['Name'] ?? 'Unknown',
                'title' => $breach['Title'] ?? '',
                'domain' => $breach['Domain'] ?? '',
                'breach_date' => $breach['BreachDate'] ?? '',
                'added_date' => $breach['AddedDate'] ?? '',
                'pwn_count' => $breach['PwnCount'] ?? 0,
                'data_classes' => $breach['DataClasses'] ?? [],
                'is_sensitive' => $breach['IsSensitive'] ?? false,
                'is_verified' => $breach['IsVerified'] ?? false,
                'logo_path' => $breach['LogoPath'] ?? '',
            ];
        }

        return [
            'source' => 'haveibeenpwned',
            'status' => 'success',
            'breach_count' => count($items),
            'data' => $items
        ];
    }
}
