<?php

class ChatController {

    public function chat() {

        $input = json_decode(file_get_contents("php://input"), true);

        if (!isset($input['search_id']) || !isset($input['message'])) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid input"]);
            return;
        }

        $db = new Database();
        $pdo = $db->getConnection();

        // Fetch summary context
        $stmt = $pdo->prepare("
            SELECT summary FROM intelligence_reports
            WHERE search_id = :search_id
            ORDER BY id DESC LIMIT 1
        ");

        $stmt->execute([':search_id' => $input['search_id']]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        $contextSummary = $report['summary'] ?? '';

        // Call Python worker chat endpoint
        $payload = [
            "search_id" => $input['search_id'],
            "user_message" => $input['message'],
            "context_summary" => $contextSummary
        ];

        $ch = curl_init("http://127.0.0.1:8001/chat");

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $aiReply = $data['response'] ?? '';

        // Store conversation
        $stmt = $pdo->prepare("
            INSERT INTO conversations (search_id, user_message, ai_response, model_used)
            VALUES (:search_id, :user_message, :ai_response, :model_used)
        ");

        $stmt->execute([
            ':search_id' => $input['search_id'],
            ':user_message' => $input['message'],
            ':ai_response' => $aiReply,
            ':model_used' => 'worker-v1'
        ]);

        echo json_encode([
            "response" => $aiReply
        ]);
    }
}