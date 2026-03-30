<?php

require_once "../config/db.php";
require_once "../services/hibp.php";
require_once "../services/risk_engine.php";

$email = $_POST['email'] ?? '';

if (!$email) {
    echo json_encode(["error" => "Email required"]);
    exit;
}

$breaches = checkHIBP($email);

$breach_count = count($breaches);

$risk_score = calculateRiskScore($breaches);

$severity = "Low";

if ($risk_score > 70) {
    $severity = "High";
} elseif ($risk_score > 40) {
    $severity = "Medium";
}

$response = [
    "email" => $email,
    "breaches" => $breaches,
    "breach_count" => $breach_count,
    "risk_score" => $risk_score,
    "severity" => $severity
];

echo json_encode($response);