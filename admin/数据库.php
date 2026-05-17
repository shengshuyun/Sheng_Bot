<?php
declare(strict_types=1);

class SQLiteDatabase
{
    private static ?PDO $instance = null;
    private string $dbPath;
    
    public function __construct(string $dbPath = null)
    {
        $this->dbPath = $dbPath ?? __DIR__ . '/../数据/sheng_bot.db';
        $this->initDatabase();
    }
    
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->getConnection();
    }
    
    private function initDatabase(): void
    {
        $dbDir = dirname($this->dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        $pdo = $this->getConnection();
        $this->createTables($pdo);
    }
    
    public function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new PDO(
                'sqlite:' . $this->dbPath,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        }
        return self::$instance;
    }

    private function createTables(PDO $pdo): void
    {
        // 配置表
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

        // 管理员表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admins_username ON admins(username)");

        // QQ机器人表
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

        // NapCat机器人表
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

        // 消息日志表
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

        // KV存储表
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

        // 系统日志表
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
    }
    
    public function setConfig(string $key, mixed $value): bool
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO config (config_key, config_value, updated_at)
            VALUES (?, ?, datetime('now'))
        ");
        return $stmt->execute([$key, json_encode($value)]);
    }
    
    public function getConfig(string $key, mixed $default = null): mixed
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("SELECT config_value FROM config WHERE config_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? json_decode($row['config_value'], true) : $default;
    }
    
    public function getAllConfigs(): array
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->query("SELECT config_key, config_value FROM config");
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['config_key']] = json_decode($row['config_value'], true);
        }
        return $result;
    }
    
    public function setKV(string $key, mixed $value): bool
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO kv_store (store_key, store_value, updated_at)
            VALUES (?, ?, datetime('now'))
        ");
        return $stmt->execute([$key, json_encode($value)]);
    }
    
    public function getKV(string $key, mixed $default = null): mixed
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("SELECT store_value FROM kv_store WHERE store_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? json_decode($row['store_value'], true) : $default;
    }
    
    public function deleteKV(string $key): bool
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("DELETE FROM kv_store WHERE store_key = ?");
        return $stmt->execute([$key]);
    }
    
    public function isInstalled(): bool
    {
        // 首先检查配置表中的 installed 标记
        $installed = $this->getConfig('installed', false);
        if ($installed) {
            return true;
        }
        
        // 兼容旧数据：如果有管理员但没有标记，创建标记
        $pdo = $this->getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
        if ($stmt->fetchColumn() > 0) {
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
}
