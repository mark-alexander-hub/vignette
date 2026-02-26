<?php
require_once 'Services/HaveIBeenPwnedService.php';
require_once 'Services/RiskScoringService.php';

class SearchController {

    public function createSearch() {

        $input = json_decode(file_get_contents("php://input"), true);

        if (!isset($input['query_value']) || !isset($input['query_type'])) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid input"]);
            return;
        }

        $db = new Database();
        $pdo = $db->getConnection();

        // Insert search
        $stmt = $pdo->prepare("
            INSERT INTO searches (query_value, query_type)
            VALUES (:query_value, :query_type)
        ");

        $stmt->execute([
            ':query_value' => $input['query_value'],
            ':query_type' => $input['query_type']
        ]);

        $searchId = $pdo->lastInsertId();
        $breachCount = 0;
        $riskScore = 0;
        $breaches = [];
        $summary = '';

        // If email → check breaches
        if ($input['query_type'] === 'email') {

            // Fetch breaches
            $hibp = new HaveIBeenPwnedService();
            $breaches = $hibp->checkBreaches($input['query_value']);

            $breachCount = is_array($breaches) ? count($breaches) : 0;

            // Store raw data
            $stmt = $pdo->prepare("
                INSERT INTO data_sources (search_id, source_name, raw_data)
                VALUES (:search_id, :source_name, :raw_data)
            ");

            $stmt->execute([
                ':search_id' => $searchId,
                ':source_name' => 'haveibeenpwned',
                ':raw_data' => json_encode($breaches)
            ]);

            // Calculate risk score
            $riskService = new RiskScoringService();
            $riskScore = $riskService->calculate($breaches);

            // Call Python AI worker
            $workerPayload = [
                "breach_count" => $breachCount,
                "risk_score" => $riskScore,
                "raw_data" => $breaches
            ];

            $ch = curl_init("http://127.0.0.1:8001/generate-summary");

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($workerPayload),
                CURLOPT_TIMEOUT => 10
            ]);

            $workerResponse = curl_exec($ch);
            curl_close($ch);

            $workerData = json_decode($workerResponse, true);
            $summary = $workerData['summary'] ?? '';

            // Store intelligence report
            $stmt = $pdo->prepare("
                INSERT INTO intelligence_reports (search_id, risk_score, summary, model_used)
                VALUES (:search_id, :risk_score, :summary, :model_used)
            ");

            $stmt->execute([
                ':search_id' => $searchId,
                ':risk_score' => $riskScore,
                ':summary' => $summary,
                ':model_used' => 'worker-v1'
            ]);
        }

        // Final response
        echo json_encode([
            "message" => "Search created",
            "search_id" => $searchId,
            "breach_count" => $breachCount,
            "risk_score" => $riskScore,
            "summary" => $summary
        ]);
    }
}