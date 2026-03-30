<?php

/**
 * Vignette — Search Controller
 * Handles search requests by orchestrating data source lookups,
 * aggregating results, and building a unified profile.
 */

require_once __DIR__ . '/../core/orchestrator.php';
require_once __DIR__ . '/../core/aggregator.php';
require_once __DIR__ . '/../core/profiler.php';
require_once __DIR__ . '/../modules/gemini.php';
require_once __DIR__ . '/../core/relationships.php';

class SearchController {

    private array $apiKeys;

    public function __construct() {
        $keysFile = __DIR__ . '/../config/api_keys.php';
        $this->apiKeys = file_exists($keysFile) ? require $keysFile : [];
    }

    /**
     * POST /api/?route=search
     * Body: { "query_value": "...", "query_type": "email|username|name|ip|domain|phone" }
     */
    public function search(): void {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || empty($input['query_value']) || empty($input['query_type'])) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid input',
                    'message' => 'query_value and query_type are required'
                ]);
                return;
            }

            $queryValue = trim($input['query_value']);
            $queryType = trim($input['query_type']);

            if (strlen($queryValue) > 255) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid input', 'message' => 'Query is too long (max 255 characters)']);
                return;
            }

            $validTypes = ['name', 'email', 'phone', 'username', 'ip', 'domain'];
            if (!in_array($queryType, $validTypes, true)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid query_type',
                    'message' => 'Must be one of: ' . implode(', ', $validTypes)
                ]);
                return;
            }

            // Run the full search pipeline
            $result = $this->executeSearch($queryValue, $queryType);

            $response = [
                'success' => true,
                'search_id' => $result['search_id'],
                'profile' => $result['profile'],
                'relationships' => $result['relationships'],
                'source_errors' => $result['source_errors'],
            ];

            if (!empty($result['timings']['per_source'])) {
                $response['timings'] = $result['timings'];
            }

            echo json_encode($response);

        } catch (\Throwable $e) {
            http_response_code(500);
            error_log('Vignette search error: ' . $e->getMessage());
            echo json_encode([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred. Please try again.'
            ]);
        }
    }

    /**
     * POST /api/?route=bulk-search
     * Body: { "queries": [{"value": "...", "type": "email"}, ...] }
     * Max 10 queries per batch.
     */
    public function bulkSearch(): void {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || empty($input['queries']) || !is_array($input['queries'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid input', 'message' => 'queries array is required']);
                return;
            }

            $queries = $input['queries'];
            $maxBatch = 10;

            if (count($queries) > $maxBatch) {
                http_response_code(400);
                echo json_encode(['error' => 'Too many queries', 'message' => "Maximum $maxBatch queries per batch"]);
                return;
            }

            if (count($queries) < 1) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid input', 'message' => 'At least 1 query is required']);
                return;
            }

            $validTypes = ['name', 'email', 'phone', 'username', 'ip', 'domain'];
            $bulkId = bin2hex(random_bytes(12));
            $results = [];
            $totalRisk = 0;
            $successCount = 0;

            foreach ($queries as $i => $q) {
                $value = trim($q['value'] ?? '');
                $type = trim($q['type'] ?? '');

                if (empty($value) || !in_array($type, $validTypes, true)) {
                    $results[] = [
                        'index' => $i,
                        'query_value' => $value,
                        'query_type' => $type,
                        'success' => false,
                        'error' => 'Invalid query or type',
                    ];
                    continue;
                }

                if (strlen($value) > 255) {
                    $results[] = [
                        'index' => $i,
                        'query_value' => $value,
                        'query_type' => $type,
                        'success' => false,
                        'error' => 'Query too long (max 255)',
                    ];
                    continue;
                }

                try {
                    $searchResult = $this->executeSearch($value, $type, $bulkId);
                    $profile = $searchResult['profile'];
                    $successCount++;
                    $totalRisk += $profile['risk']['score'] ?? 0;

                    $results[] = [
                        'index' => $i,
                        'query_value' => $value,
                        'query_type' => $type,
                        'success' => true,
                        'search_id' => $searchResult['search_id'],
                        'profile' => $profile,
                        'relationships' => $searchResult['relationships'],
                        'source_errors' => $searchResult['source_errors'],
                    ];
                } catch (\Throwable $e) {
                    error_log("Bulk search error for '$value': " . $e->getMessage());
                    $results[] = [
                        'index' => $i,
                        'query_value' => $value,
                        'query_type' => $type,
                        'success' => false,
                        'error' => 'Search failed',
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'bulk_id' => $bulkId,
                'total' => count($queries),
                'completed' => $successCount,
                'failed' => count($queries) - $successCount,
                'avg_risk' => $successCount > 0 ? round($totalRisk / $successCount) : 0,
                'results' => $results,
            ]);

        } catch (\Throwable $e) {
            http_response_code(500);
            error_log('Vignette bulk search error: ' . $e->getMessage());
            echo json_encode(['error' => 'Internal server error', 'message' => 'Bulk search failed']);
        }
    }

    /**
     * GET /api/?route=history
     * Supports: ?type=email &risk=high &q=searchtext &sort=date_desc &page=1 &per_page=20
     */
    public function getHistory(): void {
        header('Content-Type: application/json');

        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $where = [];
            $params = [];

            // Filter by query type
            $validTypes = ['name', 'email', 'phone', 'username', 'ip', 'domain'];
            if (!empty($_GET['type']) && in_array($_GET['type'], $validTypes, true)) {
                $where[] = "s.query_type = :type";
                $params[':type'] = $_GET['type'];
            }

            // Filter by risk level
            $riskRanges = [
                'low' => [0, 20],
                'moderate' => [21, 50],
                'high' => [51, 75],
                'critical' => [76, 100],
            ];
            if (!empty($_GET['risk']) && isset($riskRanges[$_GET['risk']])) {
                $range = $riskRanges[$_GET['risk']];
                $where[] = "COALESCE(p.risk_score, 0) BETWEEN :risk_min AND :risk_max";
                $params[':risk_min'] = $range[0];
                $params[':risk_max'] = $range[1];
            }

            // Text search on query value (escape LIKE wildcards)
            if (!empty($_GET['q'])) {
                $where[] = "s.query_value LIKE :q";
                $escaped = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $_GET['q']);
                $params[':q'] = '%' . $escaped . '%';
            }

            $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            // Sorting
            $sortMap = [
                'date_desc' => 's.created_at DESC',
                'date_asc' => 's.created_at ASC',
                'risk_desc' => 'COALESCE(p.risk_score, 0) DESC',
                'risk_asc' => 'COALESCE(p.risk_score, 0) ASC',
            ];
            $sort = $sortMap[$_GET['sort'] ?? ''] ?? 's.created_at DESC';

            // Pagination
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $perPage;

            // Count total
            $countSQL = "SELECT COUNT(*) FROM searches s LEFT JOIN profiles p ON p.search_id = s.id $whereSQL";
            $countStmt = $pdo->prepare($countSQL);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Fetch page
            $sql = "SELECT s.id, s.query_value, s.query_type, s.created_at,
                           p.display_name, p.avatar_url, p.risk_score
                    FROM searches s
                    LEFT JOIN profiles p ON p.search_id = s.id
                    $whereSQL
                    ORDER BY $sort
                    LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'searches' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => max(1, (int)ceil($total / $perPage)),
                ],
            ]);

        } catch (\Throwable $e) {
            http_response_code(500);
            error_log('Vignette history error: ' . $e->getMessage());
            echo json_encode(['error' => 'Failed to load history']);
        }
    }

    /**
     * POST /api/?route=save-profile
     * Body: { search_id, label, notes, tags }
     */
    public function saveProfile(): void {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $searchId = (int)($input['search_id'] ?? 0);
            $label = trim($input['label'] ?? '');
            $notes = trim($input['notes'] ?? '');
            $tags = $this->normalizeTags($input['tags'] ?? '');

            if (!$searchId) {
                http_response_code(400);
                echo json_encode(['error' => 'search_id is required']);
                return;
            }

            $db = new Database();
            $pdo = $db->getConnection();

            // Verify search exists
            $check = $pdo->prepare("SELECT id FROM searches WHERE id = :id");
            $check->execute([':id' => $searchId]);
            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Search not found']);
                return;
            }

            // Check for duplicate
            $dup = $pdo->prepare("SELECT id FROM saved_profiles WHERE search_id = :sid");
            $dup->execute([':sid' => $searchId]);
            if ($dup->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'This search is already saved']);
                return;
            }

            $stmt = $pdo->prepare("INSERT INTO saved_profiles (search_id, label, notes, tags) VALUES (:sid, :label, :notes, :tags)");
            $stmt->execute([':sid' => $searchId, ':label' => $label, ':notes' => $notes, ':tags' => $tags]);

            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (\Throwable $e) {
            http_response_code(500);
            error_log('Vignette save-profile error: ' . $e->getMessage());
            echo json_encode(['error' => 'Failed to save profile']);
        }
    }

    /**
     * GET /api/?route=saved-profiles
     * Optional: ?tag=tagname
     */
    public function getSavedProfiles(): void {
        header('Content-Type: application/json');
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $where = '';
            $params = [];
            if (!empty($_GET['tag'])) {
                $where = "AND FIND_IN_SET(:tag, sp.tags) > 0";
                $params[':tag'] = trim($_GET['tag']);
            }

            $stmt = $pdo->prepare("
                SELECT sp.id, sp.search_id, sp.label, sp.notes, sp.tags, sp.created_at,
                       s.query_value, s.query_type,
                       p.display_name, p.avatar_url, p.risk_score
                FROM saved_profiles sp
                JOIN searches s ON s.id = sp.search_id
                LEFT JOIN profiles p ON p.search_id = sp.search_id
                WHERE 1=1 $where
                ORDER BY sp.created_at DESC
            ");
            $stmt->execute($params);
            $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get all distinct tags
            $tagStmt = $pdo->query("SELECT tags FROM saved_profiles WHERE tags IS NOT NULL AND tags != ''");
            $allTags = [];
            while ($row = $tagStmt->fetch(PDO::FETCH_ASSOC)) {
                foreach (explode(',', $row['tags']) as $t) {
                    $t = trim($t);
                    if ($t !== '') $allTags[$t] = true;
                }
            }

            echo json_encode([
                'success' => true,
                'profiles' => $profiles,
                'all_tags' => array_keys($allTags),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            error_log('Vignette saved-profiles error: ' . $e->getMessage());
            echo json_encode(['error' => 'Failed to load saved profiles']);
        }
    }

    /**
     * POST /api/?route=update-saved-profile
     * Body: { id, label, notes, tags }
     */
    public function updateSavedProfile(): void {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id is required']);
                return;
            }

            $db = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("UPDATE saved_profiles SET label = :label, notes = :notes, tags = :tags WHERE id = :id");
            $stmt->execute([
                ':id' => $id,
                ':label' => trim($input['label'] ?? ''),
                ':notes' => trim($input['notes'] ?? ''),
                ':tags' => $this->normalizeTags($input['tags'] ?? ''),
            ]);

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update saved profile']);
        }
    }

    /**
     * POST /api/?route=delete-saved-profile
     * Body: { id }
     */
    public function deleteSavedProfile(): void {
        header('Content-Type: application/json');
        try {
            $id = (int)(json_decode(file_get_contents('php://input'), true)['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id is required']);
                return;
            }

            $db = new Database();
            $pdo = $db->getConnection();
            $pdo->prepare("DELETE FROM saved_profiles WHERE id = :id")->execute([':id' => $id]);

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete saved profile']);
        }
    }

    /**
     * GET /api/?route=watchlist
     */
    public function getWatchlist(): void {
        header('Content-Type: application/json');
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->query("
                SELECT w.*,
                       (SELECT p.risk_score FROM searches s
                        JOIN profiles p ON p.search_id = s.id
                        WHERE s.query_value = w.query_value AND s.query_type = w.query_type
                        ORDER BY s.created_at DESC LIMIT 1) as last_risk_score
                FROM watchlist w
                ORDER BY w.created_at DESC
            ");

            echo json_encode(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load watchlist']);
        }
    }

    /**
     * POST /api/?route=watchlist-add
     * Body: { query_value, query_type }
     */
    public function addToWatchlist(): void {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $qv = trim($input['query_value'] ?? '');
            $qt = trim($input['query_type'] ?? '');

            $validTypes = ['name', 'email', 'phone', 'username', 'ip', 'domain'];
            if (!$qv || !in_array($qt, $validTypes, true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Valid query_value and query_type required']);
                return;
            }

            $db = new Database();
            $pdo = $db->getConnection();

            // Check duplicate
            $dup = $pdo->prepare("SELECT id FROM watchlist WHERE query_value = :qv AND query_type = :qt");
            $dup->execute([':qv' => $qv, ':qt' => $qt]);
            if ($dup->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Already on watchlist']);
                return;
            }

            $stmt = $pdo->prepare("INSERT INTO watchlist (query_value, query_type) VALUES (:qv, :qt)");
            $stmt->execute([':qv' => $qv, ':qt' => $qt]);

            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add to watchlist']);
        }
    }

    /**
     * POST /api/?route=watchlist-toggle
     * Body: { id }
     */
    public function toggleWatchlist(): void {
        header('Content-Type: application/json');
        try {
            $id = (int)(json_decode(file_get_contents('php://input'), true)['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id is required']);
                return;
            }

            $db = new Database();
            $pdo = $db->getConnection();
            $pdo->prepare("UPDATE watchlist SET active = NOT active WHERE id = :id")->execute([':id' => $id]);

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to toggle watchlist item']);
        }
    }

    /**
     * POST /api/?route=watchlist-delete
     * Body: { id }
     */
    public function deleteWatchlistItem(): void {
        header('Content-Type: application/json');
        try {
            $id = (int)(json_decode(file_get_contents('php://input'), true)['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id is required']);
                return;
            }

            $db = new Database();
            $pdo = $db->getConnection();
            $pdo->prepare("DELETE FROM watchlist WHERE id = :id")->execute([':id' => $id]);

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete watchlist item']);
        }
    }

    /**
     * POST /api/?route=watchlist-recheck
     * Body: { id }
     * Re-runs the search and compares with previous results.
     */
    public function recheckWatchlist(): void {
        header('Content-Type: application/json');
        try {
            $id = (int)(json_decode(file_get_contents('php://input'), true)['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id is required']);
                return;
            }

            $db = new Database();
            $pdo = $db->getConnection();

            // Get watchlist item
            $item = $pdo->prepare("SELECT * FROM watchlist WHERE id = :id");
            $item->execute([':id' => $id]);
            $watch = $item->fetch(PDO::FETCH_ASSOC);
            if (!$watch) {
                http_response_code(404);
                echo json_encode(['error' => 'Watchlist item not found']);
                return;
            }

            // Get previous risk score from most recent search
            $prevStmt = $pdo->prepare("
                SELECT p.risk_score, p.ai_summary
                FROM searches s JOIN profiles p ON p.search_id = s.id
                WHERE s.query_value = :qv AND s.query_type = :qt
                ORDER BY s.created_at DESC LIMIT 1
            ");
            $prevStmt->execute([':qv' => $watch['query_value'], ':qt' => $watch['query_type']]);
            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
            $oldRisk = $prev ? (int)$prev['risk_score'] : null;

            // Run a fresh search through the full pipeline
            $result = $this->executeSearch($watch['query_value'], $watch['query_type']);

            $newRisk = $result['profile']['risk']['score'] ?? 0;

            // Update watchlist last_checked
            $pdo->prepare("UPDATE watchlist SET last_checked = NOW() WHERE id = :id")->execute([':id' => $id]);

            echo json_encode([
                'success' => true,
                'search_id' => $result['search_id'],
                'new_risk' => $newRisk,
                'old_risk' => $oldRisk,
                'risk_change' => $oldRisk !== null ? $newRisk - $oldRisk : null,
                'summary_changed' => $prev ? ($result['profile']['ai_summary'] ?? '') !== ($prev['ai_summary'] ?? '') : null,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            error_log('Vignette watchlist-recheck error: ' . $e->getMessage());
            echo json_encode(['error' => 'Re-check failed: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/?route=analytics
     * Returns aggregated statistics for the dashboard.
     */
    public function getAnalytics(): void {
        header('Content-Type: application/json');

        try {
            $db = new Database();
            $pdo = $db->getConnection();

            // Total searches
            $totalSearches = (int)$pdo->query("SELECT COUNT(*) FROM searches")->fetchColumn();

            // Searches by type
            $typeRows = $pdo->query("SELECT query_type, COUNT(*) as count FROM searches GROUP BY query_type ORDER BY count DESC")->fetchAll();
            $byType = [];
            foreach ($typeRows as $r) $byType[$r['query_type']] = (int)$r['count'];

            // Searches over last 30 days (by day)
            $dailyRows = $pdo->query(
                "SELECT DATE(created_at) as day, COUNT(*) as count FROM searches
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY DATE(created_at) ORDER BY day"
            )->fetchAll();
            $daily = [];
            foreach ($dailyRows as $r) $daily[] = ['day' => $r['day'], 'count' => (int)$r['count']];

            // Risk distribution
            $riskRows = $pdo->query(
                "SELECT
                    CASE
                        WHEN risk_score = 0 THEN 'clean'
                        WHEN risk_score BETWEEN 1 AND 20 THEN 'low'
                        WHEN risk_score BETWEEN 21 AND 50 THEN 'moderate'
                        WHEN risk_score BETWEEN 51 AND 75 THEN 'high'
                        ELSE 'critical'
                    END as level, COUNT(*) as count
                 FROM profiles GROUP BY level ORDER BY FIELD(level, 'clean', 'low', 'moderate', 'high', 'critical')"
            )->fetchAll();
            $riskDist = [];
            foreach ($riskRows as $r) $riskDist[$r['level']] = (int)$r['count'];

            // Average risk
            $avgRisk = (int)$pdo->query("SELECT COALESCE(AVG(risk_score), 0) FROM profiles")->fetchColumn();

            // Total saved & watchlist
            $totalSaved = (int)$pdo->query("SELECT COUNT(*) FROM saved_profiles")->fetchColumn();
            $totalWatchlist = (int)$pdo->query("SELECT COUNT(*) FROM watchlist WHERE active = 1")->fetchColumn();

            // Top platforms from username_profiles in data_sources
            $platformRows = $pdo->query(
                "SELECT raw_data FROM data_sources WHERE source_name = 'username_osint' AND status = 'success' ORDER BY id DESC LIMIT 100"
            )->fetchAll();

            $platformCounts = [];
            foreach ($platformRows as $row) {
                $data = json_decode($row['raw_data'], true);
                if (!empty($data['profiles'])) {
                    foreach ($data['profiles'] as $p) {
                        if (!empty($p['exists']) && !empty($p['platform'])) {
                            $name = $p['platform'];
                            $platformCounts[$name] = ($platformCounts[$name] ?? 0) + 1;
                        }
                    }
                }
            }
            arsort($platformCounts);
            $topPlatforms = array_slice($platformCounts, 0, 10, true);

            echo json_encode([
                'success' => true,
                'total_searches' => $totalSearches,
                'avg_risk' => $avgRisk,
                'total_saved' => $totalSaved,
                'total_watchlist' => $totalWatchlist,
                'by_type' => $byType,
                'daily' => $daily,
                'risk_distribution' => $riskDist,
                'top_platforms' => $topPlatforms,
            ]);

        } catch (\Throwable $e) {
            http_response_code(500);
            error_log('Vignette analytics error: ' . $e->getMessage());
            echo json_encode(['error' => 'Failed to load analytics']);
        }
    }

    /**
     * Core search pipeline — reused by search(), bulkSearch(), and recheckWatchlist().
     */
    private function executeSearch(string $queryValue, string $queryType, ?string $bulkId = null): array {
        $db = new Database();
        $pdo = $db->getConnection();

        // Save search record
        $stmt = $pdo->prepare("INSERT INTO searches (query_value, query_type, bulk_id) VALUES (:qv, :qt, :bid)");
        $stmt->execute([':qv' => $queryValue, ':qt' => $queryType, ':bid' => $bulkId]);
        $searchId = (int)$pdo->lastInsertId();

        // Run orchestrator
        $orchestrator = new Orchestrator($this->apiKeys);
        $sourceResults = $orchestrator->search($queryValue, $queryType);

        $timings = $sourceResults['_timings'] ?? [];
        $totalTime = $sourceResults['_total_time'] ?? null;
        unset($sourceResults['_timings'], $sourceResults['_total_time']);

        // Store raw results
        $insertSource = $pdo->prepare(
            "INSERT INTO data_sources (search_id, source_name, raw_data, status) VALUES (:sid, :src, :data, :status)"
        );
        foreach ($sourceResults as $sourceName => $result) {
            $insertSource->execute([
                ':sid' => $searchId,
                ':src' => $sourceName,
                ':data' => json_encode($result['data'] ?? []),
                ':status' => $result['status'] ?? 'error',
            ]);
        }

        // Aggregate + profile
        $aggregator = new Aggregator();
        $merged = $aggregator->merge($sourceResults);
        $profiler = new Profiler();
        $profile = $profiler->build($merged, $queryValue, $queryType);

        // AI summary
        $geminiKey = $this->apiKeys['gemini']['api_key'] ?? '';
        if (!empty($geminiKey)) {
            $gemini = new GeminiModule($geminiKey);
            $aiSummary = $gemini->generateSummary($profile, $queryType);
            if (!empty($aiSummary)) {
                $profile['ai_summary'] = $aiSummary;
            }
        }

        // Store profile
        $stmt = $pdo->prepare("INSERT INTO profiles (search_id, display_name, avatar_url, location, bio, known_emails, known_usernames, social_links, ai_summary, risk_score) VALUES (:sid, :name, :avatar, :loc, :bio, :emails, :usernames, :links, :summary, :risk)");
        $stmt->execute([
            ':sid' => $searchId,
            ':name' => $profile['identity']['display_name'] ?? '',
            ':avatar' => $profile['identity']['avatar_url'] ?? '',
            ':loc' => $profile['identity']['location'] ?? '',
            ':bio' => $profile['identity']['bio'] ?? '',
            ':emails' => json_encode($profile['identity']['emails'] ?? []),
            ':usernames' => json_encode($profile['identity']['usernames'] ?? []),
            ':links' => json_encode($profile['social_links'] ?? []),
            ':summary' => $profile['ai_summary'] ?? '',
            ':risk' => $profile['risk']['score'] ?? 0,
        ]);

        // Relationships
        $mapper = new RelationshipMapper($pdo);
        $relationships = $mapper->findRelationships($profile, $queryValue, $queryType);

        // Source errors
        $sourceErrors = [];
        foreach ($sourceResults as $name => $res) {
            if (($res['status'] ?? '') !== 'success') {
                $err = $res['error'] ?? 'Unknown error';
                $skipPatterns = ['not configured', 'token not configured', 'does not have the access', 'quota', 'rate limit', 'not found', '404', 'no results', 'hasn\'t returned any results', 'returned no results'];
                $skip = false;
                foreach ($skipPatterns as $pattern) {
                    if (stripos($err, $pattern) !== false) { $skip = true; break; }
                }
                if ($skip) continue;
                $sourceErrors[$name] = $err;
            }
        }

        return [
            'search_id' => $searchId,
            'profile' => $profile,
            'relationships' => $relationships,
            'source_errors' => $sourceErrors,
            'timings' => ['per_source' => $timings, 'total_ms' => $totalTime],
        ];
    }

    /**
     * GET /api/?route=replay&id=123
     * Reconstructs a previous search result from stored data.
     */
    public function replay(): void {
        header('Content-Type: application/json');
        try {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'id is required']);
                return;
            }

            $db = new Database();
            $pdo = $db->getConnection();

            // Get search record
            $search = $pdo->prepare("SELECT * FROM searches WHERE id = :id");
            $search->execute([':id' => $id]);
            $searchRow = $search->fetch(PDO::FETCH_ASSOC);
            if (!$searchRow) {
                http_response_code(404);
                echo json_encode(['error' => 'Search not found']);
                return;
            }

            // Get all source data
            $sources = $pdo->prepare("SELECT source_name, raw_data, status FROM data_sources WHERE search_id = :id");
            $sources->execute([':id' => $id]);
            $sourceResults = [];
            while ($row = $sources->fetch(PDO::FETCH_ASSOC)) {
                $sourceResults[$row['source_name']] = [
                    'status' => $row['status'],
                    'data' => json_decode($row['raw_data'], true) ?: [],
                ];
            }

            // Re-aggregate and build profile from stored data
            $aggregator = new Aggregator();
            $merged = $aggregator->merge($sourceResults);
            $profiler = new Profiler();
            $profile = $profiler->build($merged, $searchRow['query_value'], $searchRow['query_type']);

            // Get stored AI summary (don't re-generate)
            $profileRow = $pdo->prepare("SELECT ai_summary, risk_score FROM profiles WHERE search_id = :id");
            $profileRow->execute([':id' => $id]);
            $stored = $profileRow->fetch(PDO::FETCH_ASSOC);
            if ($stored && !empty($stored['ai_summary'])) {
                $profile['ai_summary'] = $stored['ai_summary'];
            }

            // Get relationships
            $mapper = new RelationshipMapper($pdo);
            $relationships = $mapper->findRelationships($profile, $searchRow['query_value'], $searchRow['query_type']);

            echo json_encode([
                'success' => true,
                'search_id' => $id,
                'query_value' => $searchRow['query_value'],
                'query_type' => $searchRow['query_type'],
                'profile' => $profile,
                'relationships' => $relationships,
                'source_errors' => [],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            error_log('Vignette replay error: ' . $e->getMessage());
            echo json_encode(['error' => 'Failed to replay search']);
        }
    }

    private function normalizeTags(string $tags): string {
        $parts = array_filter(array_map(function($t) {
            return strtolower(trim($t));
        }, explode(',', $tags)));
        return substr(implode(',', array_unique($parts)), 0, 500);
    }
}
