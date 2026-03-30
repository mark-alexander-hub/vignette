<?php

/**
 * Vignette — Profiler
 * Builds a unified profile object from aggregated data and computes risk score.
 */

require_once __DIR__ . '/../api/Services/RiskScoringService.php';

class Profiler {

    /**
     * Build a complete profile from aggregated data.
     *
     * @param array  $mergedData   Output from Aggregator::merge()
     * @param string $queryValue   Original search query
     * @param string $queryType    Original query type
     * @return array Complete profile ready for display/storage
     */
    public function build(array $mergedData, string $queryValue, string $queryType): array {
        // Calculate risk score from all data sources
        $riskScore = 0;
        if (!empty($mergedData['breaches'])) {
            $riskService = new RiskScoringService();
            $riskScore = $riskService->calculate($mergedData['breaches']);
        }

        // Add domain/IP-based risk factors
        if ($queryType === 'domain' || $queryType === 'ip') {
            $riskScore += $this->calculateDomainRisk($mergedData);
            $riskScore = min($riskScore, 100);
        }

        // Build risk factors list
        $riskFactors = $this->analyzeRiskFactors($mergedData);

        return [
            'query' => [
                'value' => $queryValue,
                'type' => $queryType,
            ],
            'identity' => [
                'display_name' => $mergedData['display_name'] ?: $queryValue,
                'avatar_url' => $mergedData['avatar_url'] ?? '',
                'bio' => $mergedData['bio'] ?? '',
                'location' => $mergedData['location'] ?? '',
                'company' => $mergedData['company'] ?? '',
                'emails' => $mergedData['emails'] ?? [],
                'usernames' => $mergedData['usernames'] ?? [],
            ],
            'social_links' => $mergedData['social_links'] ?? [],
            'github' => $mergedData['github'] ?? null,
            'repos' => $mergedData['repos'] ?? [],
            'breaches' => [
                'count' => $mergedData['breach_count'] ?? 0,
                'items' => $mergedData['breaches'] ?? [],
            ],
            'ip_data' => $mergedData['ip_data'] ?? null,
            'whois_data' => $mergedData['whois_data'] ?? null,
            'dns_data' => $mergedData['dns_data'] ?? null,
            'ssl_data' => $mergedData['ssl_data'] ?? null,
            'virustotal_data' => $mergedData['virustotal_data'] ?? null,
            'google_results' => $mergedData['google_results'] ?? null,
            'phone_data' => $mergedData['phone_data'] ?? null,
            'username_profiles' => $mergedData['username_profiles'] ?? null,
            'risk' => [
                'score' => $riskScore,
                'level' => $this->riskLevel($riskScore),
                'factors' => $riskFactors,
            ],
            'meta' => [
                'sources_queried' => $mergedData['sources_queried'] ?? [],
                'sources_success' => $mergedData['sources_success'] ?? 0,
                'sources_failed' => $mergedData['sources_failed'] ?? 0,
                'timestamp' => date('c'),
            ],
        ];
    }

    /**
     * Determine risk level label from numeric score.
     */
    private function riskLevel(int $score): string {
        if ($score === 0) return 'clean';
        if ($score <= 20) return 'low';
        if ($score <= 50) return 'moderate';
        if ($score <= 75) return 'high';
        return 'critical';
    }

