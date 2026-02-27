<?php

namespace SpwTransform;

/**
 * Transforms Resales Online properties into widget-compatible format.
 */
class PropertyTransformer
{
    private string $agencyCode;
    private string $language;

    public function __construct(string $agencyCode = '', string $language = 'en_US')
    {
        $this->agencyCode = $agencyCode;
        $this->language = $language;
    }

    /**
     * Transform a list of properties from search results.
     */
    public function transformList(array $resalesData): array
    {
        $properties = $resalesData['Property'] ?? [];

        if (!is_array($properties)) {
            $properties = [];
        }

        // If single property, wrap in array
        if (isset($properties['Reference'])) {
            $properties = [$properties];
        }

        $transformed = [];
        foreach ($properties as $property) {
            $transformed[] = $this->transformProperty($property);
        }

        return [
            'data' => $transformed,
            'count' => count($transformed),
            'total' => (int) ($resalesData['QueryInfo']['PropertyCount'] ?? count($transformed)),
        ];
    }

    /**
     * Transform a single property.
     */
    public function transformProperty(array $property): array
    {
        $ref = $property['Reference'] ?? '';
        $id = $this->agencyCode ? $this->agencyCode . $ref : $ref;

        // Get description in requested language
        $description = $this->getLocalizedValue($property, 'Description');

        // Build features array
        $features = $this->extractFeatures($property);

        // Extract images
        $images = $this->extractImages($property);

        // Get location hierarchy
        $locationId = $this->extractLocationId($property);
        $locationName = $property['Location'] ?? '';
        $areaName = $property['Area'] ?? 'Costa del Sol';
        $provinceName = $property['Province'] ?? 'Málaga';

        // Get property type
        $typeName = $this->getLocalizedValue($property, 'PropertyType', 'Type');
        $typeId = $property['PropertyType']['TypeId'] ?? $property['TypeId'] ?? 0;

        // Build the widget-compatible property
        return [
            'id' => $id,
            'ref_no' => $id,
            'unique_ref' => $id,
            'agent_ref' => $property['AgentRef'] ?? '',
            'status' => [
                'title' => $property['Status'] ?? 'Available',
                'value' => $property['Status'] ?? 'Available',
            ],
            'name' => $this->buildPropertyTitle($property),
            'agent_id' => [
                'id' => 0,
                'name' => $property['CompanyName'] ?? '',
                'lang' => ['en_US', 'es_ES'],
            ],
            'area_id' => [
                'id' => abs(crc32($areaName)) % 1000 + 100,
                'name' => $areaName,
            ],
            'municipality_id' => [
                'id' => $this->getMunicipalityId($property),
                'name' => $property['Region'] ?? $property['Municipality'] ?? '',
            ],
            'location_id' => [
                'id' => $locationId,
                'name' => $locationName,
            ],
            'province_id' => [
                'id' => 0,
                'name' => $provinceName,
            ],
            'province_id2' => [
                'id' => 0,
                'name' => $areaName,
            ],
            'suburb' => $property['SubLocation'] ?? '',
            'zipcode' => $property['PostalCode'] ?? $property['Zipcode'] ?? '',
            'bathrooms' => (string) ($property['Bathrooms'] ?? 0),
            'bedrooms' => (string) ($property['Bedrooms'] ?? 0),
            'build_size' => (int) ($property['Built'] ?? $property['BuiltArea'] ?? 0),
            'terrace_size' => (int) ($property['Terrace'] ?? 0),
            'plot_size' => (int) ($property['GardenPlot'] ?? $property['Plot'] ?? 0),
            'type_id' => [
                'id' => (int) $typeId,
                'name' => $typeName,
            ],
            'is_own' => ($property['OwnProperty'] ?? $property['Own'] ?? 0) == 1,
            'desc' => $description,
            'video_url' => $this->extractVideoUrl($property),
            'virtual_tour_url' => $this->extractVirtualTourUrl($property),
            'pools' => ($property['Pool'] ?? 0) ? 1 : 0,
            'parking' => ($property['Parking'] ?? 0) ? 1 : 0,
            'cars' => (int) ($property['Parking'] ?? 0),
            'garden' => ($property['Garden'] ?? 0) ? 1 : 0,
            'features' => $features,
            'images' => $images,
            'ibi_fees' => (float) ($property['IBI'] ?? 0),
            'basura_tax' => (float) ($property['Basura'] ?? 0),
            'completion_date' => $property['BuiltYear'] ?? '',
            'built_year' => $property['BuiltYear'] ?? '',
            'community_fees' => (float) ($property['CommunityFees'] ?? 0),
            'community_fees_monthly' => (int) (($property['CommunityFees'] ?? 0) / 12),
            'energy_rating' => $property['EnergyRated'] ?? '',
            'list_price' => (int) ($property['Price'] ?? $property['CurrentPrice'] ?? 0),
            'listing_type' => $this->determineListingType($property),
            'pdf' => $this->buildPdfUrl($id),
            'latitude' => (float) ($property['Latitude'] ?? 0),
            'longitude' => (float) ($property['Longitude'] ?? 0),
        ];
    }

