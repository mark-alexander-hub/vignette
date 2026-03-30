<?php

/**
 * Vignette -- Relationship Mapper
 * Finds connections between the current search and previous searches
 * by comparing emails, usernames, IPs, registrars, hosting, SSL CAs, and names.
 */

class RelationshipMapper {

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Find relationships for a given search result.
     * Looks at the current profile data and finds connections to previous searches.
     *
     * @param array  $profile    The unified profile built by the Profiler
     * @param string $queryValue The raw search query (e.g. "john@example.com")
     * @param string $queryType  The search type (email, domain, ip, etc.)
     * @return array List of relationship arrays
     */
    public function findRelationships(array $profile, string $queryValue, string $queryType): array {
        $relationships = [];

        // Gather current profile attributes for matching
        $currentEmails    = $profile['identity']['emails'] ?? [];
        $currentUsernames = $profile['identity']['usernames'] ?? [];
        $currentName      = $profile['identity']['display_name'] ?? '';
        $currentIps       = $this->extractIps($profile);
        $currentRegistrar = $profile['whois_data']['registrar'] ?? '';
        $currentHosting   = $profile['dns_data']['hosting'] ?? [];
        $currentSslIssuer = $profile['ssl_data']['issuer'] ?? '';

        // Add the query value itself as a searchable attribute
        if ($queryType === 'email' && !in_array($queryValue, $currentEmails, true)) {
            $currentEmails[] = $queryValue;
        }
        if ($queryType === 'username' && !in_array($queryValue, $currentUsernames, true)) {
            $currentUsernames[] = $queryValue;
        }
        if ($queryType === 'ip' && !in_array($queryValue, $currentIps, true)) {
            $currentIps[] = $queryValue;
        }
        if ($queryType === 'name' && empty($currentName)) {
            $currentName = $queryValue;
        }

        // 1. Shared emails -- search profiles.known_emails JSON
        if (!empty($currentEmails)) {
            $matches = $this->findInProfileJson('known_emails', $currentEmails, $queryValue);
            foreach ($matches as $m) {
                $relationships[] = $this->buildRelationship(
                    'shared_email',
                    "Email \"{$m['matched_value']}\" also found in search for {$m['query_value']}",
                    $m,
                    'high'
                );
            }
        }

        // 2. Shared usernames -- search profiles.known_usernames JSON
        if (!empty($currentUsernames)) {
            $matches = $this->findInProfileJson('known_usernames', $currentUsernames, $queryValue);
            foreach ($matches as $m) {
                $relationships[] = $this->buildRelationship(
                    'shared_username',
                    "Username \"{$m['matched_value']}\" also found in search for {$m['query_value']}",
                    $m,
                    'high'
                );
            }
        }

        // 3. Same IP across different domain/IP searches -- search data_sources raw_data
        if (!empty($currentIps)) {
            $matches = $this->findSharedIps($currentIps, $queryValue);
            foreach ($matches as $m) {
                $relationships[] = $this->buildRelationship(
                    'shared_ip',
                    "IP {$m['matched_value']} also resolves for {$m['query_value']}",
                    $m,
                    'high'
                );
            }
        }

        // Generic/meaningless values that should not trigger relationship matches
        $genericValues = ['unknown', 'none', 'n/a', '', 'not available', 'not disclosed',
                          'redacted for privacy', 'data protected', 'contact privacy'];

        // 4. Same registrar across domain searches
        if (!empty($currentRegistrar) && !in_array(strtolower(trim($currentRegistrar)), $genericValues, true)) {
            $matches = $this->findSharedRawDataField('whois', 'registrar', $currentRegistrar, $queryValue);
            foreach ($matches as $m) {
                $relationships[] = $this->buildRelationship(
                    'shared_registrar',
                    "Same registrar \"{$currentRegistrar}\" as {$m['query_value']}",
                    $m,
                    'low'
                );
            }
        }

        // 5. Same hosting provider (skip generic/unknown values)
        if (!empty($currentHosting)) {
            foreach ($currentHosting as $host) {
                if (in_array(strtolower(trim($host)), $genericValues, true)) continue;
                $matches = $this->findSharedRawDataField('dns', 'hosting', $host, $queryValue);
                foreach ($matches as $m) {
                    $relationships[] = $this->buildRelationship(
                        'shared_hosting',
                        "Same hosting provider \"{$host}\" as {$m['query_value']}",
                        $m,
                        'medium'
                    );
                }
            }
        }

        // 6. Same SSL certificate authority (skip very common CAs that would match too broadly)
        $commonCAs = ["let's encrypt", 'letsencrypt', 'cloudflare', 'google trust services'];
        if (!empty($currentSslIssuer) && !in_array(strtolower(trim($currentSslIssuer)), $genericValues, true)) {
            $confidence = in_array(strtolower(trim($currentSslIssuer)), $commonCAs, true) ? 'low' : 'medium';
            $matches = $this->findSharedRawDataField('ssl', 'issuer', $currentSslIssuer, $queryValue);
            foreach ($matches as $m) {
                $relationships[] = $this->buildRelationship(
                    'shared_ssl_ca',
                    "Same SSL CA \"{$currentSslIssuer}\" as {$m['query_value']}",
                    $m,
                    $confidence
                );
            }
        }

        // 7. Same person name across searches
        if (!empty($currentName) && strlen($currentName) > 2) {
            $matches = $this->findSharedName($currentName, $queryValue);
            foreach ($matches as $m) {
                $relationships[] = $this->buildRelationship(
                    'same_person',
                    "Name \"{$currentName}\" also appears in search for {$m['query_value']}",
                    $m,
                    'medium'
                );
            }
        }

        // De-duplicate: same type + same related search = keep first
        $relationships = $this->dedup($relationships);

        return $relationships;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Search a JSON column in profiles for any of the given values.
     * Returns matching search metadata.
     */
    private function findInProfileJson(string $column, array $values, string $excludeQuery): array {
        if (empty($values)) {
            return [];
        }

        // Build LIKE conditions for each value inside the JSON column
        $conditions = [];
        $params = [':exclude' => $excludeQuery];
        foreach ($values as $i => $val) {
            $val = trim($val);
            if (empty($val)) continue;
            $key = ":val{$i}";
            $conditions[] = "p.{$column} LIKE {$key}";
            $params[$key] = '%' . $this->escapeLike($val) . '%';
        }

        if (empty($conditions)) {
            return [];
        }

        $sql = "SELECT s.id AS search_id, s.query_value, s.query_type, s.created_at,
                       p.{$column} AS json_data
                FROM profiles p
                JOIN searches s ON s.id = p.search_id
                WHERE s.query_value != :exclude
                  AND (" . implode(' OR ', $conditions) . ")
                ORDER BY s.created_at DESC
                LIMIT 20";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $jsonArr = json_decode($row['json_data'], true) ?: [];
            foreach ($values as $val) {
                $val = trim($val);
                if (empty($val)) continue;
                if (in_array($val, $jsonArr, true) || in_array(strtolower($val), array_map('strtolower', $jsonArr), true)) {
                    $results[] = [
                        'search_id'    => (int)$row['search_id'],
                        'query_value'  => $row['query_value'],
                        'query_type'   => $row['query_type'],
                        'matched_value' => $val,
                    ];
                    break; // one match per row is enough
                }
            }
        }

        return $results;
    }