    /**
     * Analyze specific risk factors from the data.
     */
    private function analyzeRiskFactors(array $data): array {
        $factors = [];

        $breaches = $data['breaches'] ?? [];
        if (!empty($breaches)) {
            $factors[] = count($breaches) . ' data breach(es) found';

            foreach ($breaches as $breach) {
                if (isset($breach['data_classes']) && in_array('Passwords', $breach['data_classes'])) {
                    $factors[] = 'Password exposed in breach: ' . ($breach['name'] ?? 'unknown');
                }
                if (!empty($breach['is_sensitive'])) {
                    $factors[] = 'Sensitive breach: ' . ($breach['name'] ?? 'unknown');
                }
            }
        }

        $ipData = $data['ip_data'] ?? [];
        if (!empty($ipData['is_vpn'])) {
            $factors[] = 'VPN detected on IP';
        }
        if (!empty($ipData['is_tor'])) {
            $factors[] = 'Tor exit node detected';
        }
        if (!empty($ipData['is_proxy'])) {
            $factors[] = 'Proxy detected on IP';
        }

        // WHOIS risk factors
        $whois = $data['whois_data'] ?? [];
        if (!empty($whois)) {
            if (!empty($whois['is_privacy_protected'])) {
                $factors[] = 'Domain WHOIS privacy protection enabled';
            }
            if (!empty($whois['created_date'])) {
                $created = strtotime($whois['created_date']);
                if ($created) {
                    $ageDays = (time() - $created) / 86400;
                    if ($ageDays < 30) {
                        $factors[] = 'Domain registered less than 30 days ago (very new)';
                    } elseif ($ageDays < 180) {
                        $factors[] = 'Domain registered less than 6 months ago';
                    }
                }
            }
            if (!empty($whois['expiry_date'])) {
                $expiry = strtotime($whois['expiry_date']);
                if ($expiry && ($expiry - time()) < 90 * 86400 && $expiry > time()) {
                    $factors[] = 'Domain expires within 90 days';
                }
            }
        }

        // SSL risk factors
        $ssl = $data['ssl_data'] ?? [];
        if (!empty($ssl)) {
            if (!empty($ssl['is_expired'])) {
                $factors[] = 'SSL certificate is expired';
            } elseif (!empty($ssl['is_expiring_soon'])) {
                $factors[] = 'SSL certificate expires within 30 days';
            }
            $isEc = !empty($ssl['key_type']) && stripos($ssl['key_type'], 'EC') !== false;
            $weakKey = $isEc ? ($ssl['key_bits'] ?? 0) < 224 : ($ssl['key_bits'] ?? 0) < 2048;
            if (!empty($ssl['key_bits']) && $weakKey) {
                $factors[] = 'Weak SSL key size (' . $ssl['key_type'] . ' ' . $ssl['key_bits'] . ' bits)';
            }
        } elseif (isset($data['ssl_data']) && empty($data['ssl_data']) && in_array('ssl', $data['sources_queried'] ?? [])) {
            $factors[] = 'No SSL certificate detected';
        }

        // DNS/Email security risk factors
        $dns = $data['dns_data'] ?? [];
        if (!empty($dns)) {
            $emailSec = $dns['email_security'] ?? [];
            if (empty($emailSec['spf'])) {
                $factors[] = 'No SPF record — vulnerable to email spoofing';
            }
            if (empty($emailSec['dmarc'])) {
                $factors[] = 'No DMARC policy configured';
            }
        }

        // VirusTotal risk factors
        $vt = $data['virustotal_data'] ?? [];
        if (!empty($vt)) {
            if (!empty($vt['is_malicious'])) {
                $factors[] = 'Flagged as malicious by ' . ($vt['malicious_count'] ?? 0) . ' security engine(s)';
            }
            if (!empty($vt['suspicious_count']) && $vt['suspicious_count'] > 0) {
                $factors[] = 'Flagged as suspicious by ' . $vt['suspicious_count'] . ' engine(s)';
            }
        }

        if (empty($factors)) {
            $factors[] = 'No significant risk indicators found';
        }

        return $factors;
    }

    /**
     * Calculate numeric risk score contribution from domain data.
     */
    private function calculateDomainRisk(array $data): int {
        $score = 0;

        $whois = $data['whois_data'] ?? [];
        if (!empty($whois)) {
            // New domain
            if (!empty($whois['created_date'])) {
                $created = strtotime($whois['created_date']);
                if ($created) {
                    $ageDays = (time() - $created) / 86400;
                    if ($ageDays < 30) $score += 25;
                    elseif ($ageDays < 180) $score += 10;
                    elseif ($ageDays < 365) $score += 5;
                }
            }
            // Privacy protected + young domain
            if (!empty($whois['is_privacy_protected'])) {
                $score += 5;
            }
            // Expiring soon
            if (!empty($whois['expiry_date'])) {
                $expiry = strtotime($whois['expiry_date']);
                if ($expiry && ($expiry - time()) < 90 * 86400 && $expiry > time()) {
                    $score += 10;
                }
            }
        }

        // SSL issues
        $ssl = $data['ssl_data'] ?? [];
        if (!empty($ssl)) {
            if (!empty($ssl['is_expired'])) $score += 20;
            elseif (!empty($ssl['is_expiring_soon'])) $score += 10;
            $isEc = !empty($ssl['key_type']) && stripos($ssl['key_type'], 'EC') !== false;
            $weakKey = $isEc ? ($ssl['key_bits'] ?? 0) < 224 : ($ssl['key_bits'] ?? 0) < 2048;
            if (!empty($ssl['key_bits']) && $weakKey) $score += 10;
        }

        // Email security
        $dns = $data['dns_data'] ?? [];
        if (!empty($dns)) {
            $emailSec = $dns['email_security'] ?? [];
            if (empty($emailSec['spf'])) $score += 10;
            if (empty($emailSec['dmarc'])) $score += 5;
        }

        // VirusTotal
        $vt = $data['virustotal_data'] ?? [];
        if (!empty($vt)) {
            $malicious = $vt['malicious_count'] ?? 0;
            if ($malicious > 5) $score += 30;
            elseif ($malicious > 0) $score += 15;
            $suspicious = $vt['suspicious_count'] ?? 0;
            if ($suspicious > 0) $score += 10;
        }

        return $score;
    }
}
