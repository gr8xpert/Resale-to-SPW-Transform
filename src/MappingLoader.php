<?php

namespace SpwTransform;

/**
 * Loads static municipality mappings from JSON files.
 *
 * These mappings define which cities belong to which municipalities
 * for regions where the Resales API doesn't provide this data.
 */
class MappingLoader
{
    private string $mappingsDir;
    private array $loadedMappings = [];

    public function __construct(string $mappingsDir = null)
    {
        $this->mappingsDir = $mappingsDir ?? __DIR__ . '/../mappings';
    }

    /**
     * Get municipality mapping for a specific area.
     * Returns array: ['City Name' => 'Municipality Name', ...]
     */
    public function getMunicipalityMap(string $areaName): array
    {
        // Normalize area name for filename
        $filename = $this->areaToFilename($areaName);

        if (isset($this->loadedMappings[$filename])) {
            return $this->loadedMappings[$filename];
        }

        $filePath = $this->mappingsDir . '/' . $filename . '.json';

        if (!file_exists($filePath)) {
            return [];
        }

        $json = file_get_contents($filePath);
        $data = json_decode($json, true);

        if (!$data) {
            return [];
        }

        // Convert the grouped format to flat City => Municipality map
        $map = [];
        foreach ($data as $key => $group) {
            // Skip metadata keys
            if (str_starts_with($key, '_')) {
                continue;
            }

            if (!isset($group['municipality']) || !isset($group['cities'])) {
                continue;
            }

            $municipality = $group['municipality'];
            foreach ($group['cities'] as $city) {
                $map[$city] = $municipality;
            }
        }

        $this->loadedMappings[$filename] = $map;
        return $map;
    }

    /**
     * Check if a mapping exists for an area.
     */
    public function hasMapping(string $areaName): bool
    {
        $filename = $this->areaToFilename($areaName);
        $filePath = $this->mappingsDir . '/' . $filename . '.json';
        return file_exists($filePath);
    }

    /**
     * Get all available mapping files.
     */
    public function getAvailableMappings(): array
    {
        $files = glob($this->mappingsDir . '/*.json');
        $mappings = [];

        foreach ($files as $file) {
            $name = basename($file, '.json');
            $mappings[] = $this->filenameToArea($name);
        }

        return $mappings;
    }

    /**
     * Convert area name to filename.
     * "Costa del Sol" => "costa-del-sol"
     */
    private function areaToFilename(string $areaName): string
    {
        $filename = mb_strtolower($areaName, 'UTF-8');
        $filename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
        $filename = preg_replace('/[^a-z0-9]+/', '-', $filename);
        $filename = trim($filename, '-');
        return $filename ?: 'unknown';
    }

    /**
     * Convert filename back to area name (for display).
     * "costa-del-sol" => "Costa Del Sol"
     */
    private function filenameToArea(string $filename): string
    {
        return ucwords(str_replace('-', ' ', $filename));
    }
}
