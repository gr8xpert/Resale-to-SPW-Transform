<?php

/**
 * Location endpoint - GET /v2/location
 *
 * Fetches locations from Resales Online SearchLocations endpoint.
 * Applies display preferences (visibility, ordering, custom names).
 *
 * Builds 3-level hierarchy (Area -> Municipality -> City) using:
 * 1. Static mappings from JSON files (e.g., costa-del-sol.json)
 * 2. Property data extraction (fallback if no static mapping)
 */

use SpwTransform\LocationTransformer;
use SpwTransform\MunicipalityExtractor;

// Fetch locations from Resales Online
$resalesData = $resalesClient->getLocations();

if (!$resalesData) {
    errorResponse('Failed to fetch locations from Resales Online', 502);
}

// Extract area names from the response to get appropriate mappings
$areaNames = [];
$locationData = $resalesData['LocationData'] ?? [];
$provinceAreas = $locationData['ProvinceArea'] ?? [];

// Handle single ProvinceArea (not wrapped in array)
if (isset($provinceAreas['ProvinceAreaName'])) {
    $provinceAreas = [$provinceAreas];
}

foreach ($provinceAreas as $area) {
    $areaName = $area['ProvinceAreaName'] ?? '';
    if ($areaName) {
        $areaNames[] = $areaName;
    }
}

// Get municipality mappings for all areas
$municipalityExtractor = new MunicipalityExtractor($resalesClient, $cache, $domain);
$municipalityMap = $municipalityExtractor->getAllMunicipalityMaps($areaNames);

// Transform to widget format with municipality hierarchy
$transformer = new LocationTransformer();
$result = $transformer->transform($resalesData, $municipalityMap);

// Apply display preferences from Laravel
$prefs = $laravelClient->getDisplayPreferences($domain, 'location');
$hiddenIds = array_map('strval', $prefs['hidden_ids'] ?? []);
$sortOrder = $prefs['sort_order'] ?? [];
$customNames = $prefs['custom_names'] ?? [];

// Filter out hidden locations and apply custom names
if (!empty($hiddenIds) || !empty($customNames)) {
    $result['data'] = array_values(array_filter($result['data'], function ($loc) use ($hiddenIds, $customNames) {
        // Skip hidden locations
        if (in_array((string) $loc['id'], $hiddenIds)) {
            return false;
        }
        return true;
    }));

    // Apply custom names
    if (!empty($customNames)) {
        $result['data'] = array_map(function ($loc) use ($customNames) {
            $id = (string) $loc['id'];
            if (isset($customNames[$id]) && !empty($customNames[$id])) {
                $loc['name'] = $customNames[$id];
            }
            return $loc;
        }, $result['data']);
    }
}

// Apply custom sort order
if (!empty($sortOrder)) {
    usort($result['data'], function ($a, $b) use ($sortOrder) {
        $orderA = $sortOrder[(string) $a['id']] ?? 9999;
        $orderB = $sortOrder[(string) $b['id']] ?? 9999;

        if ($orderA === $orderB) {
            return strcmp($a['name'], $b['name']);
        }
        return $orderA - $orderB;
    });
}

// Update count after filtering
$result['count'] = count($result['data']);

jsonResponse($result);
