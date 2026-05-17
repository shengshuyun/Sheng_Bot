<?php
declare(strict_types=1);

class DatabaseConnectionPool
{
    private static $instance = null;
    private $pool = [];
    private $maxPoolSize = 10;
    private $minPoolSize = 2;
    private $poolTimeout = 5;
    private $connectionTimeout = 30;
    private $dbPath;
    
    private function __construct(string $dbPath)
    {
        $this->dbPath = $dbPath;
        $this->loadConfig();
        $this->initPool();
    }
    
    public static function getInstance(string $dbPath = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($dbPath ?? __DIR__ . '/../数据/sheng_bot.db');
        }
        return self::$instance;
    }
    
    private function loadConfig(): void
    {
        try {
            $pdo = new PDO('sqlite:' . $this->dbPath);
            $stmt = $pdo->query("SELECT config_key, config_value FROM config WHERE config_key LIKE 'db_pool_%'");
            $configs = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $configs[$row['config_key']] = json_decode($row['config_value'], true);
            }
            
            $this->maxPoolSize = $configs['db_pool_max_size'] ?? 10;
            $this->minPoolSize = $configs['db_pool_min_size'] ?? 2;
            $this->poolTimeout = $configs['db_pool_timeout'] ?? 5;
            $this->connectionTimeout = $configs['db_pool_conn_timeout'] ?? 30;
        } catch (Throwable $e) {
            // 使用默认配置
        }
    }
    
    private function initPool(): void
    {
        for ($i = 0; $i < $this->minPoolSize; $i++) {
            $this->pool[] = $this->createConnection();
        }
    }
    
    private function createConnection(): array
    {
        $pdo = new PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        return [
            'pdo' => $pdo,
            'created' => time(),
            'last_used' => time(),
            'in_use' => false
        ];
    }
    
    public function getConnection(): PDO
    {
        $startTime = microtime(true);
        
        while (microtime(true) - $startTime < $this->poolTimeout) {
            foreach ($this->pool as &$conn) {
                if (!$conn['in_use']) {
                    $conn['in_use'] = true;
                    $conn['last_used'] = time();
                    return $conn['pdo'];
                }
            }
            
            if (count($this->pool) < $this->maxPoolSize) {
                $newConn = $this->createConnection();
                $newConn['in_use'] = true;
                $this->pool[] = $newConn;
                return $newConn['pdo'];
            }
            
            usleep(10000);
        }
        
        throw new RuntimeException('Database connection pool timeout');
    }
    
    public function releaseConnection(PDO $pdo): void
    {
        foreach ($this->pool as &$conn) {
            if ($conn['pdo'] === $pdo) {
                $conn['in_use'] = false;
                $conn['last_used'] = time();
                break;
            }
        }
    }
    
    public function getStats(): array
    {
        $active = 0;
        $idle = 0;
        
        foreach ($this->pool as $conn) {
            if ($conn['in_use']) {
                $active++;
            } else {
                $idle++;
            }
        }
        
        return [
            'total' => count($this->pool),
            'active' => $active,
            'idle' => $idle,
            'max_size' => $this->maxPoolSize,
            'min_size' => $this->minPoolSize
        ];
    }
    
    public function close(): void
    {
        $this->pool = [];
    }
}

class QueryCache
{
    private static $instance = null;
    private $cache = [];
    private $ttl = 300;
    private $maxCacheSize = 1000;
    private $enabled = true;
    
    private function __construct()
    {
        $this->loadConfig();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadConfig(): void
    {
        try {
            $db = new SQLiteDatabase();
            $this->ttl = $db->getConfig('query_cache_ttl', 300);
            $this->maxCacheSize = $db->getConfig('query_cache_max_size', 1000);
            $this->enabled = (bool)$db->getConfig('query_cache_enabled', true);
        } catch (Throwable $e) {
            // 使用默认配置
        }
    }
    
    private function getCacheKey(string $sql, array $params = []): string
    {
        return md5($sql . json_encode($params));
    }
    
    public function get(string $sql, array $params = [])
    {
        if (!$this->enabled) {
            return null;
        }
        
        $key = $this->getCacheKey($sql, $params);
        
        if (isset($this->cache[$key])) {
            $item = $this->cache[$key];
            if (time() - $item['time'] < $this->ttl) {
                return $item['data'];
            }
            unset($this->cache[$key]);
        }
        
        return null;
    }
    
    public function set(string $sql, array $params, $data): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $key = $this->getCacheKey($sql, $params);
        
        if (count($this->cache) >= $this->maxCacheSize) {
            $this->cleanOldCache();
        }
        
        $this->cache[$key] = [
            'data' => $data,
            'time' => time()
        ];
    }
    
    private function cleanOldCache(): void
    {
        $limit = (int)($this->maxCacheSize * 0.7);
        $keys = array_keys($this->cache);
        usort($keys, function ($a, $b) {
            return $this->cache[$a]['time'] <=> $this->cache[$b]['time'];
        });
        
        while (count($this->cache) > $limit && !empty($keys)) {
            $key = array_shift($keys);
            unset($this->cache[$key]);
        }
    }
    
    public function invalidate(string $pattern = null): void
    {
        if ($pattern === null) {
            $this->cache = [];
        } else {
            foreach (array_keys($this->cache) as $key) {
                if (strpos($key, $pattern) !== false) {
                    unset($this->cache[$key]);
                }
            }
        }
    }
    
    public function getStats(): array
    {
        return [
            'enabled' => $this->enabled,
            'count' => count($this->cache),
            'max_size' => $this->maxCacheSize,
            'ttl' => $this->ttl
        ];
    }
}
