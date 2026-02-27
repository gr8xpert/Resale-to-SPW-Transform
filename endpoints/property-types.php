<?php

/**
 * Property types endpoint - GET /v1/property_types
 *
 * Fetches property types from Resales Online SearchPropertyTypes endpoint.
 * Applies display preferences (visibility, ordering, custom names).
 */

use SpwTransform\TypeTransformer;

// Fetch property types from Resales Online
$resalesData = $resalesClient->getPropertyTypes();

if (!$resalesData) {
    errorResponse('Failed to fetch property types from Resales Online', 502);
}

// Transform to widget format
$transformer = new TypeTransformer();
$result = $transformer->transform($resalesData);

// Apply display preferences from Laravel
$prefs = $laravelClient->getDisplayPreferences($domain, 'property_type');
$hiddenIds = array_map('strval', $prefs['hidden_ids'] ?? []);
$sortOrder = $prefs['sort_order'] ?? [];
$customNames = $prefs['custom_names'] ?? [];

// Filter out hidden types and apply custom names
if (!empty($hiddenIds) || !empty($customNames)) {
    $result['data'] = array_values(array_filter($result['data'], function ($type) use ($hiddenIds) {
        // Skip hidden types
        if (in_array((string) $type['id'], $hiddenIds)) {
            return false;
        }
        return true;
    }));

    // Apply custom names
    if (!empty($customNames)) {
        $result['data'] = array_map(function ($type) use ($customNames) {
            $id = (string) $type['id'];
            if (isset($customNames[$id]) && !empty($customNames[$id])) {
                $type['name'] = $customNames[$id];
            }
            return $type;
        }, $result['data']);
    }
}

// Apply custom sort order (keep parent-child relationships)
if (!empty($sortOrder)) {
    // Group by parent
    $parents = [];
    $children = [];

    foreach ($result['data'] as $type) {
        if ($type['parent_id'] === false) {
            $parents[] = $type;
        } else {
            $children[$type['parent_id']][] = $type;
        }
    }

    // Sort parents
    usort($parents, function ($a, $b) use ($sortOrder) {
        $orderA = $sortOrder[(string) $a['id']] ?? 9999;
        $orderB = $sortOrder[(string) $b['id']] ?? 9999;
        return $orderA - $orderB ?: strcmp($a['name'], $b['name']);
    });

    // Rebuild with children after each parent
    $sorted = [];
    foreach ($parents as $parent) {
        $sorted[] = $parent;
        if (isset($children[$parent['id']])) {
            // Sort children of this parent
            usort($children[$parent['id']], function ($a, $b) use ($sortOrder) {
                $orderA = $sortOrder[(string) $a['id']] ?? 9999;
                $orderB = $sortOrder[(string) $b['id']] ?? 9999;
                return $orderA - $orderB ?: strcmp($a['name'], $b['name']);
            });
            foreach ($children[$parent['id']] as $child) {
                $sorted[] = $child;
            }
        }
    }

    $result['data'] = $sorted;
}

// Update count after filtering
$result['count'] = count($result['data']);

jsonResponse($result);
