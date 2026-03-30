<?php

/**
 * Vignette — Data Aggregator & Deduplicator
 * Merges results from multiple data sources into a unified structure.
 */

class Aggregator {

    /**
     * Merge results from all sources into a single unified dataset.
     *
     * @param array $sourceResults Keyed by source name, each containing normalized data
     * @return array Merged profile data
     */
    public function merge(array $sourceResults): array {
        $merged = [
            'display_name' => '',
            'avatar_url' => '',
            'location' => '',
            'bio' => '',
            'company' => '',
            'emails' => [],
            'usernames' => [],
            'social_links' => [],
            'breaches' => [],
            'ip_data' => [],
            'whois_data' => [],
            'dns_data' => [],
            'ssl_data' => [],
            'virustotal_data' => [],
            'google_results' => [],
            'phone_data' => [],
            'username_profiles' => [],
            'repos' => [],
            'sources_queried' => [],
            'sources_success' => 0,
            'sources_failed' => 0,
        ];

        // Track all candidate values for smart dedup
        $candidates = [
            'names' => [],
            'locations' => [],
            'bios' => [],
        ];

        foreach ($sourceResults as $sourceName => $result) {
            // Skip orchestrator metadata keys (not actual source results)
            if (str_starts_with($sourceName, '_')) {
                continue;
            }

            $merged['sources_queried'][] = $sourceName;

            if (($result['status'] ?? '') !== 'success') {
                // Don't count "not found" / "no results" as failures — they're expected
                $err = strtolower($result['error'] ?? '');
                $benign = str_contains($err, 'not found') || str_contains($err, 'no results')
                       || str_contains($err, 'not configured') || str_contains($err, 'token not configured');
                if (!$benign) {
                    $merged['sources_failed']++;
                }
                continue;
            }

            $merged['sources_success']++;
            $data = $result['data'] ?? [];

            switch ($sourceName) {
                case 'haveibeenpwned':
                    $merged['breaches'] = $this->deduplicateBreaches($data);
                    $merged['breach_count'] = $result['breach_count'] ?? count($merged['breaches']);
                    break;

                case 'github':
                    if (!empty($data['display_name'])) {
                        $candidates['names'][] = ['value' => $data['display_name'], 'source' => 'github', 'priority' => 10];
                    }
                    if (!empty($data['avatar_url'])) {
                        $merged['avatar_url'] = $merged['avatar_url'] ?: $data['avatar_url'];
                    }
                    if (!empty($data['location'])) {
                        $candidates['locations'][] = ['value' => $data['location'], 'source' => 'github', 'priority' => 10];
                    }
                    if (!empty($data['bio'])) {
                        $candidates['bios'][] = ['value' => $data['bio'], 'source' => 'github', 'priority' => 10];
                    }
                    if (!empty($data['email'])) {
                        $merged['emails'] = $this->addUnique($merged['emails'], $data['email']);
                    }
                    if (!empty($data['username'])) {
                        $merged['usernames'] = $this->addUnique($merged['usernames'], $data['username']);
                    }
                    if (!empty($data['profile_url'])) {
                        $merged['social_links']['GitHub'] = $data['profile_url'];
                    }
                    if (!empty($data['repos'])) {
                        $merged['repos'] = $data['repos'];
                    }
                    if (!empty($data['company'])) {
                        $merged['company'] = $merged['company'] ?: $data['company'];
                    }
                    $merged['github'] = [
                        'public_repos' => $data['public_repos'] ?? 0,
                        'followers' => $data['followers'] ?? 0,
                        'following' => $data['following'] ?? 0,
                        'company' => $data['company'] ?? '',
                        'created_at' => $data['created_at'] ?? '',
                    ];
                    break;

                case 'ipinfo':
                    $merged['ip_data'] = $data;
                    if (!empty($data['city']) && !empty($data['country'])) {
                        $ipLocation = $data['city'] . ', ' . ($data['region'] ?? '') . ', ' . $data['country'];
                        $candidates['locations'][] = ['value' => $ipLocation, 'source' => 'ipinfo', 'priority' => 5];
                    }
                    break;

                case 'whois':
                    $merged['whois_data'] = $data;
                    if (!empty($data['registrant_org'])) {
                        $candidates['names'][] = ['value' => $data['registrant_org'], 'source' => 'whois', 'priority' => 5];
                        $merged['company'] = $merged['company'] ?: $data['registrant_org'];
                    }
                    if (!empty($data['registrant_country'])) {
                        $candidates['locations'][] = ['value' => $data['registrant_country'], 'source' => 'whois', 'priority' => 3];
                    }
                    break;

                case 'dns':
                    $merged['dns_data'] = $data;
                    break;

                case 'ssl':
                    $merged['ssl_data'] = $data;
                    // Extract org from OV/EV cert subject
                    if (!empty($data['subject_cn']) && !empty($data['cert_type']) && strpos($data['cert_type'], 'DV') === false) {
                        $merged['company'] = $merged['company'] ?: $data['subject_cn'];
                    }
                    break;

                case 'gravatar':
                    if (!empty($data['display_name'])) {
                        $candidates['names'][] = ['value' => $data['display_name'], 'source' => 'gravatar', 'priority' => 8];
                    }
                    if (!empty($data['avatar_url'])) {
                        $merged['avatar_url'] = $merged['avatar_url'] ?: $data['avatar_url'];
                    }
                    if (!empty($data['location'])) {
                        $candidates['locations'][] = ['value' => $data['location'], 'source' => 'gravatar', 'priority' => 7];
                    }
                    if (!empty($data['bio'])) {
                        $candidates['bios'][] = ['value' => $data['bio'], 'source' => 'gravatar', 'priority' => 7];
                    }
                    if (!empty($data['profile_url'])) {
                        $merged['social_links']['Gravatar'] = $data['profile_url'];
                    }
                    if (!empty($data['social_links'])) {
                        foreach ($data['social_links'] as $link) {
                            $platform = ucfirst($link['shortname'] ?? $link['name'] ?? 'unknown');
                            $url = $link['url'] ?? $link['value'] ?? '';
                            if ($url) {
                                $merged['social_links'][$platform] = $merged['social_links'][$platform] ?? $url;
                            }
                        }
                    }
                    if (!empty($data['emails'])) {
                        foreach ($data['emails'] as $email) {
                            $merged['emails'] = $this->addUnique($merged['emails'], $email);
                        }
                    }
                    break;

                case 'username_osint':
                    $merged['username_profiles'] = $data;
                    // Cross-pollinate social links from found platforms
                    if (!empty($data['profiles'])) {
                        foreach ($data['profiles'] as $profile) {
                            if (!empty($profile['exists']) && !empty($profile['url'])) {
                                $platform = $profile['platform'] ?? '';
                                if ($platform && !isset($merged['social_links'][$platform])) {
                                    $merged['social_links'][$platform] = $profile['url'];
                                }
                            }
                        }
                    }
                    break;

                case 'virustotal':
                    $merged['virustotal_data'] = $data;
                    break;

                case 'phone_info':
                    $merged['phone_data'] = $data;
                    if (!empty($data['country'])) {
                        $candidates['locations'][] = ['value' => $data['country'], 'source' => 'phone_info', 'priority' => 3];
                    }
                    break;

                case 'google_search':
                    $merged['google_results'] = $data;
                    // Extract social media profiles from Google Search results
                    // These are more accurate than username-guessed profiles because
                    // Google found them in context of the person's name
                    if (!empty($data['results'])) {
                        $socialPatterns = [
                            'Instagram'  => '#instagram\.com/([a-zA-Z0-9._]+)#',
                            'X (Twitter)'=> '#(?:twitter\.com|x\.com)/([a-zA-Z0-9_]+)#',
                            'Facebook'   => '#facebook\.com/([a-zA-Z0-9.]+)#',
                            'LinkedIn'   => '#linkedin\.com/in/([a-zA-Z0-9_-]+)#',
                            'YouTube'    => '#youtube\.com/(?:@|channel/|user/)([a-zA-Z0-9_-]+)#',
                            'TikTok'     => '#tiktok\.com/@([a-zA-Z0-9._]+)#',
                            'Pinterest'  => '#pinterest\.com/([a-zA-Z0-9_]+)#',
                            'Reddit'     => '#reddit\.com/(?:user|u)/([a-zA-Z0-9_-]+)#',
                            'Threads'    => '#threads\.net/@([a-zA-Z0-9._]+)#',
                            'Snapchat'   => '#snapchat\.com/add/([a-zA-Z0-9._-]+)#',
                            'Kaggle'     => '#kaggle\.com/([a-zA-Z0-9_-]+)#',
                        ];
                        foreach ($data['results'] as $gResult) {
                            $url = $gResult['url'] ?? '';
                            foreach ($socialPatterns as $platform => $pattern) {
                                if (preg_match($pattern, $url, $m)) {
                                    $profileUrl = $url;
                                    $username = $m[1];
                                    // Skip generic pages (help, about, login, etc.)
                                    if (in_array(strtolower($username), ['help', 'about', 'login', 'signup', 'explore', 'settings', 'privacy', 'terms'])) continue;
                                    // Google-discovered profiles OVERWRITE username-guessed ones
                                    // (they're contextually verified — Google found them for this person)
                                    $merged['social_links'][$platform] = $profileUrl;
                                    // Add discovered username
                                    $merged['usernames'] = $this->addUnique($merged['usernames'], $username);
                                    // Also inject into username_profiles so they appear in Social Profiles card
                                    if (!empty($merged['username_profiles']['profiles'])) {
                                        $replaced = false;
                                        foreach ($merged['username_profiles']['profiles'] as &$p) {
                                            if (($p['platform'] ?? '') === $platform) {
                                                $p['url'] = $profileUrl;
                                                $p['exists'] = true;
                                                $p['web_verified'] = true;
                                                $replaced = true;
                                                break;
                                            }
                                        }
                                        unset($p);
                                        if (!$replaced) {
                                            $merged['username_profiles']['profiles'][] = [
                                                'platform' => $platform,
                                                'url' => $profileUrl,
                                                'exists' => true,
                                                'web_verified' => true,
                                            ];
                                            $merged['username_profiles']['total_found'] = ($merged['username_profiles']['total_found'] ?? 0) + 1;
                                            $merged['username_profiles']['found_on'][] = strtolower($platform);
                                        }
                                    }
                                    break; // One platform match per result
                                }
                            }
                        }
                    }
                    break;
            }
        }

        // Resolve best values from candidates (highest priority wins)
        $merged['display_name'] = $this->resolveBest($candidates['names']);
        $merged['location'] = $this->resolveBest($candidates['locations']);
        $merged['bio'] = $this->resolveBest($candidates['bios']);

        // Deduplicate social links (normalize platform names)
        $merged['social_links'] = $this->deduplicateSocialLinks($merged['social_links']);

        return $merged;
    }

