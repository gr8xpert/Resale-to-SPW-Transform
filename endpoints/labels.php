<?php

/**
 * Plugin labels endpoint - GET /v1/plugin_labels
 *
 * Fetches merged labels from Laravel (defaults + client overrides).
 * Variables available from index.php: $resalesClient, $laravelClient, $domain, $language, $agencyCode
 */

// Fetch merged labels from Laravel
$labels = $laravelClient->getLabels($domain, $language);

// Return in widget-expected format
jsonResponse([
    'labels' => $labels,
    'language' => $language,
]);
