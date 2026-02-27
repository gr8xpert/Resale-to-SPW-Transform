<?php

/**
 * Property features endpoint - GET /v1/property_features
 *
 * Fetches features from Resales Online SearchFeatures endpoint.
 * Applies display preferences (visibility, ordering, custom names).
 */

use SpwTransform\FeatureTransformer;

// Fetch features from Resales Online
$resalesData = $resalesClient->getFeatures();

if (!$resalesData) {
    // Fallback to static features if API fails
    $transformer = new FeatureTransformer();
    $result = $transformer->getFeatures();
} else {
    // Transform to widget format
    $transformer = new FeatureTransformer();
    $result = $transformer->transformFromResales($resalesData);
}

// Apply display preferences from Laravel
$prefs = $laravelClient->getDisplayPreferences($domain, 'feature');
$hiddenIds = array_map('strval', $prefs['hidden_ids'] ?? []);
$sortOrder = $prefs['sort_order'] ?? [];
$customNames = $prefs['custom_names'] ?? [];

// Filter out hidden feature groups and values
if (!empty($hiddenIds) || !empty($customNames)) {
    $result['data'] = array_values(array_filter(
        array_map(function ($group) use ($hiddenIds, $customNames) {
            $groupId = 'group_' . $group['id'];

            // Skip hidden groups
            if (in_array($groupId, $hiddenIds)) {
                return null;
            }

            // Apply custom name to group
            if (isset($customNames[$groupId]) && !empty($customNames[$groupId])) {
                $group['name'] = $customNames[$groupId];
            }

            // Filter hidden values within group
            if (isset($group['value_ids'])) {
                $group['value_ids'] = array_values(array_filter(
                    array_map(function ($val) use ($hiddenIds, $customNames) {
                        $valId = (string) $val['id'];

                        // Skip hidden values
                        if (in_array($valId, $hiddenIds)) {
                            return null;
                        }

                        // Apply custom name
                        if (isset($customNames[$valId]) && !empty($customNames[$valId])) {
                            $val['name'] = $customNames[$valId];
                        }

                        return $val;
                    }, $group['value_ids'])
                ));
            }

            return $group;
        }, $result['data'])
    ));
}

// Apply custom sort order to groups
if (!empty($sortOrder)) {
    usort($result['data'], function ($a, $b) use ($sortOrder) {
        $orderA = $sortOrder['group_' . $a['id']] ?? 9999;
        $orderB = $sortOrder['group_' . $b['id']] ?? 9999;
        return $orderA - $orderB ?: strcmp($a['name'], $b['name']);
    });
}

// Update count after filtering
$result['count'] = count($result['data']);

jsonResponse($result);
