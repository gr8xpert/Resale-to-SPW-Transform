<?php

namespace SpwTransform;

/**
 * Client for Resales Online WebAPI V6.
 *
 * Correct endpoint names (from documentation):
 * - SearchProperties
 * - SearchPropertyTypes
 * - SearchLocations
 * - SearchFeatures
 * - PropertyDetails
 */
class ResalesClient
{
    private string $baseUrl;
    private Cache $cache;
    private array $cacheConfig;

    // Resales credentials (set per request)
    private string $clientId = '';
    private string $apiKey = '';
    private string $filterId = '1';

    public function __construct(array $config, Cache $cache)
    {
        $this->baseUrl = rtrim($config['resales_api_url'], '/');
        $this->cache = $cache;
        $this->cacheConfig = $config['cache']['ttl'] ?? [];
    }

    /**
     * Set credentials for the current request.
     */
    public function setCredentials(string $clientId, string $apiKey, string $filterId = '1'): self
    {
        $this->clientId = $clientId;
        $this->apiKey = $apiKey;
        $this->filterId = $filterId;
        return $this;
    }

    /**
     * Override the filter ID (useful for different listing types with separate filters).
     */
    public function setFilterId(string $filterId): self
    {
        $this->filterId = $filterId;
        return $this;
    }

    /**
     * Get property types.
     */
    public function getPropertyTypes(): ?array
    {
        $cacheKey = Cache::makeKey('property_types', [
            'client_id' => $this->clientId,
            'filter_id' => $this->filterId,
        ]);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $response = $this->request('SearchPropertyTypes');

        if ($response && isset($response['PropertyTypes'])) {
            $ttl = $this->cacheConfig['property_types'] ?? 86400;
            $this->cache->set($cacheKey, $response, $ttl);
        }

        return $response;
    }

    /**
     * Get locations.
     */
    public function getLocations(): ?array
    {
        $cacheKey = Cache::makeKey('locations', [
            'client_id' => $this->clientId,
            'filter_id' => $this->filterId,
        ]);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $response = $this->request('SearchLocations');

        if ($response && isset($response['LocationData'])) {
            $ttl = $this->cacheConfig['locations'] ?? 86400;
            $this->cache->set($cacheKey, $response, $ttl);
        }

        return $response;
    }

    /**
     * Get features.
     */
    public function getFeatures(): ?array
    {
        $cacheKey = Cache::makeKey('features', [
            'client_id' => $this->clientId,
            'filter_id' => $this->filterId,
        ]);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $response = $this->request('SearchFeatures');

        if ($response && isset($response['FeaturesData'])) {
            $ttl = $this->cacheConfig['features'] ?? 86400;
            $this->cache->set($cacheKey, $response, $ttl);
        }

        return $response;
    }

    /**
     * Search properties.
     */
    public function searchProperties(array $params = []): ?array
    {
        $cacheKey = Cache::makeKey('search', array_merge([
            'client_id' => $this->clientId,
            'filter_id' => $this->filterId,
        ], $params));

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $response = $this->request('SearchProperties', $params);

        if ($response) {
            $ttl = $this->cacheConfig['properties'] ?? 300;
            $this->cache->set($cacheKey, $response, $ttl);
        }

        return $response;
    }

    /**
     * Get property details.
     */
    public function getPropertyDetails(string $reference, string $language = 'EN'): ?array
    {
        $cacheKey = Cache::makeKey('property', [
            'client_id' => $this->clientId,
            'filter_id' => $this->filterId,
            'ref' => $reference,
            'lang' => $language,
        ]);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $response = $this->request('PropertyDetails', [
            'P_RefId' => $reference,
            'P_Lang' => $this->mapLanguageCode($language),
            'P_VirtualTours' => '2',  // 1=virtual tours only, 2=virtual tours + video tours
            'P_Dimension' => '1',
        ]);

        if ($response) {
            $ttl = $this->cacheConfig['property_detail'] ?? 3600;
            $this->cache->set($cacheKey, $response, $ttl);
        }

        return $response;
    }

    /**
     * Make a request to Resales Online API.
     */
    private function request(string $endpoint, array $params = []): ?array
    {
        if (!$this->clientId || !$this->apiKey) {
            throw new \RuntimeException('Resales credentials not set');
        }

        $url = $this->baseUrl . '/' . $endpoint . '?' . http_build_query(array_merge([
            'p_agency_filterid' => $this->filterId,
            'p1' => $this->clientId,
            'p2' => $this->apiKey,
        ], $params));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: SPW-Transform/1.0',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Map widget language codes to Resales language codes.
     */
    private function mapLanguageCode(string $language): string
    {
        $map = [
            'en_US' => '1',  // English
            'en_GB' => '1',
            'es_ES' => '2',  // Spanish
            'de_DE' => '4',  // German
            'fr_FR' => '5',  // French
            'nl_NL' => '3',  // Dutch
            'ru_RU' => '6',  // Russian
            'sv_SE' => '8',  // Swedish
            'da_DK' => '9',  // Danish
            'no_NO' => '10', // Norwegian
            'fi_FI' => '11', // Finnish
            'pl_PL' => '12', // Polish
            'pt_PT' => '7',  // Portuguese
        ];

        return $map[$language] ?? '1';
    }
}
