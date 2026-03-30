<?php

/**
 * Vignette — GitHub Module
 * Fetches public profile, repositories, and activity from GitHub API.
 * API docs: https://docs.github.com/en/rest
 */

class GitHubModule {

    private string $token;
    private string $baseUrl = 'https://api.github.com';

    public function __construct(string $token) {
        $this->token = $token;
    }

    /**
     * Fetch user profile by username.
     */
    public function getProfile(string $username): array {
        return $this->request("/users/" . urlencode($username));
    }

    /**
     * Fetch public repositories for a user.
     */
    public function getRepos(string $username, int $limit = 10): array {
        return $this->request("/users/" . urlencode($username) . "/repos?sort=updated&per_page={$limit}");
    }

    /**
     * Fetch recent public events (activity) for a user.
     */
    public function getEvents(string $username, int $limit = 10): array {
        return $this->request("/users/" . urlencode($username) . "/events/public?per_page={$limit}");
    }

    /**
     * Search for a user by email address.
     * Returns array of matching user profiles.
     */
    public function searchByEmail(string $email): array {
        $result = $this->request("/search/users?q=" . urlencode($email . " in:email"));
        return $result['items'] ?? [];
    }

    /**
     * Full lookup — profile + repos + events in one call.
     */
    public function fullLookup(string $username): array {
        $profile = $this->getProfile($username);

        if (isset($profile['error'])) {
            return $profile;
        }

        $repos = $this->getRepos($username, 10);
        $events = $this->getEvents($username, 10);

        return [
            'profile' => $profile,
            'repos' => is_array($repos) && !isset($repos['error']) ? $repos : [],
            'events' => is_array($events) && !isset($events['error']) ? $events : [],
        ];
    }

    /**
     * Normalize GitHub data into Vignette's standard format.
     */
    public function normalize(array $data): array {
        if (isset($data['error'])) {
            return [
                'source' => 'github',
                'status' => 'error',
                'error' => $data['error'],
                'data' => []
            ];
        }

        $profile = $data['profile'] ?? $data;
        $repos = $data['repos'] ?? [];

        $repoSummaries = [];
        foreach ($repos as $repo) {
            if (isset($repo['name'])) {
                $repoSummaries[] = [
                    'name' => $repo['name'],
                    'description' => $repo['description'] ?? '',
                    'language' => $repo['language'] ?? '',
                    'stars' => $repo['stargazers_count'] ?? 0,
                    'forks' => $repo['forks_count'] ?? 0,
                    'url' => $repo['html_url'] ?? '',
                    'updated_at' => $repo['updated_at'] ?? '',
                ];
            }
        }

        return [
            'source' => 'github',
            'status' => 'success',
            'data' => [
                'username' => $profile['login'] ?? '',
                'display_name' => $profile['name'] ?? '',
                'bio' => $profile['bio'] ?? '',
                'company' => $profile['company'] ?? '',
                'location' => $profile['location'] ?? '',
                'email' => $profile['email'] ?? '',
                'avatar_url' => $profile['avatar_url'] ?? '',
                'profile_url' => $profile['html_url'] ?? '',
                'public_repos' => $profile['public_repos'] ?? 0,
                'followers' => $profile['followers'] ?? 0,
                'following' => $profile['following'] ?? 0,
                'created_at' => $profile['created_at'] ?? '',
                'repos' => $repoSummaries,
            ]
        ];
    }

    /**
     * Make an authenticated GitHub API request.
     */
    private function request(string $endpoint): array {
        if (empty($this->token)) {
            return ['error' => 'GitHub token not configured — get one at github.com/settings/tokens'];
        }

        $ch = curl_init($this->baseUrl . $endpoint);

        $headers = [
            'User-Agent: Vignette-OSINT-Platform',
            'Accept: application/vnd.github.v3+json',
            'Authorization: token ' . $this->token,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 8
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 404) {
            return ['error' => 'GitHub user not found'];
        }

        if ($status === 403) {
            return ['error' => 'GitHub API rate limit exceeded'];
        }

        if ($status !== 200) {
            return ['error' => "GitHub API returned status $status"];
        }

        return json_decode($response, true) ?? [];
    }
}