    /**
     * Get localized value from property.
     */
    private function getLocalizedValue(array $property, string $field, string $altField = null): string
    {
        // Try exact field with language suffix
        $langSuffix = $this->getLanguageSuffix();

        // Try: Description_EN, DescriptionEN, Description
        $variants = [
            $field . '_' . $langSuffix,
            $field . $langSuffix,
            $field,
        ];

        if ($altField) {
            $variants[] = $altField . '_' . $langSuffix;
            $variants[] = $altField . $langSuffix;
            $variants[] = $altField;
        }

        foreach ($variants as $key) {
            if (!empty($property[$key]) && is_string($property[$key])) {
                return $property[$key];
            }
        }

        return '';
    }

    /**
     * Get language suffix for localized fields.
     */
    private function getLanguageSuffix(): string
    {
        $map = [
            'en_US' => 'EN',
            'en_GB' => 'EN',
            'es_ES' => 'ES',
            'de_DE' => 'DE',
            'fr_FR' => 'FR',
            'nl_NL' => 'NL',
            'ru_RU' => 'RU',
        ];

        return $map[$this->language] ?? 'EN';
    }

    /**
     * Build property title.
     */
    private function buildPropertyTitle(array $property): string
    {
        $type = $this->getLocalizedValue($property, 'PropertyType', 'Type');
        $location = $property['Location'] ?? '';

        if ($type && $location) {
            return "$type in $location";
        }

        return $type ?: $property['Reference'] ?? 'Property';
    }

    /**
     * Extract features from property.
     */
    private function extractFeatures(array $property): array
    {
        $features = [];

        // Map Resales categories to features
        $categoryMappings = [
            'Setting' => ['Beachfront', 'Frontline Golf', 'Town', 'Country', 'Village', 'Beachside', 'Port', 'Marina'],
            'Condition' => ['Excellent', 'Good', 'Fair', 'Renovation Required', 'New Construction'],
            'Pool' => ['Private Pool', 'Communal Pool', 'Heated Pool', 'Indoor Pool'],
            'Climate Control' => ['Air Conditioning', 'Central Heating', 'Fireplace', 'U/F Heating'],
            'Views' => ['Sea Views', 'Mountain Views', 'Golf Views', 'Garden Views', 'Panoramic Views'],
            'Security' => ['Gated Complex', 'Alarm System', '24 Hour Security', 'Entry Phone'],
            'Parking' => ['Private Parking', 'Underground Parking', 'Garage', 'Covered Parking'],
            'Features' => ['Covered Terrace', 'Lift', 'Fitted Wardrobes', 'Private Terrace', 'Gym', 'Sauna'],
            'Category' => ['Resale', 'New Development', 'Investment', 'Holiday Homes', 'Luxury'],
        ];

        // Check each category in property data
        foreach ($categoryMappings as $category => $possibleValues) {
            $categoryData = $property[$category] ?? $property['MainFeatures'][$category] ?? null;

            if ($categoryData && is_string($categoryData)) {
                $features[] = [
                    'name' => $categoryData,
                    'attr_id' => ['name' => $category],
                ];
            } elseif (is_array($categoryData)) {
                foreach ($categoryData as $feature) {
                    $featureName = is_array($feature) ? ($feature['Name'] ?? $feature['Value'] ?? '') : $feature;
                    if ($featureName) {
                        $features[] = [
                            'name' => $featureName,
                            'attr_id' => ['name' => $category],
                        ];
                    }
                }
            }
        }

        return $features;
    }

