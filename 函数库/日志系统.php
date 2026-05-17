<?php
declare(strict_types=1);

class Logger
{
    const DEBUG = 'debug';
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';
    const CRITICAL = 'critical';
    
    private static $instance = null;
    private $logDir;
    private $logLevel;
    private $maxFileSize;
    private $maxFiles;
    private $logToDatabase;
    private $logToFile;
    
    private $levels = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::WARNING => 2,
        self::ERROR => 3,
        self::CRITICAL => 4
    ];
    
    private function __construct()
    {
        $this->logDir = __DIR__ . '/../日志';
        $this->loadConfig();
        $this->ensureLogDir();
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
            $this->logLevel = $db->getConfig('log_level', self::INFO);
            $this->maxFileSize = $db->getConfig('log_max_file_size', 10 * 1024 * 1024);
            $this->maxFiles = $db->getConfig('log_max_files', 10);
            $this->logToDatabase = (bool)$db->getConfig('log_to_database', true);
            $this->logToFile = (bool)$db->getConfig('log_to_file', true);
        } catch (Throwable $e) {
            $this->logLevel = self::INFO;
            $this->maxFileSize = 10 * 1024 * 1024;
            $this->maxFiles = 10;
            $this->logToDatabase = true;
            $this->logToFile = true;
        }
    }
    
    private function ensureLogDir(): void
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }
    }
    
    private function shouldLog(string $level): bool
    {
        return $this->levels[$level] >= $this->levels[$this->logLevel];
    }
    
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }
    
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }
    
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }
    
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }
    
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context
        ];
        
        if ($this->logToFile) {
            $this->writeToFile($logEntry);
        }
        
        if ($this->logToDatabase) {
            $this->writeToDatabase($logEntry);
        }
        
        if ($level === self::CRITICAL) {
            $this->handleCritical($logEntry);
        }
    }
    
    private function writeToFile(array $entry): void
    {
        $filename = $this->logDir . '/app.log';
        $this->rotateLogFile($filename);
        
        $logLine = sprintf(
            "[%s] [%s] %s %s\n",
            $entry['timestamp'],
            strtoupper($entry['level']),
            $entry['message'],
            !empty($entry['context']) ? json_encode($entry['context'], JSON_UNESCAPED_UNICODE) : ''
        );
        
        file_put_contents($filename, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    private function rotateLogFile(string $filename): void
    {
        if (!file_exists($filename)) {
            return;
        }
        
        if (filesize($filename) < $this->maxFileSize) {
            return;
        }
        
        for ($i = $this->maxFiles - 1; $i > 0; $i--) {
            $oldFile = $filename . '.' . $i;
            $newFile = $filename . '.' . ($i + 1);
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }
        
        rename($filename, $filename . '.1');
        $this->archiveOldLogs();
    }
    
    private function archiveOldLogs(): void
    {
        $archiveDir = $this->logDir . '/archive';
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0777, true);
        }
        
        $cutoffTime = time() - (30 * 24 * 60 * 60);
        
        $files = glob($this->logDir . '/app.log.*');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                $archiveFile = $archiveDir . '/' . basename($file) . '.gz';
                $fpIn = fopen($file, 'rb');
                $fpOut = gzopen($archiveFile, 'wb9');
                stream_copy_to_stream($fpIn, $fpOut);
                fclose($fpIn);
                gzclose($fpOut);
                unlink($file);
            }
        }
    }
    
    private function writeToDatabase(array $entry): void
    {
        try {
            $db = new SQLiteDatabase();
            $pdo = $db->getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO system_logs (level, message, context, created_at)
                VALUES (?, ?, ?, datetime('now'))
            ");
            $stmt->execute([
                $entry['level'],
                $entry['message'],
                !empty($entry['context']) ? json_encode($entry['context'], JSON_UNESCAPED_UNICODE) : null
            ]);
        } catch (Throwable $e) {
            // 忽略数据库日志错误
        }
    }
    
    private function handleCritical(array $entry): void
    {
        $alertFile = $this->logDir . '/critical-alert.log';
        $alertLine = sprintf(
            "[%s] CRITICAL ALERT: %s %s\n",
            $entry['timestamp'],
            $entry['message'],
            !empty($entry['context']) ? json_encode($entry['context'], JSON_UNESCAPED_UNICODE) : ''
        );
        file_put_contents($alertFile, $alertLine, FILE_APPEND | LOCK_EX);
    }
    
    public function getLogs(int $limit = 100, string $level = null): array
    {
        $db = new SQLiteDatabase();
        $pdo = $db->getConnection();
        
        if ($level) {
            $stmt = $pdo->prepare("
                SELECT * FROM system_logs 
                WHERE level = ? 
                ORDER BY id DESC 
                LIMIT ?
            ");
            $stmt->execute([$level, $limit]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM system_logs 
                ORDER BY id DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getStats(): array
    {
        return [
            'log_dir' => $this->logDir,
            'log_level' => $this->logLevel,
            'max_file_size' => $this->maxFileSize,
            'max_files' => $this->maxFiles,
            'log_to_db' => $this->logToDatabase,
            'log_to_file' => $this->logToFile
        ];
    }
}
