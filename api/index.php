<?php

require_once 'config.php';
require_once 'Database.php';
require_once 'SearchController.php';
require_once 'ChatController.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

if ($method === 'POST' && strpos($path, '/api/search') !== false) {
    $controller = new SearchController();
    $controller->createSearch();
}
elseif ($method === 'POST' && strpos($path, '/api/chat') !== false) {
    $controller = new ChatController();
    $controller->chat();
}
else {
    echo json_encode(["message" => "Vignette API Running"]);
}