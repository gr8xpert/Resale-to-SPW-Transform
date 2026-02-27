# Municipality Mappings Guide

This document explains how to add location hierarchy mappings for new regions.

## Overview

The Resales Online API `SearchLocations` endpoint returns a flat list of cities without municipality groupings. To create a 3-level hierarchy (Area → Municipality → City), we use static JSON mapping files.

## How It Works

```
Request Flow:
┌─────────────────────────┐
│ 1. Fetch locations from │
│    Resales API          │
└───────────┬─────────────┘
            ↓
┌─────────────────────────┐
│ 2. Check for static     │
│    mapping file         │
│    (e.g., costa-del-sol)│
└───────────┬─────────────┘
            ↓
┌─────────────────────────┐
│ 3. Group cities by      │
│    municipality         │
└───────────┬─────────────┘
            ↓
┌─────────────────────────┐
│ 4. Return 3-level       │
│    hierarchy            │
└─────────────────────────┘
```

## Adding a New Region Mapping

### Step 1: Create the JSON File

Create a new file in the `/mappings/` directory. The filename should be the slugified version of the area name:

| Area Name | Filename |
|-----------|----------|
| Costa del Sol | `costa-del-sol.json` |
| Costa Blanca | `costa-blanca.json` |
| Mallorca | `mallorca.json` |
| Canary Islands | `canary-islands.json` |

### Step 2: JSON Structure

```json
{
    "_comment": "Region description - for documentation only",
    "_area": "Costa Blanca",

    "Municipality Name": {
        "municipality": "Municipality Name",
        "cities": [
            "City 1",
            "City 2",
            "City 3"
        ]
    },

    "Another Municipality": {
        "municipality": "Another Municipality",
        "cities": [
            "City A",
            "City B"
        ]
    }
}
```

### Step 3: Example - Costa Blanca

```json
{
    "_comment": "Costa Blanca municipality mappings",
    "_area": "Costa Blanca",

    "Alicante": {
        "municipality": "Alicante",
        "cities": [
            "Alicante",
            "Playa de San Juan",
            "El Campello",
            "Mutxamel"
        ]
    },

    "Benidorm": {
        "municipality": "Benidorm",
        "cities": [
            "Benidorm",
            "Finestrat",
            "La Nucia",
            "Alfaz del Pi"
        ]
    },

    "Torrevieja": {
        "municipality": "Torrevieja",
        "cities": [
            "Torrevieja",
            "La Mata",
            "Los Balcones",
            "Punta Prima"
        ]
    }
}
```

### Step 4: Upload and Test

1. Upload the new JSON file to `/mappings/` on the server
2. Clear the cache: Delete all files in `/cache/` folder
3. Test the endpoint:
   ```
   https://api.smartpropertywidget.com/v2/location?_domain=YOUR_CLIENT_DOMAIN
   ```

## Important Notes

### City Names Must Match Exactly

The city names in the mapping file must match exactly what the Resales API returns:
- Case-sensitive: "Puerto Banús" not "Puerto Banus"
- Accents matter: "Benahavís" not "Benahavis"
- Spaces matter: "San Pedro de Alcántara" not "SanPedro de Alcantara"

### Unmapped Cities

Cities not included in any mapping will appear directly under the Area level. This is intentional - not all cities need to be grouped into municipalities.

### Multiple Areas

If a client has properties in multiple areas (e.g., both Costa del Sol and Costa Blanca), create separate mapping files for each. The system will automatically load the appropriate mapping based on the area name from the Resales API.

## File Locations

```
spw-transform/
├── mappings/
│   ├── costa-del-sol.json    # Costa del Sol mappings
│   ├── costa-blanca.json     # Costa Blanca mappings (create as needed)
│   └── mallorca.json         # Mallorca mappings (create as needed)
├── src/
│   ├── MappingLoader.php     # Loads mapping files
│   ├── MunicipalityExtractor.php  # Manages municipality extraction
│   └── LocationTransformer.php    # Transforms to widget format
```

## Troubleshooting

### Mapping Not Applied

1. Check filename matches area name (slugified, lowercase, hyphens)
2. Verify JSON is valid (use a JSON validator)
3. Clear the cache folder
4. Check city names match Resales API exactly

### Some Cities Not Grouped

1. Check if the city name is spelled exactly as in Resales API
2. Add missing cities to the appropriate municipality in the mapping file
3. Clear cache and test again

## Getting City Names from Resales API

To see what city names the Resales API returns for a specific client:

```
https://api.smartpropertywidget.com/v2/location?_domain=CLIENT_DOMAIN
```

Or use the debug endpoint (if available):
```
https://api.smartpropertywidget.com/debug-locations-raw.php?key=spw-debug-xyz123&domain=CLIENT_DOMAIN
```

## Support

For questions about municipality mappings, check:
1. The Resales API documentation for location data structure
2. The existing `costa-del-sol.json` as a reference
3. Spanish municipality official lists for correct groupings
