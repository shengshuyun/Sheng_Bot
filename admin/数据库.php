<?php
declare(strict_types=1);

class SQLiteDatabase
{
    private static ?PDO $instance = null;
    private string $dbPath;

    public function __construct(string $dbPath = __DIR__ . '/../数据/sheng_bot.db')
    {
        $this->dbPath = $dbPath;
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
        
        // 创建表结构
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

        // 管理员表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

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

        // KV存储表（替代ini数据库的存储）
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS kv_store (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_key TEXT UNIQUE NOT NULL,
                store_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function setConfig(string $key, mixed $value): bool
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO config (config_key, config_value, updated_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
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
            VALUES (?, ?, CURRENT_TIMESTAMP)
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
        $pdo = $this->getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
        return $stmt->fetchColumn() > 0;
    }
}
