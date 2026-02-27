<?php

/**
 * SPW-Transform Microservice
 *
 * Transforms Resales Online API data into the format expected
 * by the Smart Property Widget.
 *
 * Endpoints:
 *   GET /v1/property           - Search properties
 *   GET /v1/property/{ref}     - Get property details
 *   GET /v2/location           - Get locations hierarchy
 *   GET /v1/property_types     - Get property types
 *   GET /v1/property_features  - Get property features
 *   GET /v1/plugin_labels      - Get UI labels
 */

// Load configuration
$config = require __DIR__ . '/config/config.php';

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'SpwTransform\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Parse request
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);
$path = preg_replace('#^/spw-transform#', '', $path); // Remove base path if present
$path = rtrim($path, '/');

// Get domain from query or referer
$domain = $_GET['_domain'] ?? '';
if (!$domain && isset($_SERVER['HTTP_REFERER'])) {
    $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    if ($refererHost) {
        $domain = preg_replace('/^www\./', '', $refererHost);
    }
}

// Initialize services
$cache = new SpwTransform\Cache($config['cache']);
$laravelClient = new SpwTransform\LaravelClient($config, $cache);
$resalesClient = new SpwTransform\ResalesClient($config, $cache);

// Get language parameter
$language = $_GET['_lang'] ?? $_GET['ln'] ?? 'en_US';

/**
 * Response helper
 */
function jsonResponse($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Error response helper
 */
function errorResponse(string $message, int $status = 400): void
{
    jsonResponse(['error' => $message], $status);
}

// Validate domain
if (!$domain) {
    errorResponse('Missing domain parameter (_domain or Referer header)', 400);
}

// Get Resales credentials from Laravel
$resalesConfig = $laravelClient->getResalesConfig($domain);
if (!$resalesConfig) {
    errorResponse('Domain not configured or Resales credentials missing', 404);
}

// Set Resales credentials
$resalesClient->setCredentials(
    $resalesConfig['resales_client_id'],
    $resalesConfig['resales_api_key'],
    $resalesConfig['resales_filter_id'] ?? '1'
);

$agencyCode = $resalesConfig['resales_agency_code'] ?? '';

// Route the request
try {
    switch (true) {
        // Location hierarchy
        case $path === '/v2/location' || $path === '/v1/location':
            require __DIR__ . '/endpoints/location.php';
            break;

        // Property types
        case $path === '/v1/property_types':
            require __DIR__ . '/endpoints/property-types.php';
            break;

        // Property features
        case $path === '/v1/property_features':
            require __DIR__ . '/endpoints/features.php';
            break;

        // Plugin labels
        case $path === '/v1/plugin_labels':
            require __DIR__ . '/endpoints/labels.php';
            break;

        // Property search
        case $path === '/v1/property':
            require __DIR__ . '/endpoints/property.php';
            break;

        // Property detail (with reference in path or query)
        case preg_match('#^/v1/property/(.+)$#', $path, $matches):
            $_GET['ref'] = $matches[1];
            require __DIR__ . '/endpoints/property-detail.php';
            break;

        default:
            errorResponse('Endpoint not found: ' . $path, 404);
    }
} catch (\Throwable $e) {
    if ($config['debug']) {
        errorResponse($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 500);
    } else {
        errorResponse('Internal server error', 500);
    }
}
