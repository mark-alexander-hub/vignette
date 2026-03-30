<?php

/**
 * Vignette — Search Orchestrator
 * Dispatches search queries to relevant modules based on query type.
 * Runs tasks sequentially with per-module timing and a 30-second total time budget.
 */

require_once __DIR__ . '/../modules/hibp.php';
require_once __DIR__ . '/../modules/github.php';
require_once __DIR__ . '/../modules/ipinfo.php';
require_once __DIR__ . '/../modules/whois.php';
require_once __DIR__ . '/../modules/dns.php';
require_once __DIR__ . '/../modules/ssl.php';
require_once __DIR__ . '/../modules/gravatar.php';
require_once __DIR__ . '/../modules/username_osint.php';
require_once __DIR__ . '/../modules/virustotal.php';
require_once __DIR__ . '/../modules/serpapi.php';
require_once __DIR__ . '/../modules/phone_info.php';

class Orchestrator {

    private array $apiKeys;

    public function __construct(array $apiKeys) {
        $this->apiKeys = $apiKeys;
    }

    /**
     * Execute a search across all relevant data sources.
     *
     * @param string $queryValue The search input
     * @param string $queryType  One of: name, email, phone, username, ip, domain
     * @return array Results keyed by source name
     */
    public function search(string $queryValue, string $queryType): array {
        $results = [];
        $timings = [];
        $totalStart = microtime(true);
        $maxTotalTime = 30; // seconds

        // Determine which modules to run based on query type
        $tasks = $this->planTasks($queryValue, $queryType);

        // Execute all tasks sequentially with per-module timing and total time budget
        foreach ($tasks as $taskName => $taskFn) {
            // Skip if we've exceeded total time budget
            if ((microtime(true) - $totalStart) > $maxTotalTime) {
                $results[$taskName] = [
                    'source' => $taskName,
                    'status' => 'skipped',
                    'error' => 'Search time limit exceeded',
                    'data' => []
                ];
                $timings[$taskName] = 0;
                continue;
            }

            $taskStart = microtime(true);
            try {
                $results[$taskName] = $taskFn();
            } catch (\Throwable $e) {
                $results[$taskName] = [
                    'source' => $taskName,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'data' => []
                ];
            }
            $timings[$taskName] = round((microtime(true) - $taskStart) * 1000);
        }

        // Attach timing metadata
        $results['_timings'] = $timings;
        $results['_total_time'] = round((microtime(true) - $totalStart) * 1000);

        return $results;
    }

