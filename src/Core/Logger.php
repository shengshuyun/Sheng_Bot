<?php
declare(strict_types=1);

namespace ShengBot\Core;

class Logger
{
    private string $logDir;
    private bool $echoToStdout;
    private static array $buffer = [];
    private static int $lastFlush = 0;
    private static int $flushIntervalMs = 1000;
    private static int $maxBufferSize = 50;

    public function __construct(string $logDir = __DIR__ . '/../../日志', bool $echoToStdout = true)
    {
        $this->logDir = rtrim($logDir, '/');
        $this->echoToStdout = $echoToStdout;
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0777, true);
        }
    }

    public function info(string $消息): void
    {
        $this->写入('INFO', $消息);
    }

    public function error(string $消息): void
    {
        $this->写入('ERROR', $消息);
        $this->flush();
    }

    public function warning(string $消息): void
    {
        $this->写入('WARN', $消息);
    }

    private function 写入(string $级别, string $消息): void
    {
        $行 = date('Y-m-d H:i:s') . " [{$级别}] {$消息}" . PHP_EOL;
        if ($this->echoToStdout) {
            echo $行;
        }

        self::$buffer[] = $行;

        $now = intval(microtime(true) * 1000);
        if (count(self::$buffer) >= self::$maxBufferSize
            || ($now - self::$lastFlush) >= self::$flushIntervalMs
        ) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (empty(self::$buffer)) return;
        $data = implode('', self::$buffer);
        self::$buffer = [];
        self::$lastFlush = intval(microtime(true) * 1000);
        @file_put_contents($this->logDir . '/app.log', $data, FILE_APPEND | LOCK_EX);
    }

    public function __destruct()
    {
        $this->flush();
    }
}
