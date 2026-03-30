<?php

/**
 * Vignette — Gemini AI Module
 * Generates intelligence summaries from aggregated OSINT data.
 * API docs: https://ai.google.dev/api
 */

class GeminiModule {

    private string $apiKey;
    private array $models = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.0-flash-lite'];
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Generate an intelligence summary from a unified profile.
     *
     * @param array  $profile   The profile object from Profiler::build()
     * @param string $queryType The original query type
     * @return string The AI-generated summary, or empty string on failure
     */
    public function generateSummary(array $profile, string $queryType): string {
        if (empty($this->apiKey)) {
            return '';
        }

        $prompt = $this->buildPrompt($profile, $queryType);

        $payload = json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 1500,
                'thinkingConfig' => ['thinkingBudget' => 0],
            ]
        ]);

        // Try each model until one succeeds (handles per-model rate limits)
        foreach ($this->models as $model) {
            $url = $this->baseUrl . $model . ':generateContent?key=' . $this->apiKey;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($status === 200 && !$error) {
                $data = json_decode($response, true);
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if (!empty($text)) {
                    return $text;
                }
            }

            // If rate limited (429), try next model
            if ($status === 429) {
                continue;
            }

            // For other errors, don't retry
            if ($status !== 200) {
                break;
            }
        }

        return '';
    }

    /**
     * Build the prompt for Gemini based on available profile data.
     */
    private function buildPrompt(array $profile, string $queryType): string {
        $identity = $profile['identity'] ?? [];
        $risk = $profile['risk'] ?? [];
        $breaches = $profile['breaches'] ?? [];
        $github = $profile['github'] ?? [];
        $ipData = $profile['ip_data'] ?? [];
        $repos = $profile['repos'] ?? [];
        $whois = $profile['whois_data'] ?? [];
        $dns = $profile['dns_data'] ?? [];
        $ssl = $profile['ssl_data'] ?? [];
        $vt = $profile['virustotal_data'] ?? [];
        $usernames = $profile['username_profiles'] ?? [];

        $sections = [];
        $sections[] = "You are an OSINT intelligence analyst for the Vignette platform. Generate a concise 2-3 paragraph intelligence briefing based on the following data. Be factual, neutral, and professional. Do not speculate beyond what the data shows. Highlight notable findings, security posture, and potential areas of interest. Format with clear paragraphs.";

        $sections[] = "QUERY: {$identity['display_name']} (type: {$queryType})";

        if (!empty($identity['location'])) {
            $sections[] = "LOCATION: {$identity['location']}";
        }

        if (!empty($identity['bio'])) {
            $sections[] = "BIO: {$identity['bio']}";
        }

        // GitHub
        if (!empty($github)) {
            $ghInfo = "GITHUB: {$github['public_repos']} public repos, {$github['followers']} followers";
            if (!empty($github['company'])) $ghInfo .= ", company: {$github['company']}";
            if (!empty($github['created_at'])) $ghInfo .= ", member since: {$github['created_at']}";
            $sections[] = $ghInfo;
        }

        if (!empty($repos)) {
            $topRepos = array_slice($repos, 0, 3);
            $repoLines = [];
            foreach ($topRepos as $repo) {
                $line = "{$repo['name']}";
                if (!empty($repo['language'])) $line .= " [{$repo['language']}]";
                if (!empty($repo['stars'])) $line .= " ({$repo['stars']} stars)";
                $repoLines[] = $line;
            }
            $sections[] = "TOP REPOS: " . implode(', ', $repoLines);
        }

        // Username OSINT
        if (!empty($usernames['found_on'])) {
            $sections[] = "USERNAME FOUND ON: " . implode(', ', array_slice($usernames['found_on'], 0, 10))
                . " ({$usernames['total_found']}/{$usernames['total_checked']} platforms)";
        }

        // Breaches
        if (!empty($breaches['items'])) {
            $breachNames = array_map(fn($b) => $b['name'] ?? $b['Name'] ?? 'Unknown', $breaches['items']);
            $sections[] = "DATA BREACHES ({$breaches['count']}): " . implode(', ', array_slice($breachNames, 0, 5));
        }

        // WHOIS
        if (!empty($whois)) {
            $whoisInfo = "WHOIS: ";
            if (!empty($whois['domain'])) $whoisInfo .= "domain {$whois['domain']}";
            if (!empty($whois['registrar'])) $whoisInfo .= ", registrar: {$whois['registrar']}";
            if (!empty($whois['domain_age'])) $whoisInfo .= ", age: {$whois['domain_age']}";
            if (!empty($whois['registrant_org'])) $whoisInfo .= ", org: {$whois['registrant_org']}";
            if (!empty($whois['is_privacy_protected'])) $whoisInfo .= ", PRIVACY PROTECTED";
            $sections[] = $whoisInfo;
        }

        // DNS
        if (!empty($dns)) {
            $dnsInfo = "DNS: ";
            if (!empty($dns['mail_provider'])) $dnsInfo .= "mail: {$dns['mail_provider']}";
            if (!empty($dns['hosting'])) $dnsInfo .= ", hosting: " . implode('/', $dns['hosting']);
            $emailSec = $dns['email_security'] ?? [];
            if (!empty($emailSec)) {
                $dnsInfo .= ", email security: {$emailSec['score']}/100";
                $dnsInfo .= !empty($emailSec['spf']) ? ' (SPF)' : ' (NO SPF)';
                $dnsInfo .= !empty($emailSec['dmarc']) ? ' (DMARC)' : ' (NO DMARC)';
            }
            if (!empty($dns['verifications'])) {
                $dnsInfo .= ", verified services: " . implode(', ', $dns['verifications']);
            }
            $sections[] = $dnsInfo;
        }

        // SSL
        if (!empty($ssl)) {
            $sslInfo = "SSL: ";
            if (!empty($ssl['certificate_authority'])) $sslInfo .= "CA: {$ssl['certificate_authority']}";
            if (!empty($ssl['cert_type'])) $sslInfo .= ", type: {$ssl['cert_type']}";
            if (!empty($ssl['days_remaining'])) $sslInfo .= ", {$ssl['days_remaining']} days remaining";
            if (!empty($ssl['is_expired'])) $sslInfo .= ", EXPIRED";
            if (!empty($ssl['san_count'])) $sslInfo .= ", {$ssl['san_count']} SAN domains";
            $sections[] = $sslInfo;
        }

        // VirusTotal
        if (!empty($vt)) {
            $vtInfo = "VIRUSTOTAL: ";
            $vtInfo .= ($vt['malicious_count'] ?? 0) . " malicious, ";
            $vtInfo .= ($vt['suspicious_count'] ?? 0) . " suspicious, ";
            $vtInfo .= ($vt['harmless_count'] ?? 0) . " harmless";
            $vtInfo .= " out of " . ($vt['total_engines'] ?? 0) . " engines";
            if (!empty($vt['reputation'])) $vtInfo .= ", reputation: {$vt['reputation']}";
            $sections[] = $vtInfo;
        }

        // IP
        if (!empty($ipData)) {
            $ipInfo = "IP: ";
            if (!empty($ipData['ip'])) $ipInfo .= $ipData['ip'];
            if (!empty($ipData['org'])) $ipInfo .= ", {$ipData['org']}";
            if (!empty($ipData['city'])) $ipInfo .= ", {$ipData['city']}, {$ipData['country']}";
            $flags = [];
            if (!empty($ipData['is_vpn'])) $flags[] = 'VPN';
            if (!empty($ipData['is_proxy'])) $flags[] = 'Proxy';
            if (!empty($ipData['is_tor'])) $flags[] = 'Tor';
            if (!empty($flags)) $ipInfo .= ", FLAGS: " . implode(', ', $flags);
            $sections[] = $ipInfo;
        }

        // Risk
        $sections[] = "RISK SCORE: {$risk['score']}/100 ({$risk['level']})";
        if (!empty($risk['factors'])) {
            $sections[] = "RISK FACTORS: " . implode('; ', $risk['factors']);
        }

        return implode("\n\n", $sections);
    }
}
