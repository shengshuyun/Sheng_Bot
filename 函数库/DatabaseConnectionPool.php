<?php
declare(strict_types=1);

class DatabaseConnectionPool
{
    private static ?self $instance = null;
    private array $pool = [];
    private array $inUse = [];
    private int $minSize;
    private int $maxSize;
    private float $timeout;
    private string $dbPath;
    private bool $initialized = false;
    
    private function __construct(string $dbPath, array $config)
    {
        $this->dbPath = $dbPath;
        $this->minSize = $config['db_pool_min_size'] ?? 2;
        $this->maxSize = $config['db_pool_max_size'] ?? 10;
        $this->timeout = $config['db_pool_timeout'] ?? 5.0;
    }
    
    public static function getInstance(string $dbPath, array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($dbPath, $config);
        }
        return self::$instance;
    }
    
    public function init(): void
    {
        if ($this->initialized) {
            return;
        }
        
        for ($i = 0; $i < $this->minSize; $i++) {
            $this->pool[] = $this->createConnection();
        }
        
        $this->initialized = true;
    }
    
    private function createConnection(): PDO
    {
        $pdo = new PDO(
            'sqlite:' . $this->dbPath,
            null,
            null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    }
    
    public function getConnection(): PDO
    {
        $startTime = microtime(true);
        
        while (true) {
            if (!empty($this->pool)) {
                $connection = array_pop($this->pool);
                $this->inUse[spl_object_hash($connection)] = $connection;
                return $connection;
            }
            
            if (count($this->inUse) < $this->maxSize) {
                $connection = $this->createConnection();
                $this->inUse[spl_object_hash($connection)] = $connection;
                return $connection;
            }
            
            if (microtime(true) - $startTime > $this->timeout) {
                throw new RuntimeException('Database connection pool timeout');
            }
            
            usleep(10000); // 10ms
        }
    }
    
    public function releaseConnection(PDO $connection): void
    {
        $hash = spl_object_hash($connection);
        if (isset($this->inUse[$hash])) {
            unset($this->inUse[$hash]);
            
            if (count($this->pool) < $this->maxSize) {
                $this->pool[] = $connection;
            }
        }
    }
    
    public function getStats(): array
    {
        return [
            'pool_size' => count($this->pool),
            'in_use' => count($this->inUse),
            'min_size' => $this->minSize,
            'max_size' => $this->maxSize
        ];
    }
    
    public function closeAll(): void
    {
        $this->pool = [];
        $this->inUse = [];
        $this->initialized = false;
    }
}