    /**
     * Resolve the best value from prioritized candidates.
     */
    private function resolveBest(array $candidates): string {
        if (empty($candidates)) return '';

        // Sort by priority descending, pick the highest
        usort($candidates, fn($a, $b) => $b['priority'] - $a['priority']);

        return $candidates[0]['value'] ?? '';
    }

    /**
     * Deduplicate breaches by name.
     */
    private function deduplicateBreaches(array $breaches): array {
        $seen = [];
        $unique = [];
        foreach ($breaches as $breach) {
            $key = strtolower($breach['Name'] ?? $breach['name'] ?? '');
            if ($key && !isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $breach;
            }
        }
        return $unique;
    }

    /**
     * Deduplicate social links by normalizing URLs.
     */
    private function deduplicateSocialLinks(array $links): array {
        $normalized = [];
        $seenDomains = [];

        foreach ($links as $platform => $url) {
            // Extract domain from URL to detect duplicates
            $domain = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
            $domain = preg_replace('/^www\./', '', $domain);

            // Skip if we already have a link to this domain
            if (isset($seenDomains[$domain])) {
                continue;
            }

            $seenDomains[$domain] = true;
            $normalized[$platform] = $url;
        }

        return $normalized;
    }

    /**
     * Add a value to an array if not already present (case-insensitive).
     */
    private function addUnique(array $arr, string $value): array {
        $value = trim($value);
        if ($value === '') return $arr;

        // Case-insensitive dedup
        $lower = strtolower($value);
        foreach ($arr as $existing) {
            if (strtolower($existing) === $lower) {
                return $arr;
            }
        }

        $arr[] = $value;
        return $arr;
    }
}
