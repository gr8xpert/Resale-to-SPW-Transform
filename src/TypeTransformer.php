<?php

namespace SpwTransform;

/**
 * Transforms Resales Online property types into widget-compatible format.
 *
 * Resales SearchPropertyTypes returns:
 * PropertyTypes -> PropertyType[] -> (Type, OptionValue, SubType[])
 * Where each PropertyType has a main Type name and SubType array
 */
class TypeTransformer
{
    private int $nextId = 1;

    /**
     * Transform property types from Resales SearchPropertyTypes response.
     */
    public function transform(array $resalesData): array
    {
        $types = [];
        $propertyTypes = $resalesData['PropertyTypes']['PropertyType'] ?? [];

        if (empty($propertyTypes)) {
            return ['count' => 0, 'data' => [], 'pages' => 1, 'page' => 1];
        }

        // Handle single PropertyType (not wrapped in array)
        if (isset($propertyTypes['Type'])) {
            $propertyTypes = [$propertyTypes];
        }

        foreach ($propertyTypes as $typeData) {
            // Get main type info
            $mainTypeName = $typeData['Type'] ?? '';
            $mainTypeValue = $typeData['OptionValue'] ?? '';

            if (!$mainTypeName) {
                continue;
            }

            // Generate stable ID for main type
            $mainTypeId = $this->getTypeId($mainTypeName);

            // Add main type (parent)
            $types[] = [
                'id' => $mainTypeId,
                'name' => $mainTypeName,
                'parent_id' => false,
                'option_value' => $mainTypeValue,
            ];

            // Process subtypes
            $subTypes = $typeData['SubType'] ?? [];

            // Handle single SubType (not wrapped in array)
            if (isset($subTypes['Type'])) {
                $subTypes = [$subTypes];
            }

            foreach ($subTypes as $subType) {
                $subTypeName = $subType['Type'] ?? '';
                $subTypeValue = $subType['OptionValue'] ?? '';

                if (!$subTypeName) {
                    continue;
                }

                // Generate stable ID for subtype
                $subTypeId = $this->getTypeId($mainTypeName . '/' . $subTypeName);

                $types[] = [
                    'id' => $subTypeId,
                    'name' => $subTypeName,
                    'parent_id' => $mainTypeId,
                    'option_value' => $subTypeValue,
                ];
            }
        }

        return [
            'count' => count($types),
            'data' => $types,
            'pages' => 1,
            'page' => 1,
        ];
    }

    /**
     * Generate stable ID from type name.
     */
    private function getTypeId(string $name): int
    {
        // Generate deterministic ID from name hash
        return abs(crc32($name)) % 10000 + 1;
    }
}
