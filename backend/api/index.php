<?php
/**
 * WiFight ISP System - API Entry Point
 *
 * Main router for all API requests
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load dependencies
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

// Load environment variables
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

$response = new Response();
$logger = new Logger('API');

try {
    // Get request URI and method
    $requestUri = $_SERVER['REQUEST_URI'];
    $requestMethod = $_SERVER['REQUEST_METHOD'];

    // Remove query string
    $requestUri = strtok($requestUri, '?');

    // Remove base path if exists
    $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $requestUri = substr($requestUri, strlen($basePath));
    $requestUri = trim($requestUri, '/');

    // Parse route
    $parts = explode('/', $requestUri);

    // Expected format: api/v1/resource/action
    if (empty($parts[0]) || $parts[0] !== 'api') {
        $response->notFound('Invalid API endpoint');
    }

    $version = $parts[1] ?? 'v1';
    $resource = $parts[2] ?? '';
    $action = $parts[3] ?? '';

    // Log request
    $logger->logRequest([
        'method' => $requestMethod,
        'uri' => $requestUri,
        'resource' => $resource,
        'action' => $action
    ]);

    // Route to appropriate handler
    $handlerPath = __DIR__ . "/{$version}/{$resource}.php";

    if (!file_exists($handlerPath)) {
        $response->notFound("Resource not found: {$resource}");
    }

    // Include resource handler
    require_once $handlerPath;

} catch (Exception $e) {
    $logger->error('API Error', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    $response->serverError(
        getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'Internal server error'
    );
}
