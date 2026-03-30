<?php

/**
 * Vignette — API Router
 * Routes incoming requests to the appropriate controller.
 */

// CORS headers for local dev
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Load config
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Database.php';

// Determine route
$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($route) {
    case 'search':
        if ($method === 'POST') {
            require_once __DIR__ . '/SearchController.php';
            $controller = new SearchController();
            $controller->search();
        }
        break;

    case 'bulk-search':
        if ($method === 'POST') {
            require_once __DIR__ . '/SearchController.php';
            $controller = new SearchController();
            $controller->bulkSearch();
        }
        break;

    case 'analytics':
        if ($method === 'GET') {
            require_once __DIR__ . '/SearchController.php';
            $controller = new SearchController();
            $controller->getAnalytics();
        }
        break;

    case 'chat':
        if ($method === 'POST') {
            require_once __DIR__ . '/ChatController.php';
            $controller = new ChatController();
            $controller->chat();
        }
        break;

    case 'history':
        if ($method === 'GET') {
            require_once __DIR__ . '/SearchController.php';
            $controller = new SearchController();
            $controller->getHistory();
        }
        break;

    case 'replay':
        if ($method === 'GET') {
            require_once __DIR__ . '/SearchController.php';
            $controller = new SearchController();
            $controller->replay();
        }
        break;

    case 'save-profile':
        require_once __DIR__ . '/SearchController.php';
        $controller = new SearchController();
        if ($method === 'POST') $controller->saveProfile();
        break;

    case 'saved-profiles':
        if ($method === 'GET') {
            require_once __DIR__ . '/SearchController.php';
            $controller = new SearchController();
            $controller->getSavedProfiles();
        }
        break;

    case 'update-saved-profile':
        if ($method === 'POST') {
            require_once __DIR__ . '/SearchController.php';
            $controller = new SearchController();
            $controller->updateSavedProfile();
        }
        break;

    case 'delete-saved-profile':
        if ($method === 'POST') {
            require_once __DIR__ . '/SearchController.php';
            $controller = new SearchController();
            $controller->deleteSavedProfile();
        }
        break;

    case 'watchlist':
        require_once __DIR__ . '/SearchController.php';
        $controller = new SearchController();
        if ($method === 'GET') $controller->getWatchlist();
        break;

    case 'watchlist-add':
        if ($method === 'POST') {
            require_once __DIR__ . '/SearchController.php';
            $controller = new SearchController();
            $controller->addToWatchlist();
        }
        break;

    case 'watchlist-toggle':
        if ($method === 'POST') {
            require_once __DIR__ . '/SearchController.php';
            $controller = new SearchController();
            $controller->toggleWatchlist();
        }
        break;

    case 'watchlist-delete':
        if ($method === 'POST') {
            require_once __DIR__ . '/SearchController.php';
            $controller = new SearchController();
            $controller->deleteWatchlistItem();
        }
        break;

    case 'watchlist-recheck':
        if ($method === 'POST') {
            require_once __DIR__ . '/SearchController.php';
            $controller = new SearchController();
            $controller->recheckWatchlist();
        }
        break;

    default:
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'platform' => 'Vignette',
            'version' => '2.0.0-phase4',
            'endpoints' => [
                'POST /api/?route=search' => 'Run an intelligence search',
                'POST /api/?route=bulk-search' => 'Run bulk search (max 10 queries)',
                'GET /api/?route=analytics' => 'Get dashboard analytics',
                'POST /api/?route=chat' => 'Chat with AI about results',
                'GET /api/?route=history' => 'Get search history (filterable)',
                'POST /api/?route=save-profile' => 'Save a search as a profile',
                'GET /api/?route=saved-profiles' => 'List saved profiles',
                'POST /api/?route=update-saved-profile' => 'Update saved profile',
                'POST /api/?route=delete-saved-profile' => 'Delete saved profile',
                'GET /api/?route=watchlist' => 'Get watchlist',
                'POST /api/?route=watchlist-add' => 'Add to watchlist',
                'POST /api/?route=watchlist-toggle' => 'Toggle watchlist active/inactive',
                'POST /api/?route=watchlist-delete' => 'Remove from watchlist',
                'POST /api/?route=watchlist-recheck' => 'Re-check a watchlist item',
            ]
        ]);
}
