<?php

/**
 * Property search endpoint - GET /v1/property
 *
 * Searches properties from Resales Online and transforms to widget format.
 * Variables available from index.php: $resalesClient, $laravelClient, $domain, $language, $agencyCode
 */

use SpwTransform\PropertyTransformer;

// Build search parameters from query string
$searchParams = [];

// Pagination
$page = (int) ($_GET['_page'] ?? $_GET['page'] ?? 1);
$limit = (int) ($_GET['_limit'] ?? $_GET['limit'] ?? 12);
$searchParams['P_PageNo'] = max(1, $page);
$searchParams['P_PageSize'] = min(100, max(1, $limit));

// Property type filter
if (!empty($_GET['type_id'])) {
    $searchParams['P_PropertyTypes'] = $_GET['type_id'];
}

// Location filter
if (!empty($_GET['location_id'])) {
    $searchParams['P_Location'] = $_GET['location_id'];
}

// Bedrooms filter
if (!empty($_GET['bedrooms_from'])) {
    $searchParams['P_Bedrooms'] = $_GET['bedrooms_from'];
}
if (!empty($_GET['bedrooms_to'])) {
    $searchParams['P_BedroomsMax'] = $_GET['bedrooms_to'];
}

// Bathrooms filter
if (!empty($_GET['bathrooms_from'])) {
    $searchParams['P_Bathrooms'] = $_GET['bathrooms_from'];
}

// Price filter
if (!empty($_GET['price_from'])) {
    $searchParams['P_PriceFrom'] = $_GET['price_from'];
}
if (!empty($_GET['price_to'])) {
    $searchParams['P_PriceTo'] = $_GET['price_to'];
}

// Build size filter
if (!empty($_GET['build_size_from'])) {
    $searchParams['P_BuiltFrom'] = $_GET['build_size_from'];
}
if (!empty($_GET['build_size_to'])) {
    $searchParams['P_BuiltTo'] = $_GET['build_size_to'];
}

// Plot size filter
if (!empty($_GET['plot_size_from'])) {
    $searchParams['P_PlotFrom'] = $_GET['plot_size_from'];
}
if (!empty($_GET['plot_size_to'])) {
    $searchParams['P_PlotTo'] = $_GET['plot_size_to'];
}

// Features filter (multiple IDs comma-separated)
if (!empty($_GET['features'])) {
    $searchParams['P_Features'] = $_GET['features'];
}

// Reference search
if (!empty($_GET['reference'])) {
    $searchParams['P_RefId'] = $_GET['reference'];
}

// Get resales settings from config
$resalesSettings = $resalesConfig['resales_settings'] ?? [];

// Listing type: resale, development, short_rental, long_rental
$listingType = strtolower($_GET['listing_type'] ?? 'resales');

// Map listing type to settings and API parameters
$listingTypeMap = [
    'resales' => ['key' => 'resales', 'sale_rent' => '1'],
    'resale' => ['key' => 'resales', 'sale_rent' => '1'],
    'sale' => ['key' => 'resales', 'sale_rent' => '1'],
    'developments' => ['key' => 'developments', 'sale_rent' => '1', 'new_dev' => '1'],
    'development' => ['key' => 'developments', 'sale_rent' => '1', 'new_dev' => '1'],
    'new' => ['key' => 'developments', 'sale_rent' => '1', 'new_dev' => '1'],
    'short_rentals' => ['key' => 'short_rentals', 'sale_rent' => '0'],
    'short_rental' => ['key' => 'short_rentals', 'sale_rent' => '0'],
    'rental' => ['key' => 'short_rentals', 'sale_rent' => '0'],
    'rent' => ['key' => 'short_rentals', 'sale_rent' => '0'],
    'long_rentals' => ['key' => 'long_rentals', 'sale_rent' => '0'],
    'long_rental' => ['key' => 'long_rentals', 'sale_rent' => '0'],
];

$ltConfig = $listingTypeMap[$listingType] ?? $listingTypeMap['resales'];
$ltSettings = $resalesSettings[$ltConfig['key']] ?? [];

// Check if this listing type is enabled
if (isset($ltSettings['enabled']) && !$ltSettings['enabled']) {
    // Listing type is disabled - return empty results
    jsonResponse(['data' => [], 'count' => 0, 'total' => 0, 'page' => 1, 'pages' => 0]);
}

// Apply sale/rent parameter
$searchParams['P_SaleRent'] = $ltConfig['sale_rent'];

// Apply new development flag
if (!empty($ltConfig['new_dev'])) {
    $searchParams['P_NewDevelopment'] = '1';
}

// Override filter ID if specified for this listing type
if (!empty($ltSettings['filter_id'])) {
    $resalesClient->setFilterId($ltSettings['filter_id']);
}

// Apply min price from listing type settings (only if not already filtered by user)
if (empty($_GET['price_from']) && !empty($ltSettings['min_price'])) {
    $searchParams['P_PriceFrom'] = $ltSettings['min_price'];
}

// Apply own filter if specified
if (!empty($ltSettings['own_filter'])) {
    $searchParams['P_OwnProperty'] = '1';
}

// Sorting
$sortField = $_GET['_sort'] ?? '';
$sortOrder = strtolower($_GET['_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$sortMap = [
    'list_price' => 'Price',
    'price' => 'Price',
    'bedrooms' => 'Bedrooms',
    'build_size' => 'Built',
    'created_at' => 'LastModified',
    'updated_at' => 'LastModified',
];

if (isset($sortMap[$sortField])) {
    $searchParams['P_SortType'] = $sortMap[$sortField];
    $searchParams['P_SortOrder'] = $sortOrder;
}

// Language
$langMap = [
    'en_US' => '1', 'en_GB' => '1',
    'es_ES' => '2', 'de_DE' => '4',
    'fr_FR' => '5', 'nl_NL' => '3',
    'ru_RU' => '6', 'pt_PT' => '7',
];
$searchParams['P_Lang'] = $langMap[$language] ?? '1';

// Fetch from Resales Online
$resalesData = $resalesClient->searchProperties($searchParams);

if (!$resalesData) {
    errorResponse('Failed to fetch properties from Resales Online', 502);
}

// Transform to widget format
$transformer = new PropertyTransformer($agencyCode, $language);
$result = $transformer->transformList($resalesData);

// Add pagination info
$result['page'] = $page;
$result['pages'] = ceil($result['total'] / $limit);

jsonResponse($result);
