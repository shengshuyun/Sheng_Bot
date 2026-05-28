<?php
declare(strict_types=1);

namespace ShengBot\Adapters;

use ShengBot\Core\Logger;
use ShengBot\Core\HttpClientPool;
use ShengBot\Traits\DatabaseTrait;
use ShengBot\Traits\TimerTrait;

abstract class BaseAdapter implements AdapterInterface
{
    use TimerTrait, DatabaseTrait;

    public array $配置信息;
    public array $当前账号 = [];
    public string $来源ID = '';
    public string $信息ID = '';
    public string $用户ID = '';
    public mixed $用户信息 = null;
    public string $事件类型 = '';
    protected Logger $logger;

    public function __construct(array $配置信息)
    {
        $this->配置信息 = $配置信息;
        $this->数据库路径 = __DIR__ . '/../../数据/数据库';
        $this->logger = new Logger();
    }

    protected function 异步处理(array $解析, callable $处理器): void
    {
        \Swoole\Coroutine\go(function () use ($解析, $处理器) {
            try {
                $处理器($解析);
            } catch (\Throwable $e) {
                $this->logger->error("[处理异常] " . $e->getMessage());
            }
        });
    }

    protected function httpPost(string $url, string $body, ?array $headers = null): ?object
    {
        $result = HttpClientPool::post($url, $body, $headers);
        if ($result === null) return null;
        return new class($result) {
            private array $data;
            public function __construct(array $data) { $this->data = $data; }
            public function getBody(): string { return $this->data['body'] ?? ''; }
            public function getStatusCode(): int { return $this->data['statusCode'] ?? 0; }
            public function getErrCode(): int { return $this->data['errCode'] ?? 0; }
        };
    }
}
