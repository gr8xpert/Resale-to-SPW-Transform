<?php

namespace SpwTransform;

/**
 * Transforms property features into widget-compatible format.
 *
 * Resales SearchFeatures returns:
 * FeaturesData -> Category[] -> (@attributes.Name, Feature[])
 * Where Feature[] has Name and ParamName fields
 */
class FeatureTransformer
{
    /**
     * Transform features from Resales SearchFeatures response.
     */
    public function transformFromResales(array $resalesData): array
    {
        $features = [];
        $featuresData = $resalesData['FeaturesData'] ?? [];

        // Try different possible structures from Resales API
        $categories = $featuresData['Category'] ?? $featuresData['FeatureGroup'] ?? [];

        if (empty($categories)) {
            return $this->getFeatures(); // Fallback to static
        }

        // Handle single category (not wrapped in array)
        if (isset($categories['@attributes']) || isset($categories['Feature'])) {
            $categories = [$categories];
        }

        $groupId = 1;
        foreach ($categories as $category) {
            // Get category/group name from @attributes or direct field
            $groupName = $category['@attributes']['Name']
                ?? $category['FeatureGroupName']
                ?? $category['Name']
                ?? '';

            if (!$groupName) {
                continue;
            }

            $values = [];
            $featureList = $category['Feature'] ?? [];

            // Handle single feature (not wrapped in array)
            if (isset($featureList['Name']) || isset($featureList['FeatureName'])) {
                $featureList = [$featureList];
            }

            $featureId = 1;
            foreach ($featureList as $feature) {
                // Get feature name from different possible fields
                $featureName = $feature['Name']
                    ?? $feature['FeatureName']
                    ?? '';

                if ($featureName) {
                    // Get ID or generate from name
                    $id = $feature['FeatureId']
                        ?? $feature['Id']
                        ?? abs(crc32($groupName . '/' . $featureName)) % 1000;

                    $values[] = [
                        'id' => (int) $id,
                        'name' => $featureName,
                    ];
                }
                $featureId++;
            }

            // Get group ID or generate
            $gId = $category['FeatureGroupId']
                ?? $category['@attributes']['Id']
                ?? $groupId;

            $features[] = [
                'id' => (int) $gId,
                'name' => $groupName,
                'value_ids' => $values,
            ];

            $groupId++;
        }

        // If we got features from API, return them
        if (!empty($features)) {
            return [
                'count' => count($features),
                'data' => $features,
                'pages' => 1,
                'page' => 1,
            ];
        }

        // Fallback to static features
        return $this->getFeatures();
    }

