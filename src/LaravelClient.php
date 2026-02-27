<?php

namespace SpwTransform;

/**
 * Client for fetching data from the Laravel SmartPropertyWidget app.
 */
class LaravelClient
{
    private string $baseUrl;
    private string $apiKey;
    private Cache $cache;
    private array $cacheConfig;

    public function __construct(array $config, Cache $cache)
    {
        $this->baseUrl = rtrim($config['laravel_url'], '/');
        $this->apiKey = $config['internal_api_key'];
        $this->cache = $cache;
        $this->cacheConfig = $config['cache']['ttl'] ?? [];
    }

    /**
     * Get Resales credentials for a domain.
     */
    public function getResalesConfig(string $domain): ?array
    {
        $cacheKey = Cache::makeKey('resales_config', ['domain' => $domain]);

        // Check cache first (short TTL for credentials)
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $url = $this->baseUrl . '/api/internal/client-resales-config?' . http_build_query([
            'domain' => $domain,
        ]);

        $response = $this->request($url);

        if ($response && !isset($response['error'])) {
            // Cache for 5 minutes (credentials don't change often)
            $this->cache->set($cacheKey, $response, 300);
            return $response;
        }

        return null;
    }

    /**
     * Get merged labels for a domain and language.
     */
    public function getLabels(string $domain, string $language = 'en_US'): array
    {
        $cacheKey = Cache::makeKey('labels', ['domain' => $domain, 'language' => $language]);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $url = $this->baseUrl . '/api/internal/labels?' . http_build_query([
            'domain' => $domain,
            'language' => $language,
        ]);

        $response = $this->request($url);

        if ($response && isset($response['labels'])) {
            $ttl = $this->cacheConfig['labels'] ?? 86400;
            $this->cache->set($cacheKey, $response['labels'], $ttl);
            return $response['labels'];
        }

        return [];
    }

    /**
     * Get display preferences (visibility, ordering) for locations, types, or features.
     */
    public function getDisplayPreferences(string $domain, string $type): array
    {
        $cacheKey = Cache::makeKey('display_prefs', ['domain' => $domain, 'type' => $type]);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $url = $this->baseUrl . '/api/internal/display-preferences?' . http_build_query([
            'domain' => $domain,
            'type' => $type,
        ]);

        $response = $this->request($url);

        if ($response && !isset($response['error'])) {
            $ttl = $this->cacheConfig['display_prefs'] ?? 3600; // 1 hour default
            $this->cache->set($cacheKey, $response, $ttl);
            return $response;
        }

        return [
            'hidden_ids' => [],
            'sort_order' => [],
            'custom_names' => [],
        ];
    }

    /**
     * Make a request to the Laravel API.
     */
    private function request(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Accept: application/json',
                    'User-Agent: SPW-Transform/1.0',
                ],
                'timeout' => 10,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        return json_decode($response, true);
    }
}
