<?php

/**
 * Vignette — Username OSINT Module
 * Checks username existence across 20+ public platforms using cURL multi for parallel requests.
 * No API keys required — uses public profile URLs and HTTP status codes.
 */

class UsernameOsintModule {

    private int $timeout = 5;
    private string $userAgent = 'Vignette-OSINT-Platform';

    /**
     * Platform definitions: key => [display name, URL template, check type].
     * Check types:
     *   'status'  — HTTP 200 means exists
     *   'json'    — Valid non-null JSON means exists
     *   'body'    — Custom body content check
     */
    private function getPlatforms(string $username): array {
        return [
            // ---- Major Social Media ----
            'instagram' => [
                'name'  => 'Instagram',
                'url'   => "https://www.instagram.com/{$username}/",
                'check' => 'body',
                'match' => 'profilePage',
            ],
            'twitter' => [
                'name'  => 'X (Twitter)',
                'url'   => "https://x.com/{$username}",
                'check' => 'body',
                'match' => 'twitter:title',
            ],
            'facebook' => [
                'name'  => 'Facebook',
                'url'   => "https://www.facebook.com/{$username}",
                'check' => 'body',
                'match' => 'pageID',
            ],
            'youtube' => [
                'name'  => 'YouTube',
                'url'   => "https://www.youtube.com/@{$username}",
                'check' => 'status',
            ],
            'linkedin' => [
                'name'  => 'LinkedIn',
                'url'   => "https://www.linkedin.com/in/{$username}",
                'check' => 'body',
                'match' => 'profile-section',
            ],
            'tiktok' => [
                'name'  => 'TikTok',
                'url'   => "https://www.tiktok.com/@{$username}",
                'check' => 'status',
            ],
            'snapchat' => [
                'name'  => 'Snapchat',
                'url'   => "https://www.snapchat.com/add/{$username}",
                'check' => 'body',
                'match' => 'snap-username',
            ],
            'threads' => [
                'name'  => 'Threads',
                'url'   => "https://www.threads.net/@{$username}",
                'check' => 'body',
                'match' => 'threads.net',
            ],
            // ---- Content & Community ----
            'reddit' => [
                'name'  => 'Reddit',
                'url'   => "https://www.reddit.com/user/{$username}/about.json",
                'check' => 'json',
            ],
            'github' => [
                'name'  => 'GitHub',
                'url'   => "https://github.com/{$username}",
                'check' => 'status',
            ],
            'pinterest' => [
                'name'  => 'Pinterest',
                'url'   => "https://www.pinterest.com/{$username}/",
                'check' => 'status',
            ],
            'twitch' => [
                'name'  => 'Twitch',
                'url'   => "https://www.twitch.tv/{$username}",
                'check' => 'status',
            ],
            'medium' => [
                'name'  => 'Medium',
                'url'   => "https://medium.com/@{$username}",
                'check' => 'status',
            ],
            'soundcloud' => [
                'name'  => 'SoundCloud',
                'url'   => "https://soundcloud.com/{$username}",
                'check' => 'status',
            ],
            'spotify' => [
                'name'  => 'Spotify',
                'url'   => "https://open.spotify.com/user/{$username}",
                'check' => 'status',
            ],
            'imgur' => [
                'name'  => 'Imgur',
                'url'   => "https://imgur.com/user/{$username}",
                'check' => 'status',
            ],
            'vimeo' => [
                'name'  => 'Vimeo',
                'url'   => "https://vimeo.com/{$username}",
                'check' => 'status',
            ],
            'telegram' => [
                'name'  => 'Telegram',
                'url'   => "https://t.me/{$username}",
                'check' => 'body',
                'match' => 'tgme_page_title',
            ],
            'linktree' => [
                'name'  => 'Linktree',
                'url'   => "https://linktr.ee/{$username}",
                'check' => 'status',
            ],
            // ---- Tech & Niche ----
            'gitlab' => [
                'name'  => 'GitLab',
                'url'   => "https://gitlab.com/{$username}",
                'check' => 'status',
            ],
            'keybase' => [
                'name'  => 'Keybase',
                'url'   => "https://keybase.io/{$username}",
                'check' => 'status',
            ],
            'hackernews' => [
                'name'  => 'HackerNews',
                'url'   => "https://hacker-news.firebaseio.com/v0/user/{$username}.json",
                'check' => 'json',
            ],
            'steam' => [
                'name'  => 'Steam',
                'url'   => "https://steamcommunity.com/id/{$username}",
                'check' => 'body',
                'match' => 'profile_page',
            ],
            'patreon' => [
                'name'  => 'Patreon',
                'url'   => "https://www.patreon.com/{$username}",
                'check' => 'status',
            ],
            'devto' => [
                'name'  => 'Dev.to',
                'url'   => "https://dev.to/api/users/by_username?url={$username}",
                'check' => 'status',
            ],
            'aboutme' => [
                'name'  => 'About.me',
                'url'   => "https://about.me/{$username}",
                'check' => 'status',
            ],
            'flickr' => [
                'name'  => 'Flickr',
                'url'   => "https://www.flickr.com/people/{$username}",
                'check' => 'status',
            ],
        ];
    }

