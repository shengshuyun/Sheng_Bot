<?php
declare(strict_types=1);

require_once __DIR__ . '/../函数库/DatabaseConnectionPool.php';
require_once __DIR__ . '/../函数库/QueryCache.php';

class SQLiteDatabase
{
    private string $dbPath;
    private DatabaseConnectionPool $pool;
    private QueryCache $cache;
    private array $config;
    
    public function __construct(string $dbPath = null)
    {
        $this->dbPath = $dbPath ?? __DIR__ . '/../数据/sheng_bot.db';
        $this->loadConfig();
        $this->initDatabase();
    }
    
    private function loadConfig(): void
    {
        $this->config = [
            'db_pool_max_size' => 10,
            'db_pool_min_size' => 2,
            'db_pool_timeout' => 5,
            'query_cache_enabled' => true,
            'query_cache_ttl' => 300,
            'query_cache_max_size' => 1000
        ];
        
        $dbDir = dirname($this->dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        
        $tempPdo = new PDO(
            'sqlite:' . $this->dbPath,
            null,
            null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        try {
            $stmt = $tempPdo->query("SELECT config_key, config_value FROM config");
            while ($row = $stmt->fetch()) {
                $value = json_decode($row['config_value'], true);
                // 确保类型正确
                if (in_array($row['config_key'], ['db_pool_max_size', 'db_pool_min_size', 'db_pool_timeout', 'query_cache_ttl', 'query_cache_max_size'])) {
                    $this->config[$row['config_key']] = (int)$value;
                } elseif ($row['config_key'] === 'query_cache_enabled') {
                    $this->config[$row['config_key']] = (bool)$value;
                } else {
                    $this->config[$row['config_key']] = $value;
                }
            }
        } catch (Exception $e) {
        }
        
        $this->pool = DatabaseConnectionPool::getInstance($this->dbPath, $this->config);
        $this->pool->init();
        
        $this->cache = QueryCache::getInstance($this->config);
    }
    
    public function updateConfig(array $newConfig): void
    {
        foreach ($newConfig as $key => $value) {
            $this->config[$key] = $value;
        }
        $this->cache->updateConfig($this->config);
    }
    
    private function initDatabase(): void
    {
        $pdo = $this->getConnection();
        $this->createTables($pdo);
        $this->releaseConnection($pdo);
    }
    
    public function getConnection(): PDO
    {
        return $this->pool->getConnection();
    }
    
    public function releaseConnection(PDO $connection): void
    {
        $this->pool->releaseConnection($connection);
    }
    
    public function getPoolStats(): array
    {
        return $this->pool->getStats();
    }
    
    public function getCacheStats(): array
    {
        return $this->cache->getStats();
    }

    private function createTables(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                config_key TEXT UNIQUE NOT NULL,
                config_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_config_key ON config(config_key)");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admins_username ON admins(username)");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS qq_bots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                appid TEXT NOT NULL,
                secret TEXT NOT NULL,
                sandbox INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_qq_bots_appid ON qq_bots(appid)");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS napcat_bots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                qq TEXT NOT NULL,
                http_url TEXT NOT NULL,
                token TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_napcat_qq ON napcat_bots(qq)");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS message_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                bot_type TEXT NOT NULL,
                bot_id TEXT NOT NULL,
                user_id TEXT,
                group_id TEXT,
                message_type TEXT,
                content TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_msg_bot_type ON message_logs(bot_type)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_msg_created ON message_logs(created_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_msg_bot_id ON message_logs(bot_id)");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS kv_store (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_key TEXT UNIQUE NOT NULL,
                store_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_kv_key ON kv_store(store_key)");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                level TEXT NOT NULL,
                message TEXT NOT NULL,
                context TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sys_log_level ON system_logs(level)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sys_log_created ON system_logs(created_at)");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT UNIQUE NOT NULL,
                session_data TEXT NOT NULL,
                expire_at INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_session_id ON sessions(session_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_session_expire ON sessions(expire_at)");
    }
    
    public function setConfig(string $key, mixed $value): bool
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO config (config_key, config_value, updated_at)
            VALUES (?, ?, datetime('now'))
        ");
        $result = $stmt->execute([$key, json_encode($value)]);
        $this->releaseConnection($pdo);
        
        $this->cache->delete('config_' . $key);
        $this->cache->delete('all_configs');
        
        return $result;
    }
    
    public function getConfig(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'config_' . $key;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("SELECT config_value FROM config WHERE config_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $this->releaseConnection($pdo);
        
        $value = $row ? json_decode($row['config_value'], true) : $default;
        $this->cache->set($cacheKey, $value);
        
        return $value;
    }
    
    public function getAllConfigs(): array
    {
        $cacheKey = 'all_configs';
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $pdo = $this->getConnection();
        $stmt = $pdo->query("SELECT config_key, config_value FROM config");
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['config_key']] = json_decode($row['config_value'], true);
        }
        $this->releaseConnection($pdo);
        
        $this->cache->set($cacheKey, $result);
        return $result;
    }
    
    public function setKV(string $key, mixed $value): bool
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO kv_store (store_key, store_value, updated_at)
            VALUES (?, ?, datetime('now'))
        ");
        $result = $stmt->execute([$key, json_encode($value)]);
        $this->releaseConnection($pdo);
        
        $this->cache->delete('kv_' . $key);
        
        return $result;
    }
    
