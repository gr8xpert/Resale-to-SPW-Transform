<?php

/**
 * SPW-Transform Microservice Configuration
 *
 * This microservice transforms Resales Online API data
 * into the format expected by the Smart Property Widget.
 *
 * Configuration priority:
 * 1. config/config.local.php (if exists) - RECOMMENDED for hosting without env vars
 * 2. Environment variables
 * 3. Default values
 */

// Load local config if exists (for hosts without env var support)
$localConfig = [];
if (file_exists(__DIR__ . '/config.local.php')) {
    $localConfig = require __DIR__ . '/config.local.php';
}

return [
    // Laravel app URL (for fetching client credentials)
    'laravel_url' => $localConfig['laravel_url']
        ?? (getenv('LARAVEL_URL') ?: 'https://sm.smartpropertywidget.com'),

    // Internal API key (must match INTERNAL_API_KEY in Laravel .env)
    'internal_api_key' => $localConfig['internal_api_key']
        ?? (getenv('INTERNAL_API_KEY') ?: ''),

    // Resales Online API base URL
    'resales_api_url' => 'https://webapi.resales-online.com/V6',

    // Cache settings
    'cache' => [
        'enabled' => true,
        'directory' => __DIR__ . '/../cache',
        'ttl' => [
            'locations' => 86400,      // 24 hours
            'property_types' => 86400, // 24 hours
            'features' => 86400,       // 24 hours
            'labels' => 86400,         // 24 hours
            'properties' => 300,       // 5 minutes
            'property_detail' => 3600, // 1 hour
        ],
    ],

    // CORS settings
    'cors' => [
        'allowed_origins' => ['*'], // In production, restrict to specific domains
        'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    ],

    // Debug mode (set via environment)
    'debug' => getenv('DEBUG') === 'true',
];
