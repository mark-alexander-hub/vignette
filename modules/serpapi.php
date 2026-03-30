<?php

/**
 * Vignette — SerpAPI Web Search Module
 * Web search via SerpAPI (returns real Google results as structured JSON).
 * API docs: https://serpapi.com/search-api
 */

class SerpApiModule {

    private string $apiKey;
    private string $baseUrl = 'https://serpapi.com/search.json';

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Run a web search query via SerpAPI.
     */
    public function search(string $query, int $maxResults = 10): array {
        if (empty($this->apiKey)) {
            return ['error' => 'SerpAPI not configured — set your API key from https://serpapi.com/'];
        }

        $query = trim($query);
        if ($query === '') {
            return ['error' => 'Search query cannot be empty'];
        }

        $url = $this->baseUrl . '?' . http_build_query([
            'engine'  => 'google',
            'q'       => $query,
            'api_key' => $this->apiKey,
            'num'     => max(1, min(10, $maxResults)),
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Vignette-OSINT-Platform'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['error' => "cURL error: $curlError"];
        }

        if ($status === 429) {
            return ['error' => 'SerpAPI rate limit exceeded'];
        }

        if ($status !== 200) {
            $body = json_decode($response, true);
            $message = $body['error'] ?? "HTTP $status";
            return ['error' => "SerpAPI error: $message"];
        }

        $data = json_decode($response, true);
        if ($data === null) {
            return ['error' => 'Failed to parse SerpAPI response'];
        }

        return $data;
    }

    /**
     * Normalize SerpAPI response into Vignette's standard format.
     * Keeps source name as 'google_search' for compatibility with aggregator/profiler/frontend.
     */
    public function normalize(array $data): array {
        if (isset($data['error'])) {
            return [
                'source' => 'google_search',
                'status' => 'error',
                'error'  => $data['error'],
                'data'   => []
            ];
        }

        $results = [];
        foreach (($data['organic_results'] ?? []) as $item) {
            $url = $item['link'] ?? '';
            $domain = '';
            if ($url !== '') {
                $parsed = parse_url($url);
                $domain = $parsed['host'] ?? '';
            }

            $results[] = [
                'title'       => $item['title'] ?? '',
                'url'         => $url,
                'snippet'     => $item['snippet'] ?? '',
                'display_url' => $item['displayed_link'] ?? $url,
                'site_name'   => $domain,
            ];
        }

        $searchInfo = $data['search_information'] ?? [];

        return [
            'source' => 'google_search',
            'status' => 'success',
            'data'   => [
                'query'         => $data['search_parameters']['q'] ?? '',
                'total_results' => $searchInfo['total_results'] ?? '0',
                'search_time'   => (float)($searchInfo['time_taken_displayed'] ?? 0.0),
                'results'       => $results,
            ]
        ];
    }
}