    public function getKV(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'kv_' . $key;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("SELECT store_value FROM kv_store WHERE store_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $this->releaseConnection($pdo);
        
        $value = $row ? json_decode($row['store_value'], true) : $default;
        $this->cache->set($cacheKey, $value);
        
        return $value;
    }
    
    public function deleteKV(string $key): bool
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("DELETE FROM kv_store WHERE store_key = ?");
        $result = $stmt->execute([$key]);
        $this->releaseConnection($pdo);
        
        $this->cache->delete('kv_' . $key);
        
        return $result;
    }
    
    public function isInstalled(): bool
    {
        $installed = $this->getConfig('installed', false);
        if ($installed) {
            return true;
        }
        
        $pdo = $this->getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
        $count = $stmt->fetchColumn();
        $this->releaseConnection($pdo);
        
        if ($count > 0) {
            $this->setConfig('installed', true);
            return true;
        }
        
        return false;
    }
    
    public function lockInstall(): void
    {
        $this->setConfig('installed', true);
    }
    
    public function unlockInstall(): void
    {
        $this->setConfig('installed', false);
    }
    
    public function getMessageLogs(int $limit = 100, string $botType = null): array
    {
        $cacheKey = 'msg_logs_' . $botType . '_' . $limit;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $pdo = $this->getConnection();
        
        if ($botType) {
            $stmt = $pdo->prepare("SELECT * FROM message_logs WHERE bot_type = ? ORDER BY id DESC LIMIT ?");
            $stmt->execute([$botType, $limit]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM message_logs ORDER BY id DESC LIMIT ?");
            $stmt->execute([$limit]);
        }
        
        $result = $stmt->fetchAll();
        $this->releaseConnection($pdo);
        
        $this->cache->set($cacheKey, $result);
        return $result;
    }
    
    public function getSystemLogs(int $limit = 100, string $level = null): array
    {
        $cacheKey = 'sys_logs_' . $level . '_' . $limit;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $pdo = $this->getConnection();
        
        if ($level) {
            $stmt = $pdo->prepare("SELECT * FROM system_logs WHERE level = ? ORDER BY id DESC LIMIT ?");
            $stmt->execute([$level, $limit]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM system_logs ORDER BY id DESC LIMIT ?");
            $stmt->execute([$limit]);
        }
        
        $result = $stmt->fetchAll();
        $this->releaseConnection($pdo);
        
        $this->cache->set($cacheKey, $result);
        return $result;
    }
    
    public function addMessageLog(string $botType, string $botId, string $userId = null, string $groupId = null, string $messageType = null, string $content = null): bool
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("INSERT INTO message_logs (bot_type, bot_id, user_id, group_id, message_type, content) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$botType, $botId, $userId, $groupId, $messageType, $content]);
        $this->releaseConnection($pdo);
        
        $this->cache->clear();
        
        return $result;
    }
    
    public function addSystemLog(string $level, string $message, array $context = []): bool
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("INSERT INTO system_logs (level, message, context) VALUES (?, ?, ?)");
        $result = $stmt->execute([$level, $message, !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : null]);
        $this->releaseConnection($pdo);
        
        $this->cache->clear();
        
        return $result;
    }
    
    public function setSession(string $sessionId, array $data, int $ttl = 3600): bool
    {
        $pdo = $this->getConnection();
        $expireAt = time() + $ttl;
        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO sessions (session_id, session_data, expire_at, updated_at)
            VALUES (?, ?, ?, datetime('now'))
        ");
        $result = $stmt->execute([$sessionId, json_encode($data), $expireAt]);
        $this->releaseConnection($pdo);
        
        return $result;
    }
    
    public function getSession(string $sessionId): ?array
    {
        $pdo = $this->getConnection();
        $this->cleanExpiredSessions($pdo);
        $stmt = $pdo->prepare("SELECT session_data FROM sessions WHERE session_id = ? AND expire_at > ?");
        $stmt->execute([$sessionId, time()]);
        $row = $stmt->fetch();
        $this->releaseConnection($pdo);
        
        return $row ? json_decode($row['session_data'], true) : null;
    }
    
    public function deleteSession(string $sessionId): bool
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE session_id = ?");
        $result = $stmt->execute([$sessionId]);
        $this->releaseConnection($pdo);
        
        return $result;
    }
    
    private function cleanExpiredSessions(?PDO $pdo = null): void
    {
        $needRelease = false;
        if ($pdo === null) {
            $pdo = $this->getConnection();
            $needRelease = true;
        }
        
        $pdo->exec("DELETE FROM sessions WHERE expire_at <= " . time());
        
        if ($needRelease) {
            $this->releaseConnection($pdo);
        }
    }
}
