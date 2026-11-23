<?php
/**
 * Public API Entry Point
 * 
 * This handles all API requests. For web requests, use the web entry point.
 */

require_once __DIR__ . '/../bootstrap/app.php';

// Handle CORS
header('Access-Control-Allow-Origin: ' . Config::get('app.url', '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set JSON content type for API
header('Content-Type: application/json; charset=utf-8');

// Load API routes
require_once __DIR__ . '/../routes/api.php';

// Dispatch
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

try {
    $response = Router::dispatch($method, $uri);
    
    if ($response !== null) {
        if (is_array($response) || is_object($response)) {
            echo json_encode($response);
        } else {
            echo $response;
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => Config::get('app.debug', false) ? $e->getMessage() : 'An error occurred',
    ]);
}