    /**
     * Lookup a username across all platforms using cURL multi for parallel execution.
     */
    public function lookup(string $username): array {
        $username = trim($username);

        if (empty($username) || !preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            return ['error' => 'Invalid username — only alphanumeric characters, dots, hyphens, and underscores allowed'];
        }

        $platforms = $this->getPlatforms($username);
        $multiHandle = curl_multi_init();
        $curlHandles = [];

        // Initialize all cURL handles and add to multi handle
        foreach ($platforms as $key => $platform) {
            $ch = curl_init($platform['url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER     => [
                    'Accept: text/html,application/json',
                    "User-Agent: {$this->userAgent}",
                ],
                CURLOPT_ENCODING       => '',  // Accept all encodings for speed
            ]);

            $curlHandles[$key] = $ch;
            curl_multi_add_handle($multiHandle, $ch);
        }

        // Execute all requests in parallel
        $running = null;
        do {
            $status = curl_multi_exec($multiHandle, $running);
            if ($status > CURLM_OK) {
                break;
            }
            // Wait for activity on any connection, avoids busy-loop
            if ($running > 0) {
                curl_multi_select($multiHandle, 1.0);
            }
        } while ($running > 0);

        // Collect results
        $results = [];
        foreach ($platforms as $key => $platform) {
            $ch = $curlHandles[$key];
            $body = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            $exists = $this->checkExists($platform, $httpCode, $body, $curlError);

            $results[$key] = [
                'platform' => $platform['name'],
                'url'      => $platform['url'],
                'exists'   => $exists,
            ];

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);

        return [
            'username' => $username,
            'results'  => $results,
        ];
    }

    /**
     * Determine if a username exists on a platform based on the check type.
     */
    private function checkExists(array $platform, int $httpCode, ?string $body, string $curlError): bool {
        // If cURL failed entirely, treat as not found
        if (!empty($curlError) || $httpCode === 0) {
            return false;
        }

        switch ($platform['check']) {
            case 'status':
                return $httpCode === 200;

            case 'json':
                if ($httpCode !== 200) {
                    return false;
                }
                $decoded = json_decode($body ?? '', true);
                return !empty($decoded) && $decoded !== null;

            case 'body':
                if ($httpCode !== 200) {
                    return false;
                }
                $marker = $platform['match'] ?? '';
                return !empty($body) && stripos($body, $marker) !== false;

            default:
                return $httpCode === 200;
        }
    }

    /**
     * Normalize raw lookup data into Vignette's standard format.
     */
    public function normalize(array $data): array {
        if (isset($data['error'])) {
            return [
                'source' => 'username_osint',
                'status' => 'error',
                'error'  => $data['error'],
                'data'   => [],
            ];
        }

        $foundOn = [];
        $notFoundOn = [];
        $profiles = [];

        foreach ($data['results'] as $key => $result) {
            $profiles[] = [
                'platform' => $result['platform'],
                'url'      => $result['url'],
                'exists'   => $result['exists'],
            ];

            if ($result['exists']) {
                $foundOn[] = $key;
            } else {
                $notFoundOn[] = $key;
            }
        }

        return [
            'source' => 'username_osint',
            'status' => 'success',
            'data'   => [
                'username'     => $data['username'],
                'found_on'     => $foundOn,
                'not_found_on' => $notFoundOn,
                'profiles'     => $profiles,
                'total_found'  => count($foundOn),
                'total_checked' => count($profiles),
            ],
        ];
    }
}
