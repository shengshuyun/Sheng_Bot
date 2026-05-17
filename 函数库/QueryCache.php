<?php
declare(strict_types=1);

class QueryCache
{
    private static ?self $instance = null;
    private array $cache = [];
    private bool $enabled;
    private int $ttl;
    private int $maxSize;
    
    private function __construct(array $config)
    {
        $this->enabled = $config['query_cache_enabled'] ?? true;
        $this->ttl = $config['query_cache_ttl'] ?? 300;
        $this->maxSize = $config['query_cache_max_size'] ?? 1000;
    }
    
    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    public function get(string $key): mixed
    {
        if (!$this->enabled) {
            return null;
        }
        
        if (isset($this->cache[$key])) {
            $item = $this->cache[$key];
            if (time() < $item['expire']) {
                return $item['data'];
            }
            unset($this->cache[$key]);
        }
        
        return null;
    }
    
    public function set(string $key, mixed $data): void
    {
        if (!$this->enabled) {
            return;
        }
        
        while (count($this->cache) >= $this->maxSize) {
            array_shift($this->cache);
        }
        
        $this->cache[$key] = [
            'data' => $data,
            'expire' => time() + $this->ttl
        ];
    }
    
    public function delete(string $key): void
    {
        unset($this->cache[$key]);
    }
    
    public function clear(): void
    {
        $this->cache = [];
    }
    
    public function getStats(): array
    {
        return [
            'enabled' => $this->enabled,
            'size' => count($this->cache),
            'max_size' => $this->maxSize,
            'ttl' => $this->ttl
        ];
    }
    
    public function updateConfig(array $config): void
    {
        $this->enabled = $config['query_cache_enabled'] ?? $this->enabled;
        $this->ttl = $config['query_cache_ttl'] ?? $this->ttl;
        $this->maxSize = $config['query_cache_max_size'] ?? $this->maxSize;
    }
}
