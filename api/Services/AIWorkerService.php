<?php

class AIWorkerService
{
    private $baseUrl = "http://localhost:8001";

    public function generateSummary($breachCount, $riskScore, $rawData)
    {
        $payload = [
            "breach_count" => $breachCount,
            "risk_score" => $riskScore,
            "raw_data" => $rawData
        ];

        return $this->post("/generate-summary", $payload);
    }

    public function chat($searchId, $userMessage, $contextSummary)
    {
        $payload = [
            "search_id" => $searchId,
            "user_message" => $userMessage,
            "context_summary" => $contextSummary
        ];

        return $this->post("/chat", $payload);
    }

    private function post($endpoint, $payload)
    {
        $ch = curl_init($this->baseUrl . $endpoint);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);

        curl_close($ch);

        return json_decode($response, true);
    }
}