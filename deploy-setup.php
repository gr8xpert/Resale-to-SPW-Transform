<?php

/**
 * Microservice Setup Script
 *
 * Upload to spw-transform root folder and run via browser:
 * https://api.smartpropertywidget.com/deploy-setup.php?key=YOUR_SECRET_KEY
 *
 * DELETE THIS FILE AFTER RUNNING!
 */

// ============================================================
// SECURITY KEY - Change this to something random before uploading
// ============================================================
$secretKey = 'spw-deploy-2027-xyz123';

// Verify key
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    die('Access denied. Provide correct key in URL: ?key=YOUR_KEY');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== SPW-Transform Microservice Setup ===\n\n";

$results = [];

// 1. Create cache directory
echo "--- Creating cache directory ---\n";
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    if (mkdir($cacheDir, 0755, true)) {
        echo "✓ Created: {$cacheDir}\n";
        $results['cache_dir'] = true;
    } else {
        echo "✗ Failed to create cache directory\n";
        $results['cache_dir'] = false;
    }
} else {
    echo "✓ Already exists: {$cacheDir}\n";
    $results['cache_dir'] = true;
}

// Check if writable
if (is_writable($cacheDir)) {
    echo "✓ Cache directory is writable\n\n";
} else {
    echo "✗ Cache directory is NOT writable - fix permissions!\n\n";
    $results['cache_dir'] = false;
}

// 2. Create .htaccess for cache protection
echo "--- Protecting cache directory ---\n";
$htaccess = $cacheDir . '/.htaccess';
if (!file_exists($htaccess)) {
    $content = "Deny from all\n";
    if (file_put_contents($htaccess, $content)) {
        echo "✓ Created .htaccess in cache folder\n\n";
        $results['htaccess'] = true;
    } else {
        echo "✗ Failed to create .htaccess\n\n";
        $results['htaccess'] = false;
    }
} else {
    echo "✓ .htaccess already exists\n\n";
    $results['htaccess'] = true;
}

// 3. Check if config.local.php exists
echo "--- Checking config.local.php ---\n";
$configLocal = __DIR__ . '/config/config.local.php';
if (file_exists($configLocal)) {
    echo "✓ config.local.php exists\n\n";
    $results['config'] = true;
} else {
    echo "✗ config.local.php NOT FOUND\n";
    echo "  You need to create: {$configLocal}\n";
    echo "  Copy from config.local.example.php and update values\n\n";
    $results['config'] = false;

    // Show template
    echo "--- config.local.php Template ---\n";
    echo "Create this file with your production values:\n\n";
    echo "<?php\n";
    echo "return [\n";
    echo "    'laravel_url' => 'https://sm.smartpropertywidget.com',\n";
    echo "    'internal_api_key' => 'YOUR_INTERNAL_API_KEY_HERE',\n";
    echo "    'debug' => false,\n";
    echo "];\n\n";
}

// 4. Test Laravel connection
echo "--- Testing Laravel Connection ---\n";
if (file_exists($configLocal)) {
    $localConfig = require $configLocal;
    $baseConfig = require __DIR__ . '/config/config.php';
    $config = array_merge($baseConfig, $localConfig);

    $url = rtrim($config['laravel_url'], '/') . '/api/internal/client-resales-config?domain=test.com';

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Authorization: Bearer ' . $config['internal_api_key'],
                'Accept: application/json',
            ],
            'timeout' => 10,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response !== false) {
        $data = json_decode($response, true);
        if (isset($data['error']) && $data['error'] === 'Client not found for domain') {
            echo "✓ Laravel connection works! (404 for test domain is expected)\n\n";
            $results['laravel'] = true;
        } elseif (isset($data['resales_client_id'])) {
            echo "✓ Laravel connection works!\n\n";
            $results['laravel'] = true;
        } else {
            echo "? Unexpected response: " . substr($response, 0, 200) . "\n\n";
            $results['laravel'] = false;
        }
    } else {
        echo "✗ Failed to connect to Laravel\n";
        echo "  URL: {$url}\n\n";
        $results['laravel'] = false;
    }
} else {
    echo "⊘ Skipped - config.local.php not found\n\n";
    $results['laravel'] = false;
}

// Summary
echo "=== SUMMARY ===\n";
foreach ($results as $check => $success) {
    $status = $success ? '✓ OK' : '✗ NEEDS ATTENTION';
    echo "{$check}: {$status}\n";
}

echo "\n";
echo "==============================================\n";
echo "⚠️  IMPORTANT: DELETE THIS FILE NOW!\n";
echo "    File: " . __FILE__ . "\n";
echo "==============================================\n";
