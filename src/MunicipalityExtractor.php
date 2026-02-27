<?php

namespace SpwTransform;

/**
 * Extracts Location -> Municipality mappings.
 *
 * Priority order:
 * 1. Static mappings from JSON files (for regions like Costa del Sol)
 * 2. Property data from Resales API (if API returns municipality info)
 * 3. Empty map (results in flat Area -> City hierarchy)
 */
class MunicipalityExtractor
{
    private ?ResalesClient $resalesClient;
    private Cache $cache;
    private string $domain;
    private MappingLoader $mappingLoader;

    public function __construct(?ResalesClient $resalesClient, Cache $cache, string $domain)
    {
        $this->resalesClient = $resalesClient;
        $this->cache = $cache;
        $this->domain = $domain;
        $this->mappingLoader = new MappingLoader();
    }

    /**
     * Get Location -> Municipality mapping for a specific area.
     * Returns array: ['Location Name' => 'Municipality Name', ...]
     */
    public function getMunicipalityMap(string $areaName = 'Costa del Sol'): array
    {
        // 1. Check static mappings first (most reliable)
        $staticMap = $this->mappingLoader->getMunicipalityMap($areaName);
        if (!empty($staticMap)) {
            return $staticMap;
        }

        // 2. Try to get from property data (cached)
        $cacheKey = "municipality_map_{$this->domain}_{$this->slugify($areaName)}";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        // 3. Build map from property data (if resales client available)
        if ($this->resalesClient) {
            $map = $this->buildMunicipalityMapFromProperties();

            // Cache for 24 hours (even if empty, to avoid repeated API calls)
            $this->cache->set($cacheKey, $map, 86400);

            return $map;
        }

        return [];
    }

    /**
     * Get all municipality maps for multiple areas.
     * Returns combined map for all areas.
     */
    public function getAllMunicipalityMaps(array $areaNames): array
    {
        $combined = [];

        foreach ($areaNames as $areaName) {
            $map = $this->getMunicipalityMap($areaName);
            $combined = array_merge($combined, $map);
        }

        return $combined;
    }

    /**
     * Build municipality map by scanning property data.
     */
    private function buildMunicipalityMapFromProperties(): array
    {
        $map = [];

        // Fetch properties in batches to gather location data
        $pageSize = 200;
        $maxPages = 5; // Scan up to 1000 properties
        $page = 1;

        while ($page <= $maxPages) {
            try {
                $response = $this->resalesClient->searchProperties([
                    'p_PageSize' => $pageSize,
                    'p_PageIndex' => $page,
                ]);

                $properties = $response['Property'] ?? [];

                if (empty($properties)) {
                    break;
                }

                // Handle single property
                if (isset($properties['Reference'])) {
                    $properties = [$properties];
                }

                foreach ($properties as $property) {
                    $location = trim($property['Location'] ?? '');
                    $municipality = trim($property['Region'] ?? $property['Municipality'] ?? '');

                    // Only add if we have both location and municipality
                    if ($location && $municipality) {
                        // Use first occurrence - municipalities shouldn't change
                        if (!isset($map[$location])) {
                            $map[$location] = $municipality;
                        }
                    }
                }

                // Check if we got fewer properties than page size (last page)
                if (count($properties) < $pageSize) {
                    break;
                }

                $page++;

            } catch (\Exception $e) {
                // Log error and stop scanning
                error_log("MunicipalityExtractor error on page $page: " . $e->getMessage());
                break;
            }
        }

        return $map;
    }

    /**
     * Clear cached municipality map (for manual refresh).
     */
    public function clearCache(string $areaName = null): void
    {
        if ($areaName) {
            $cacheKey = "municipality_map_{$this->domain}_{$this->slugify($areaName)}";
            $this->cache->delete($cacheKey);
        } else {
            // Clear all municipality caches for this domain
            // Note: This is a simple implementation; the Cache class could be enhanced
            // to support pattern-based deletion
            $this->cache->delete("municipality_map_{$this->domain}_costa-del-sol");
        }
    }

    /**
     * Check if static mapping exists for an area.
     */
    public function hasStaticMapping(string $areaName): bool
    {
        return $this->mappingLoader->hasMapping($areaName);
    }

    /**
     * Create URL-friendly slug from name.
     */
    private function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-') ?: 'unknown';
    }
}
