<?php

/**
 * Property detail endpoint - GET /v1/property/{ref}
 *
 * Fetches single property details from Resales Online.
 * Variables available from index.php: $resalesClient, $laravelClient, $domain, $language, $agencyCode
 * $_GET['ref'] is set by the router from path parameter
 */

use SpwTransform\PropertyTransformer;

$reference = $_GET['ref'] ?? '';

if (!$reference) {
    errorResponse('Property reference required', 400);
}

// Remove agency code prefix if present (widget might send full ref)
if ($agencyCode && str_starts_with($reference, $agencyCode)) {
    $reference = substr($reference, strlen($agencyCode));
}

// Fetch from Resales Online
$resalesData = $resalesClient->getPropertyDetails($reference, $language);

if (!$resalesData) {
    errorResponse('Failed to fetch property from Resales Online', 502);
}

// Check if property exists
$property = $resalesData['Property'] ?? null;
if (!$property) {
    errorResponse('Property not found', 404);
}

// Handle array wrap (sometimes Resales returns array of one)
if (isset($property[0])) {
    $property = $property[0];
}

// Transform to widget format
$transformer = new PropertyTransformer($agencyCode, $language);
$result = $transformer->transformProperty($property);

jsonResponse(['data' => $result]);