    /**
     * Extract images from property.
     */
    private function extractImages(array $property): array
    {
        $images = [];
        $imageData = $property['Pictures']['Picture'] ?? $property['Images']['Image'] ?? [];

        if (!is_array($imageData)) {
            return [];
        }

        // Handle single image
        if (isset($imageData['PictureURL']) || isset($imageData['URL'])) {
            $imageData = [$imageData];
        }

        foreach ($imageData as $image) {
            $url = $image['PictureURL'] ?? $image['URL'] ?? $image['Url'] ?? '';
            if ($url) {
                $images[] = ['src' => $url];
            }
        }

        return $images;
    }

    /**
     * Extract location ID.
     */
    private function extractLocationId(array $property): int
    {
        if (isset($property['LocationId'])) {
            return (int) $property['LocationId'];
        }

        // Generate from location name
        $location = $property['Location'] ?? '';
        return $location ? abs(crc32($location)) % 10000 + 100 : 0;
    }

    /**
     * Get municipality ID.
     */
    private function getMunicipalityId(array $property): int
    {
        $municipality = $property['Region'] ?? $property['Municipality'] ?? '';
        return $municipality ? abs(crc32($municipality)) % 10000 + 500 : 0;
    }

    /**
     * Determine listing type.
     */
    private function determineListingType(array $property): string
    {
        $type = strtolower($property['ListingType'] ?? $property['TransactionType'] ?? '');

        if (str_contains($type, 'rent') || str_contains($type, 'rental')) {
            return 'rental';
        }

        if (str_contains($type, 'new') || str_contains($type, 'development')) {
            return 'new';
        }

        return 'resale';
    }

    /**
     * Build PDF URL.
     */
    private function buildPdfUrl(string $reference): string
    {
        // This would need to be configured based on your PDF generation service
        return "https://api.smartpropertywidget.com/pdf/{$reference}.pdf";
    }

    /**
     * Extract video URL from property data.
     * Resales API may use different field names.
     */
    private function extractVideoUrl(array $property): string
    {
        // Check various possible field names
        $possibleFields = [
            'VideoURL',
            'VideoUrl',
            'Video_URL',
            'Video',
            'VideoTour',
            'VideoTourURL',
            'VideoTourUrl',
        ];

        foreach ($possibleFields as $field) {
            if (!empty($property[$field]) && is_string($property[$field])) {
                return $property[$field];
            }
        }

        // Check nested structures
        if (isset($property['Media']['Video']) && is_string($property['Media']['Video'])) {
            return $property['Media']['Video'];
        }

        return '';
    }

    /**
     * Extract virtual tour URL from property data.
     * Resales API may use different field names.
     */
    private function extractVirtualTourUrl(array $property): string
    {
        // Check various possible field names
        $possibleFields = [
            'VirtualTourURL',
            'VirtualTourUrl',
            'VirtualTour',
            'Virtual_Tour',
            'VirtualTour_URL',
            'Tour360',
            'Tour360URL',
            'PanoramicTour',
        ];

        foreach ($possibleFields as $field) {
            if (!empty($property[$field]) && is_string($property[$field])) {
                return $property[$field];
            }
        }

        // Check nested structures
        if (isset($property['Media']['VirtualTour']) && is_string($property['Media']['VirtualTour'])) {
            return $property['Media']['VirtualTour'];
        }

        if (isset($property['VirtualTours']['VirtualTour'])) {
            $tour = $property['VirtualTours']['VirtualTour'];
            if (is_string($tour)) {
                return $tour;
            }
            if (is_array($tour) && isset($tour[0]['URL'])) {
                return $tour[0]['URL'];
            }
            if (is_array($tour) && isset($tour['URL'])) {
                return $tour['URL'];
            }
        }

        return '';
    }
}
