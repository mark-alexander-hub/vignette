<?php

/**
 * Vignette — Gravatar Module
 * Profile lookup via email hash. Retrieves avatar, display name, bio, location, and social links.
 * API docs: https://docs.gravatar.com/api/profiles/
 * No API key required.
 */

class GravatarModule {

    private string $baseUrl = 'https://www.gravatar.com';

    public function __construct() {
        // No API key required for Gravatar
    }

    /**
     * Lookup Gravatar profile by email address.
     */
    public function lookup(string $email): array {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Invalid email address'];
        }

        $hash = md5($email);
        $url = $this->baseUrl . '/' . $hash . '.json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Vignette-OSINT-Platform'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['error' => "Gravatar request failed: $curlError"];
        }

        // 404 = no Gravatar profile for this email (not an error, just no data)
        if ($status === 404) {
            return [
                'source' => 'gravatar',
                'status' => 'success',
                'data' => [],
                'note' => 'No Gravatar profile found for this email'
            ];
        }

        if ($status !== 200) {
            return ['error' => "Gravatar API returned status $status"];
        }

        $decoded = json_decode($response, true);

        if (!$decoded || !isset($decoded['entry'][0])) {
            return ['error' => 'Unexpected Gravatar response format'];
        }

        return $decoded['entry'][0];
    }

    /**
     * Normalize Gravatar data into Vignette's standard format.
     */
    public function normalize(array $data): array {
        // Pass through pre-built "no profile" responses
        if (isset($data['source']) && $data['source'] === 'gravatar') {
            return $data;
        }

        if (isset($data['error'])) {
            return [
                'source' => 'gravatar',
                'status' => 'error',
                'error' => $data['error'],
                'data' => []
            ];
        }

        // Extract display name — Gravatar nests it in various places
        $displayName = $data['displayName'] ?? $data['preferredUsername'] ?? $data['name']['formatted'] ?? '';

        // Extract avatar URL (use the thumbnailUrl, default size)
        $avatarUrl = $data['thumbnailUrl'] ?? '';
        if ($avatarUrl && strpos($avatarUrl, '?') === false) {
            $avatarUrl .= '?s=400'; // request a reasonable size
        }

        // Extract location from the first entry in currentLocation or aboutMe
        $location = $data['currentLocation'] ?? '';

        // Extract bio/about me
        $bio = $data['aboutMe'] ?? '';

        // Build the profile URL
        $profileUrl = $data['profileUrl'] ?? '';

        // Extract social/verified accounts
        $socialLinks = [];
        if (!empty($data['accounts']) && is_array($data['accounts'])) {
            foreach ($data['accounts'] as $account) {
                $socialLinks[] = [
                    'domain' => $account['domain'] ?? '',
                    'display' => $account['display'] ?? '',
                    'url' => $account['url'] ?? '',
                    'shortname' => $account['shortname'] ?? '',
                    'verified' => $account['verified'] ?? false
                ];
            }
        }

        // Extract associated URLs
        if (!empty($data['urls']) && is_array($data['urls'])) {
            foreach ($data['urls'] as $urlEntry) {
                $socialLinks[] = [
                    'domain' => '',
                    'display' => $urlEntry['title'] ?? '',
                    'url' => $urlEntry['value'] ?? '',
                    'shortname' => '',
                    'verified' => false
                ];
            }
        }

        // Extract emails listed in the profile
        $emails = [];
        if (!empty($data['emails']) && is_array($data['emails'])) {
            foreach ($data['emails'] as $emailEntry) {
                $emails[] = $emailEntry['value'] ?? $emailEntry;
            }
        }

        return [
            'source' => 'gravatar',
            'status' => 'success',
            'data' => [
                'display_name' => $displayName,
                'avatar_url' => $avatarUrl,
                'location' => $location,
                'bio' => $bio,
                'profile_url' => $profileUrl,
                'social_links' => $socialLinks,
                'emails' => $emails
            ]
        ];
    }
}