    /**
     * Get all property features.
     * This is mostly static data based on Resales Online's standard features.
     */
    public function getFeatures(): array
    {
        return [
            'count' => 16,
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Setting',
                    'value_ids' => [
                        ['id' => 1, 'name' => 'Beachfront'],
                        ['id' => 2, 'name' => 'Frontline Golf'],
                        ['id' => 3, 'name' => 'Town'],
                        ['id' => 4, 'name' => 'Suburban'],
                        ['id' => 5, 'name' => 'Country'],
                        ['id' => 6, 'name' => 'Commercial Area'],
                        ['id' => 7, 'name' => 'Beachside'],
                        ['id' => 8, 'name' => 'Port'],
                        ['id' => 9, 'name' => 'Village'],
                        ['id' => 10, 'name' => 'Mountain Pueblo'],
                        ['id' => 11, 'name' => 'Close To Golf'],
                        ['id' => 12, 'name' => 'Close To Port'],
                        ['id' => 13, 'name' => 'Close To Shops'],
                        ['id' => 14, 'name' => 'Close To Sea'],
                        ['id' => 15, 'name' => 'Close To Town'],
                        ['id' => 16, 'name' => 'Close To Schools'],
                        ['id' => 19, 'name' => 'Marina'],
                        ['id' => 20, 'name' => 'Close To Marina'],
                        ['id' => 21, 'name' => 'Urbanisation'],
                        ['id' => 22, 'name' => 'Front Line Beach Complex'],
                    ],
                ],
                [
                    'id' => 2,
                    'name' => 'Orientation',
                    'value_ids' => [
                        ['id' => 23, 'name' => 'North Facing'],
                        ['id' => 24, 'name' => 'North East Orientation'],
                        ['id' => 25, 'name' => 'East Facing'],
                        ['id' => 26, 'name' => 'South East Orientation'],
                        ['id' => 27, 'name' => 'South Facing'],
                        ['id' => 28, 'name' => 'South West Orientation'],
                        ['id' => 29, 'name' => 'West Facing'],
                        ['id' => 30, 'name' => 'North West Orientation'],
                    ],
                ],
                [
                    'id' => 3,
                    'name' => 'Condition',
                    'value_ids' => [
                        ['id' => 31, 'name' => 'Excellent Condition'],
                        ['id' => 32, 'name' => 'Good Condition'],
                        ['id' => 33, 'name' => 'Fair Condition'],
                        ['id' => 34, 'name' => 'Renovation Required'],
                        ['id' => 35, 'name' => 'Recently Renovated'],
                        ['id' => 36, 'name' => 'Recently Refurbished'],
                        ['id' => 37, 'name' => 'Restoration Required'],
                        ['id' => 38, 'name' => 'New Construction'],
                    ],
                ],
                [
                    'id' => 4,
                    'name' => 'Pool',
                    'value_ids' => [
                        ['id' => 39, 'name' => 'Communal Pool'],
                        ['id' => 40, 'name' => 'Private Pool'],
                        ['id' => 41, 'name' => 'Indoor Pool'],
                        ['id' => 42, 'name' => 'Heated Pool'],
                        ['id' => 43, 'name' => 'Room For Pool'],
                        ['id' => 44, 'name' => 'Childrens Pool'],
                    ],
                ],
                [
                    'id' => 5,
                    'name' => 'Climate Control',
                    'value_ids' => [
                        ['id' => 45, 'name' => 'Air Conditioning'],
                        ['id' => 46, 'name' => 'Pre Installed A/C'],
                        ['id' => 47, 'name' => 'Hot A/C'],
                        ['id' => 48, 'name' => 'Cold A/C'],
                        ['id' => 49, 'name' => 'Central Heating'],
                        ['id' => 50, 'name' => 'Fireplace'],
                        ['id' => 51, 'name' => 'U/F Heating'],
                        ['id' => 52, 'name' => 'U/F/H Bathrooms'],
                    ],
                ],
                [
                    'id' => 6,
                    'name' => 'Views',
                    'value_ids' => [
                        ['id' => 53, 'name' => 'Sea Views'],
                        ['id' => 54, 'name' => 'Mountain Views'],
                        ['id' => 55, 'name' => 'Golf Views'],
                        ['id' => 56, 'name' => 'Beach Views'],
                        ['id' => 57, 'name' => 'Port Views'],
                        ['id' => 58, 'name' => 'Country Views'],
                        ['id' => 59, 'name' => 'Panoramic Views'],
                        ['id' => 60, 'name' => 'Garden Views'],
                        ['id' => 61, 'name' => 'Pool Views'],
                        ['id' => 64, 'name' => 'Urban Views'],
                    ],
                ],
                [
                    'id' => 7,
                    'name' => 'Features',
                    'value_ids' => [
                        ['id' => 68, 'name' => 'Covered Terrace'],
                        ['id' => 69, 'name' => 'Lift'],
                        ['id' => 70, 'name' => 'Fitted Wardrobes'],
                        ['id' => 71, 'name' => 'Near Transport'],
                        ['id' => 72, 'name' => 'Private Terrace'],
                        ['id' => 73, 'name' => 'Solarium'],
                        ['id' => 76, 'name' => 'Gym'],
                        ['id' => 77, 'name' => 'Sauna'],
                        ['id' => 79, 'name' => 'Paddle Tennis'],
                        ['id' => 80, 'name' => 'Tennis Court'],
                        ['id' => 83, 'name' => 'Storage Room'],
                        ['id' => 85, 'name' => 'Ensuite Bathroom'],
                        ['id' => 86, 'name' => 'Wood Flooring'],
                        ['id' => 88, 'name' => 'Marble Flooring'],
                        ['id' => 89, 'name' => 'Jacuzzi'],
                        ['id' => 91, 'name' => 'Barbeque'],
                        ['id' => 92, 'name' => 'Double Glazing'],
                    ],
                ],
                [
                    'id' => 8,
                    'name' => 'Furniture',
                    'value_ids' => [
                        ['id' => 105, 'name' => 'Fully Furnished'],
                        ['id' => 106, 'name' => 'Part Furnished'],
                        ['id' => 107, 'name' => 'Not Furnished'],
                        ['id' => 108, 'name' => 'Optional Furniture'],
                    ],
                ],
                [
                    'id' => 9,
                    'name' => 'Kitchen',
                    'value_ids' => [
                        ['id' => 109, 'name' => 'Fully Fitted Kitchen'],
                        ['id' => 110, 'name' => 'Partially Fitted Kitchen'],
                        ['id' => 111, 'name' => 'Not Fitted Kitchen'],
                        ['id' => 112, 'name' => 'Kitchen-Lounge'],
                    ],
                ],
                [
                    'id' => 10,
                    'name' => 'Garden',
                    'value_ids' => [
                        ['id' => 113, 'name' => 'Communal Garden'],
                        ['id' => 114, 'name' => 'Private Garden'],
                        ['id' => 115, 'name' => 'Landscaped Garden'],
                        ['id' => 116, 'name' => 'Easy Maintenance Garden'],
                    ],
                ],
                [
                    'id' => 11,
                    'name' => 'Security',
                    'value_ids' => [
                        ['id' => 117, 'name' => 'Gated Complex'],
                        ['id' => 118, 'name' => 'Electric Blinds'],
                        ['id' => 119, 'name' => 'Entry Phone'],
                        ['id' => 120, 'name' => 'Alarm System'],
                        ['id' => 121, 'name' => '24 Hour Security'],
                        ['id' => 122, 'name' => 'Safe'],
                    ],
                ],
                [
                    'id' => 12,
                    'name' => 'Parking',
                    'value_ids' => [
                        ['id' => 123, 'name' => 'Underground Parking'],
                        ['id' => 124, 'name' => 'Garage'],
                        ['id' => 125, 'name' => 'Covered Parking'],
                        ['id' => 126, 'name' => 'Open Parking'],
                        ['id' => 127, 'name' => 'Street Parking'],
                        ['id' => 128, 'name' => 'Multiple Parking Spaces'],
                        ['id' => 129, 'name' => 'Communal Parking'],
                        ['id' => 130, 'name' => 'Private Parking'],
                    ],
                ],
                [
                    'id' => 13,
                    'name' => 'Utilities',
                    'value_ids' => [
                        ['id' => 131, 'name' => 'Electricity'],
                        ['id' => 132, 'name' => 'Drinkable Water'],
                        ['id' => 133, 'name' => 'Telephone'],
                        ['id' => 134, 'name' => 'Gas'],
                        ['id' => 187, 'name' => 'Photovoltaic solar panels'],
                        ['id' => 188, 'name' => 'Solar water heating'],
                    ],
                ],
                [
                    'id' => 14,
                    'name' => 'Category',
                    'value_ids' => [
                        ['id' => 135, 'name' => 'Bargain'],
                        ['id' => 136, 'name' => 'Beachfront'],
                        ['id' => 139, 'name' => 'Golf'],
                        ['id' => 140, 'name' => 'Holiday Homes'],
                        ['id' => 141, 'name' => 'Investment'],
                        ['id' => 142, 'name' => 'Luxury'],
                        ['id' => 143, 'name' => 'Off Plan'],
                        ['id' => 144, 'name' => 'Reduced'],
                        ['id' => 146, 'name' => 'Resale'],
                        ['id' => 148, 'name' => 'Contemporary'],
                        ['id' => 149, 'name' => 'New Development'],
                    ],
                ],
            ],
            'pages' => 1,
            'page' => 1,
        ];
    }
}
