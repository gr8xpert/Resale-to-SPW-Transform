<?php

namespace SpwTransform;

/**
 * Simple file-based caching for the microservice.
 */
class Cache
{
    private string $directory;
    private bool $enabled;

    public function __construct(array $config)
    {
        $this->directory = rtrim($config['directory'] ?? __DIR__ . '/../cache', '/');
        $this->enabled = $config['enabled'] ?? true;

        if ($this->enabled && !is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    /**
     * Get a cached value.
     *
     * @return mixed|null Returns null if not found or expired
     */
    public function get(string $key)
    {
        if (!$this->enabled) {
            return null;
        }

        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);

        if (!$data || !isset($data['expires_at']) || !isset($data['value'])) {
            return null;
        }

        // Check expiration
        if ($data['expires_at'] < time()) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    /**
     * Set a cached value.
     */
    public function set(string $key, $value, int $ttl = 3600): void
    {
        if (!$this->enabled) {
            return;
        }

        $file = $this->getFilePath($key);
        $data = [
            'expires_at' => time() + $ttl,
            'value' => $value,
        ];

        file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Delete a cached value.
     */
    public function delete(string $key): void
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Clear all cache.
     */
    public function clear(): void
    {
        $files = glob($this->directory . '/*.json');

        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Generate cache key from parameters.
     */
    public static function makeKey(string $prefix, array $params = []): string
    {
        $normalized = $params;
        ksort($normalized);
        return $prefix . '_' . md5(json_encode($normalized));
    }

    /**
     * Get the file path for a cache key.
     */
    private function getFilePath(string $key): string
    {
        // Sanitize key for filesystem
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->directory . '/' . $safeKey . '.json';
    }
}