    /**
     * Find searches that share an IP address (from DNS A records or IP searches).
     */
    private function findSharedIps(array $ips, string $excludeQuery): array {
        if (empty($ips)) {
            return [];
        }

        $conditions = [];
        $params = [':exclude' => $excludeQuery];
        foreach ($ips as $i => $ip) {
            $ip = trim($ip);
            if (empty($ip)) continue;
            $key = ":ip{$i}";
            $conditions[] = "ds.raw_data LIKE {$key}";
            $params[$key] = '%' . $this->escapeLike($ip) . '%';
        }

        if (empty($conditions)) {
            return [];
        }

        $sql = "SELECT DISTINCT s.id AS search_id, s.query_value, s.query_type
                FROM data_sources ds
                JOIN searches s ON s.id = ds.search_id
                WHERE s.query_value != :exclude
                  AND ds.source_name IN ('dns', 'ipinfo')
                  AND ds.status = 'success'
                  AND (" . implode(' OR ', $conditions) . ")
                ORDER BY s.created_at DESC
                LIMIT 20";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            // Determine which IP matched
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (empty($ip)) continue;
                $results[] = [
                    'search_id'    => (int)$row['search_id'],
                    'query_value'  => $row['query_value'],
                    'query_type'   => $row['query_type'],
                    'matched_value' => $ip,
                ];
                break;
            }
        }

        return $results;
    }

    /**
     * Find searches sharing a specific value inside a source's raw_data JSON.
     * Uses LIKE against the raw_data column filtered by source_name.
     */
    private function findSharedRawDataField(string $sourceName, string $field, string $value, string $excludeQuery): array {
        $value = trim($value);
        if (empty($value)) {
            return [];
        }

        $sql = "SELECT DISTINCT s.id AS search_id, s.query_value, s.query_type
                FROM data_sources ds
                JOIN searches s ON s.id = ds.search_id
                WHERE s.query_value != :exclude
                  AND ds.source_name = :source
                  AND ds.status = 'success'
                  AND ds.raw_data LIKE :val
                ORDER BY s.created_at DESC
                LIMIT 20";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':exclude' => $excludeQuery,
            ':source'  => $sourceName,
            ':val'     => '%' . $this->escapeLike($value) . '%',
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'search_id'    => (int)$row['search_id'],
                'query_value'  => $row['query_value'],
                'query_type'   => $row['query_type'],
                'matched_value' => $value,
            ];
        }

        return $results;
    }

    /**
     * Find searches where the display_name matches the current name.
     */
    private function findSharedName(string $name, string $excludeQuery): array {
        $name = trim($name);
        if (empty($name)) {
            return [];
        }

        $sql = "SELECT s.id AS search_id, s.query_value, s.query_type, p.display_name
                FROM profiles p
                JOIN searches s ON s.id = p.search_id
                WHERE s.query_value != :exclude
                  AND p.display_name != ''
                  AND LOWER(p.display_name) = LOWER(:name)
                ORDER BY s.created_at DESC
                LIMIT 20";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':exclude' => $excludeQuery,
            ':name'    => $name,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'search_id'    => (int)$row['search_id'],
                'query_value'  => $row['query_value'],
                'query_type'   => $row['query_type'],
                'matched_value' => $row['display_name'],
            ];
        }

        return $results;
    }

    /**
     * Extract IP addresses from the profile (DNS A records, IP data).
     */
    private function extractIps(array $profile): array {
        $ips = [];

        // From DNS A records
        if (!empty($profile['dns_data']['a_records'])) {
            foreach ($profile['dns_data']['a_records'] as $ip) {
                $ips[] = $ip;
            }
        }

        // From IP data
        if (!empty($profile['ip_data']['ip'])) {
            $ips[] = $profile['ip_data']['ip'];
        }

        return array_unique($ips);
    }

    /**
     * Build a standardised relationship entry.
     */
    private function buildRelationship(string $type, string $description, array $match, string $confidence): array {
        return [
            'type'               => $type,
            'description'        => $description,
            'related_search_id'  => $match['search_id'],
            'related_query'      => $match['query_value'],
            'related_query_type' => $match['query_type'],
            'confidence'         => $confidence,
        ];
    }

    /**
     * Remove duplicate relationships (same type + same related search).
     */
    private function dedup(array $relationships): array {
        $seen = [];
        $out = [];
        foreach ($relationships as $r) {
            $key = $r['type'] . '|' . $r['related_search_id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $r;
        }
        return $out;
    }

    /**
     * Escape special characters for LIKE queries.
     */
    private function escapeLike(string $str): string {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $str);
    }
}