    /**
     * Plan which data source tasks to run based on query type.
     */
    private function planTasks(string $queryValue, string $queryType): array {
        $tasks = [];

        switch ($queryType) {
            case 'email':
                $tasks['haveibeenpwned'] = function () use ($queryValue) {
                    $mod = new HibpModule($this->apiKeys['hibp']['api_key'] ?? '');
                    $raw = $mod->checkBreaches($queryValue);
                    return $mod->normalize($raw);
                };
                $tasks['github'] = function () use ($queryValue) {
                    $mod = new GitHubModule($this->apiKeys['github']['token'] ?? '');
                    $users = $mod->searchByEmail($queryValue);
                    if (!empty($users) && isset($users[0]['login'])) {
                        $raw = $mod->fullLookup($users[0]['login']);
                        return $mod->normalize($raw);
                    }
                    return [
                        'source' => 'github',
                        'status' => 'success',
                        'data' => ['note' => 'No GitHub user found for this email']
                    ];
                };
                $tasks['gravatar'] = function () use ($queryValue) {
                    $mod = new GravatarModule();
                    $raw = $mod->lookup($queryValue);
                    return $mod->normalize($raw);
                };
                $tasks['google_search'] = function () use ($queryValue) {
                    $mod = new SerpApiModule($this->apiKeys['serpapi']['api_key'] ?? '');
                    $raw = $mod->search($queryValue);
                    return $mod->normalize($raw);
                };
                break;

            case 'username':
                $tasks['github'] = function () use ($queryValue) {
                    $mod = new GitHubModule($this->apiKeys['github']['token'] ?? '');
                    $raw = $mod->fullLookup($queryValue);
                    return $mod->normalize($raw);
                };
                $tasks['username_osint'] = function () use ($queryValue) {
                    $mod = new UsernameOsintModule();
                    $raw = $mod->lookup($queryValue);
                    return $mod->normalize($raw);
                };
                $tasks['google_search'] = function () use ($queryValue) {
                    $mod = new SerpApiModule($this->apiKeys['serpapi']['api_key'] ?? '');
                    $raw = $mod->search('"' . $queryValue . '"');
                    return $mod->normalize($raw);
                };
                break;

            case 'ip':
                $tasks['ipinfo'] = function () use ($queryValue) {
                    $mod = new IpInfoModule($this->apiKeys['ipinfo']['token'] ?? '');
                    $raw = $mod->lookup($queryValue);
                    return $mod->normalize($raw);
                };
                $tasks['virustotal'] = function () use ($queryValue) {
                    $mod = new VirusTotalModule($this->apiKeys['virustotal']['api_key'] ?? '');
                    $raw = $mod->lookupIp($queryValue);
                    return $mod->normalize($raw, 'ip');
                };
                break;

            case 'name':
                // Username OSINT — try multiple username patterns from name
                $tasks['username_osint'] = function () use ($queryValue) {
                    $mod = new UsernameOsintModule();
                    $parts = preg_split('/\s+/', strtolower(trim($queryValue)));

                    // Generate username variants to try
                    $variants = [];
                    if (count($parts) >= 2) {
                        $first = $parts[0];
                        $last = $parts[count($parts) - 1];
                        $variants[] = implode('', $parts);          // bellarolland
                        $variants[] = implode('_', $parts);         // bella_rolland
                        $variants[] = implode('.', $parts);         // bella.rolland
                        $variants[] = $first . $last;               // bellarolland (same as first if 2 parts)
                        $variants[] = $first . '_' . $last;         // bella_rolland
                        $variants[] = $first . '.' . $last;         // bella.rolland
                        $variants[] = $first[0] . $last;            // brolland
                        $variants[] = $first . $last[0];            // bellar
                        $variants[] = $last . $first;               // rollandbella
                        $variants[] = $last . '_' . $first;         // rolland_bella
                    } else {
                        $variants[] = $parts[0];
                    }
                    $variants = array_unique(array_filter($variants));

                    // Run first variant as the primary lookup
                    $primaryUsername = $variants[0];
                    $raw = $mod->lookup($primaryUsername);
                    $result = $mod->normalize($raw);
                    $result['data']['searched_variants'] = [$primaryUsername];

                    // Try additional variants and merge any new found profiles
                    foreach (array_slice($variants, 1, 4) as $altUsername) {
                        // Skip if username has invalid chars for the module
                        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $altUsername)) continue;

                        $altRaw = $mod->lookup($altUsername);
                        $altResult = $mod->normalize($altRaw);
                        $result['data']['searched_variants'][] = $altUsername;

                        if (!empty($altResult['data']['profiles'])) {
                            // Track existing found platforms to avoid duplicates
                            $existingFound = [];
                            foreach ($result['data']['profiles'] ?? [] as $p) {
                                if ($p['exists']) $existingFound[$p['platform']] = true;
                            }
                            foreach ($altResult['data']['profiles'] as $p) {
                                if ($p['exists'] && !isset($existingFound[$p['platform']])) {
                                    $result['data']['profiles'][] = $p;
                                    $result['data']['found_on'][] = strtolower($p['platform']);
                                    $result['data']['total_found']++;
                                }
                            }
                        }
                    }

                    // Update total_checked to reflect unique platforms checked
                    $result['data']['total_checked'] = count(array_unique(
                        array_column($result['data']['profiles'] ?? [], 'platform')
                    ));

                    return $result;
                };
                // GitHub search by name
                $tasks['github'] = function () use ($queryValue) {
                    $mod = new GitHubModule($this->apiKeys['github']['token'] ?? '');
                    $url = 'https://api.github.com/search/users?q=' . urlencode($queryValue . ' in:name');
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => [
                            'User-Agent: Vignette-OSINT-Platform',
                            'Accept: application/vnd.github.v3+json',
                            'Authorization: token ' . ($this->apiKeys['github']['token'] ?? '')
                        ],
                        CURLOPT_TIMEOUT => 8
                    ]);
                    $response = curl_exec($ch);
                    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($status !== 200) {
                        return ['source' => 'github', 'status' => 'error', 'error' => "GitHub API status $status", 'data' => []];
                    }

                    $result = json_decode($response, true);
                    $users = array_slice($result['items'] ?? [], 0, 5);

                    if (!empty($users)) {
                        $full = $mod->fullLookup($users[0]['login']);
                        return $mod->normalize($full);
                    }

                    return ['source' => 'github', 'status' => 'success', 'data' => ['note' => 'No GitHub users found for this name']];
                };
                $tasks['google_search'] = function () use ($queryValue) {
                    $mod = new SerpApiModule($this->apiKeys['serpapi']['api_key'] ?? '');
                    $raw = $mod->search('"' . $queryValue . '"');
                    return $mod->normalize($raw);
                };
                break;

            case 'domain':
                // WHOIS registration data
                $tasks['whois'] = function () use ($queryValue) {
                    $mod = new WhoisModule();
                    $raw = $mod->lookup($queryValue);
                    return $mod->normalize($raw);
                };
                // DNS records
                $tasks['dns'] = function () use ($queryValue) {
                    $mod = new DnsModule();
                    $raw = $mod->lookup($queryValue);
                    return $mod->normalize($raw);
                };
                // SSL certificate
                $tasks['ssl'] = function () use ($queryValue) {
                    $mod = new SslModule();
                    $raw = $mod->lookup($queryValue);
                    return $mod->normalize($raw);
                };
                // VirusTotal domain reputation
                $tasks['virustotal'] = function () use ($queryValue) {
                    $mod = new VirusTotalModule($this->apiKeys['virustotal']['api_key'] ?? '');
                    $raw = $mod->lookupDomain($queryValue);
                    return $mod->normalize($raw, 'domain');
                };
                $tasks['google_search'] = function () use ($queryValue) {
                    $mod = new SerpApiModule($this->apiKeys['serpapi']['api_key'] ?? '');
                    $raw = $mod->search('site:' . $queryValue . ' OR "' . $queryValue . '"');
                    return $mod->normalize($raw);
                };
                // IP resolution via IPInfo
                $tasks['ipinfo'] = function () use ($queryValue) {
                    $ip = gethostbyname($queryValue);
                    if ($ip === $queryValue) {
                        return ['source' => 'ipinfo', 'status' => 'error', 'error' => 'Could not resolve domain', 'data' => []];
                    }
                    $mod = new IpInfoModule($this->apiKeys['ipinfo']['token'] ?? '');
                    $raw = $mod->lookup($ip);
                    $normalized = $mod->normalize($raw);
                    $normalized['data']['resolved_domain'] = $queryValue;
                    $normalized['data']['resolved_ip'] = $ip;
                    return $normalized;
                };
                break;

            case 'phone':
                // Phone number parsing and validation
                $tasks['phone_info'] = function () use ($queryValue) {
                    $mod = new PhoneInfoModule($this->apiKeys['numverify']['api_key'] ?? '');
                    $raw = $mod->lookup($queryValue);
                    return $mod->normalize($raw);
                };
                $tasks['google_search'] = function () use ($queryValue) {
                    $mod = new SerpApiModule($this->apiKeys['serpapi']['api_key'] ?? '');
                    $raw = $mod->search('"' . $queryValue . '"');
                    return $mod->normalize($raw);
                };
                break;
        }

        return $tasks;
    }
}
