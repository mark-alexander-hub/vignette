<?php

/**
 * Vignette — Google Custom Search Module
 * Web search via Google Custom Search JSON API v1.
 * API docs: https://developers.google.com/custom-search/v1/overview
 */

class GoogleSearchModule {

    private string $apiKey;
    private string $cx;
    private string $baseUrl = 'https://www.googleapis.com/customsearch/v1';

    public function __construct(string $apiKey, string $cx) {
        $this->apiKey = $apiKey;
        $this->cx = $cx;
    }

    /**
     * Run a web search query.
     */
    public function search(string $query, int $maxResults = 10): array {
        if (empty($this->apiKey) || empty($this->cx)) {
            return [
                'error' => 'Google Custom Search not configured — set your API key and Search Engine ID. '
                         . 'Get an API key at https://console.cloud.google.com/apis/credentials and '
                         . 'create a search engine at https://programmablesearchengine.google.com/'
            ];
        }

        $query = trim($query);
        if ($query === '') {
            return ['error' => 'Search query cannot be empty'];
        }

        // Google CSE allows num between 1 and 10 per request
        $num = max(1, min(10, $maxResults));

        $url = $this->baseUrl . '?' . http_build_query([
            'key' => $this->apiKey,
            'cx'  => $this->cx,
            'q'   => $query,
            'num' => $num,
        ]);

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
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['error' => "cURL error: $curlError"];
        }

        if ($status === 402 || $status === 429) {
            return [
                'error' => 'Google Custom Search API quota exceeded — check your daily limit at '
                         . 'https://console.cloud.google.com/apis/api/customsearch.googleapis.com/quotas'
            ];
        }

        if ($status !== 200) {
            $body = json_decode($response, true);
            $message = $body['error']['message'] ?? "HTTP $status";
            return ['error' => "Google Custom Search API error: $message"];
        }

        $data = json_decode($response, true);
        if ($data === null) {
            return ['error' => 'Failed to parse Google Custom Search response'];
        }

        return $data;
    }

    /**
     * Normalize Google Custom Search data into Vignette's standard format.
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
        foreach (($data['items'] ?? []) as $item) {
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
                'display_url' => $item['formattedUrl'] ?? $url,
                'site_name'   => $domain,
            ];
        }

        return [
            'source' => 'google_search',
            'status' => 'success',
            'data'   => [
                'query'         => $data['queries']['request'][0]['searchTerms'] ?? '',
                'total_results' => $data['searchInformation']['totalResults'] ?? '0',
                'search_time'   => (float)($data['searchInformation']['searchTime'] ?? 0.0),
                'results'       => $results,
            ]
        ];
    }
}
