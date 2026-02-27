<?php

namespace SpwTransform;

/**
 * Transforms Resales Online locations into widget-compatible hierarchy.
 *
 * Resales SearchLocations returns:
 * LocationData -> Country -> ProvinceArea[] -> (ProvinceAreaName, Locations -> Location[])
 * Where Location[] is a flat array of location name strings
 *
 * To get municipality hierarchy, we extract it from property data.
 *
 * Widget expects: parent_id based hierarchy with types (area, municipality, city)
 */
class LocationTransformer
{
    private array $locationMap = [];
    private ?array $municipalityMap = null;

    /**
     * Transform Resales SearchLocations response into widget format.
     * Optionally include municipality hierarchy from property data.
     *
     * @param array $resalesData Data from SearchLocations endpoint
     * @param array|null $municipalityData Optional: Location -> Municipality mapping from properties
     */
    public function transform(array $resalesData, ?array $municipalityData = null): array
    {
        $result = [];

        // Store municipality mapping if provided
        if ($municipalityData !== null) {
            $this->municipalityMap = $municipalityData;
        }

        // Extract LocationData
        $locationData = $resalesData['LocationData'] ?? [];

        if (empty($locationData)) {
            return ['count' => 0, 'data' => [], 'pages' => 1, 'page' => 1];
        }

        // Get country name (usually Spain)
        $country = $locationData['Country'] ?? 'Spain';

        // Get ProvinceArea - can be single object or array
        $provinceAreas = $locationData['ProvinceArea'] ?? [];

        // Handle single ProvinceArea (not wrapped in array)
        if (isset($provinceAreas['ProvinceAreaName'])) {
            $provinceAreas = [$provinceAreas];
        }

        if (empty($provinceAreas)) {
            return ['count' => 0, 'data' => [], 'pages' => 1, 'page' => 1];
        }

        // Process each ProvinceArea (e.g., "Costa del Sol", "Costa Blanca")
        foreach ($provinceAreas as $area) {
            $areaName = $area['ProvinceAreaName'] ?? '';

            if (!$areaName) {
                continue;
            }

            // Create area entry (top level)
            $areaId = $this->getOrCreateId('area', $areaName);
            $areaPath = $this->slugify($areaName);

            $result[] = [
                'id' => $areaId,
                'name' => $areaName,
                'parent_id' => false,
                'type' => 'area',
                'path' => $areaPath,
            ];

            // Get locations within this area
            $locations = $area['Locations']['Location'] ?? [];

            // Handle single location (not wrapped in array)
            if (is_string($locations)) {
                $locations = [$locations];
            }

            // Group locations by municipality if we have the mapping
            if (!empty($this->municipalityMap)) {
                $result = array_merge($result, $this->buildHierarchyWithMunicipalities(
                    $locations,
                    $areaId,
                    $areaName,
                    $areaPath
                ));
            } else {
                // Fall back to flat Area -> City structure
                foreach ($locations as $locationName) {
                    if (!is_string($locationName) || empty(trim($locationName))) {
                        continue;
                    }

                    $locationName = trim($locationName);
                    $locationId = $this->getOrCreateId('city', $areaName . '/' . $locationName);
                    $locationPath = $areaPath . '/' . $this->slugify($locationName);

                    $result[] = [
                        'id' => $locationId,
                        'name' => $locationName,
                        'parent_id' => $areaId,
                        'type' => 'city',
                        'path' => $locationPath,
                    ];
                }
            }
        }

        return [
            'count' => count($result),
            'data' => $result,
            'pages' => 1,
            'page' => 1,
        ];
    }

    /**
     * Build hierarchy with municipality level: Area -> Municipality -> City
     */
    private function buildHierarchyWithMunicipalities(
        array $locations,
        int $areaId,
        string $areaName,
        string $areaPath
    ): array {
        $result = [];
        $municipalities = [];

        // First pass: group locations by municipality
        foreach ($locations as $locationName) {
            if (!is_string($locationName) || empty(trim($locationName))) {
                continue;
            }

            $locationName = trim($locationName);
            $municipality = $this->municipalityMap[$locationName] ?? '';

            if (empty($municipality)) {
                // No municipality - attach directly to area
                $municipality = '__direct__';
            }

            if (!isset($municipalities[$municipality])) {
                $municipalities[$municipality] = [];
            }
            $municipalities[$municipality][] = $locationName;
        }

        // Second pass: create hierarchy
        foreach ($municipalities as $municipalityName => $cityNames) {
            if ($municipalityName === '__direct__') {
                // Cities directly under area (no municipality)
                foreach ($cityNames as $cityName) {
                    $cityId = $this->getOrCreateId('city', $areaName . '/' . $cityName);
                    $cityPath = $areaPath . '/' . $this->slugify($cityName);

                    $result[] = [
                        'id' => $cityId,
                        'name' => $cityName,
                        'parent_id' => $areaId,
                        'type' => 'city',
                        'path' => $cityPath,
                    ];
                }
            } else {
                // Create municipality entry
                $muniId = $this->getOrCreateId('municipality', $areaName . '/' . $municipalityName);
                $muniPath = $areaPath . '/' . $this->slugify($municipalityName);

                $result[] = [
                    'id' => $muniId,
                    'name' => $municipalityName,
                    'parent_id' => $areaId,
                    'type' => 'municipality',
                    'path' => $muniPath,
                ];

                // Create city entries under municipality
                foreach ($cityNames as $cityName) {
                    $cityId = $this->getOrCreateId('city', $areaName . '/' . $municipalityName . '/' . $cityName);
                    $cityPath = $muniPath . '/' . $this->slugify($cityName);

                    $result[] = [
                        'id' => $cityId,
                        'name' => $cityName,
                        'parent_id' => $muniId,
                        'type' => 'city',
                        'path' => $cityPath,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Get or create a stable ID for a location.
     */
    private function getOrCreateId(string $type, string $key): int
    {
        $fullKey = $type . ':' . $key;

        if (!isset($this->locationMap[$fullKey])) {
            // Generate deterministic ID from key hash
            $this->locationMap[$fullKey] = abs(crc32($fullKey)) % 100000 + 100;
        }

        return $this->locationMap[$fullKey];
    }

    /**
     * Create URL-friendly slug from name.
     */
    private function slugify(string $text): string
    {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Replace accented characters
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        // Remove non-alphanumeric characters
        $text = preg_replace('/[^a-z0-9]+/', '', $text);

        return $text ?: 'unknown';
    }
}
