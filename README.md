# SPW Transform - Resales Online to Smart Property Widget

A PHP microservice that transforms Resales Online WebAPI V6 data into the format expected by the Smart Property Widget.

## Overview

This microservice acts as a bridge between Resales Online API and the Smart Property Widget, providing:
- Data transformation from Resales format to Widget format
- Location hierarchy with municipality groupings
- Multi-filter support for different property segments
- Caching for improved performance
- Labels management integration

## Architecture

```
┌─────────────────────┐     ┌─────────────────────┐     ┌─────────────────────┐
│   Smart Property    │────▶│    SPW Transform    │────▶│   Resales Online    │
│      Widget         │     │   (This Service)    │     │     WebAPI V6       │
└─────────────────────┘     └─────────────────────┘     └─────────────────────┘
                                      │
                                      ▼
                            ┌─────────────────────┐
                            │   Laravel App       │
                            │ (Credentials/Labels)│
                            └─────────────────────┘
```

## Working Endpoints

| Endpoint | Description | Example |
|----------|-------------|---------|
| `/v2/location` | Location hierarchy (Area → Municipality → City) | https://api.smartpropertywidget.com/v2/location?_domain=bestinspain.net |
| `/v1/property` | Property search/listing | https://api.smartpropertywidget.com/v1/property?_domain=bestinspain.net |
| `/v1/property/{ref}` | Single property details | https://api.smartpropertywidget.com/v1/property/BISR5061460?_domain=bestinspain.net |
| `/v1/property_types` | Property types hierarchy | https://api.smartpropertywidget.com/v1/property_types?_domain=bestinspain.net |
| `/v1/property_features` | Property features/amenities | https://api.smartpropertywidget.com/v1/property_features?_domain=bestinspain.net |
| `/v1/plugin_labels` | UI labels (multilingual) | https://api.smartpropertywidget.com/v1/plugin_labels?_domain=bestinspain.net |

## Resales Online Filters

Resales Online supports multiple filters (p_agency_filterid) for different property segments:

| Filter ID | Use Case | Description |
|-----------|----------|-------------|
| Filter 1 | All properties | Main website - shows all available properties |
| Filter 2 | Luxury properties | Separate luxury site - high-end properties only |
| Filter 3 | Rentals only | Rental portal - long/short term rentals |
| Filter 4 | Own listings | Internal use - only agency's own properties |

Configure filters per client in the Laravel admin panel under "Resales Settings".

---

## Municipality Mappings

The Resales Online `SearchLocations` API returns a flat list of cities without municipality groupings. To create a proper 3-level hierarchy (Area → Municipality → City), we use static JSON mapping files.

### Location Hierarchy Example

**Without mapping (flat):**
```
Costa del Sol
├── Marbella
├── Puerto Banús
├── San Pedro de Alcántara
├── Estepona
├── Benahavís
└── ... (all cities at same level)
```

**With mapping (hierarchical):**
```
Costa del Sol
├── Marbella (Municipality)
│   ├── Marbella
│   ├── Puerto Banús
│   ├── San Pedro de Alcántara
│   └── Nueva Andalucía
├── Estepona (Municipality)
│   ├── Estepona
│   ├── Cancelada
│   └── New Golden Mile
├── Benahavís (Municipality)
│   ├── Benahavís
│   ├── La Quinta
│   └── La Zagaleta
└── ...
```

---

## Adding New Region Mappings

### Step 1: Create the JSON File

Create a new file in the `/mappings/` directory. The filename should be the slugified version of the area name:

| Area Name | Filename |
|-----------|----------|
| Costa del Sol | `costa-del-sol.json` |
| Costa Blanca | `costa-blanca.json` |
| Mallorca | `mallorca.json` |
| Canary Islands | `canary-islands.json` |
| Costa Brava | `costa-brava.json` |

### Step 2: JSON Structure

