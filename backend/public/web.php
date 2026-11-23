<?php
/**
 * Web Entry Point
 * 
 * This handles all web requests and serves the frontend.
 * Static files are served directly by the web server.
 */

require_once __DIR__ . '/../bootstrap/app.php';

// Load web routes
require_once __DIR__ . '/../routes/web.php';

// Dispatch
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

try {
    Router::dispatch($method, $uri);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Web Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    if (Config::get('app.debug', false)) {
        echo "<h1>Error</h1><pre>" . htmlspecialchars($e->getMessage()) . "</pre><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        include __DIR__ . '/../resources/views/errors/500.php';
    }
}