```json
{
    "_comment": "Description of the region",
    "_area": "Area Name (must match Resales API exactly)",

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

### Step 3: Complete Example - Costa Blanca

Create file: `/mappings/costa-blanca.json`

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
            "Mutxamel",
            "San Juan de Alicante"
        ]
    },

    "Benidorm": {
        "municipality": "Benidorm",
        "cities": [
            "Benidorm",
            "Finestrat",
            "La Nucia",
            "Alfaz del Pi",
            "Altea"
        ]
    },

    "Torrevieja": {
        "municipality": "Torrevieja",
        "cities": [
            "Torrevieja",
            "La Mata",
            "Los Balcones",
            "Punta Prima",
            "Orihuela Costa"
        ]
    },

    "Javea": {
        "municipality": "Jávea/Xàbia",
        "cities": [
            "Javea",
            "Jávea",
            "Xàbia",
            "Benitachell",
            "Moraira"
        ]
    },

    "Denia": {
        "municipality": "Dénia",
        "cities": [
            "Denia",
            "Dénia",
            "Las Marinas",
            "Las Rotas"
        ]
    },

    "Calpe": {
        "municipality": "Calpe",
        "cities": [
            "Calpe",
            "Calp",
            "Benissa"
        ]
    }
}
```

### Step 4: Deploy and Test

1. **Upload the JSON file** to `/mappings/` on the server

2. **Clear the cache:**
   ```bash
   rm -f /path/to/spw-transform/cache/*.json
   ```

3. **Test the endpoint:**
   ```
   https://api.smartpropertywidget.com/v2/location?_domain=YOUR_CLIENT_DOMAIN
   ```

---

## Important Notes

### City Names Must Match Exactly

The city names in the mapping file **must match exactly** what the Resales API returns:

| Correct ✓ | Incorrect ✗ |
|-----------|-------------|
| `Puerto Banús` | `Puerto Banus` |
| `Benahavís` | `Benahavis` |
| `San Pedro de Alcántara` | `San Pedro de Alcantara` |
| `Jávea` | `Javea` (add both versions if needed) |

### Finding City Names

To see what city names the Resales API returns for a client:

```bash
curl "https://api.smartpropertywidget.com/v2/location?_domain=CLIENT_DOMAIN"
```

Or check the debug endpoint (if enabled):
```
https://api.smartpropertywidget.com/debug-locations-raw.php?key=spw-debug-xyz123&domain=CLIENT_DOMAIN
```

### Unmapped Cities

Cities not included in any municipality mapping will appear directly under the Area level. This is intentional - not all small towns need municipality grouping.

### Multiple Spellings

If a city appears with different spellings in the API, add all variations:

```json
{
    "Jávea/Xàbia": {
        "municipality": "Jávea/Xàbia",
        "cities": [
            "Javea",
            "Jávea",
            "Xàbia",
            "Xabia"
        ]
    }
}
```

---

## Project Structure

```
spw-transform/
├── index.php                 # Main router
├── config/
│   ├── config.php            # Configuration
│   └── config.local.example.php
├── endpoints/
│   ├── location.php          # /v2/location
│   ├── property.php          # /v1/property
│   ├── property-detail.php   # /v1/property/{ref}
│   ├── property-types.php    # /v1/property_types
│   ├── features.php          # /v1/property_features
│   └── labels.php            # /v1/plugin_labels
├── src/
│   ├── Cache.php             # File-based caching
│   ├── ResalesClient.php     # Resales Online API client
│   ├── LaravelClient.php     # Laravel app integration
│   ├── LocationTransformer.php    # Location hierarchy builder
│   ├── PropertyTransformer.php    # Property data transformer
│   ├── TypeTransformer.php        # Property types transformer
│   ├── FeatureTransformer.php     # Features transformer
│   ├── MappingLoader.php          # Loads JSON mappings
│   └── MunicipalityExtractor.php  # Municipality extraction
├── mappings/
│   └── costa-del-sol.json    # Costa del Sol municipalities
├── cache/                    # Cached responses (gitignored)
└── docs/
    └── MUNICIPALITY_MAPPINGS.md  # Detailed mapping guide
```

---

## Caching

| Data Type | Cache TTL | Notes |
|-----------|-----------|-------|
| Locations | 24 hours | Includes municipality hierarchy |
| Property Types | 24 hours | Rarely changes |
| Features | 24 hours | Rarely changes |
| Property Search | 5 minutes | Frequently updated |
| Property Details | 1 hour | Balance freshness/performance |
| Labels | 1 hour | Updated via Laravel admin |

### Clear Cache

Delete all files in `/cache/` directory to force fresh data fetch.

---

## Configuration

Copy `config/config.local.example.php` to `config/config.local.php` and configure:

```php
return [
    'laravel_api_url' => 'https://your-laravel-app.com',
    'laravel_internal_key' => 'your-internal-api-key',
    'debug' => false,
];
```

---

## Requirements

- PHP 7.4+
- cURL extension
- JSON extension
- Write access to `/cache/` directory

---

## License

Proprietary - Smart Property Widget
