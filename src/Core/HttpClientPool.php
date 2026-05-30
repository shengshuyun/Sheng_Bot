<?php
declare(strict_types=1);

namespace ShengBot\Core;

use Swoole\Coroutine\Http\Client;
use Swoole\Coroutine\Channel;

class HttpClientPool
{
    private static array $pools = [];
    private static int $poolSize = 8;
    private static int $timeout = 10;

    public static function 配置(int $poolSize = 8, int $timeout = 10): void
    {
        self::$poolSize = $poolSize;
        self::$timeout = $timeout;
    }

    private static function 获取池(string $host, int $port, bool $ssl): Channel
    {
        $key = ($ssl ? 'https' : 'http') . "://{$host}:{$port}";
        if (!isset(self::$pools[$key])) {
            self::$pools[$key] = new Channel(self::$poolSize);
        }
        return self::$pools[$key];
    }

    private static function 创建连接(string $host, int $port, bool $ssl): Client
    {
        $cli = new Client($host, $port, $ssl);
        $cli->set([
            'timeout' => self::$timeout,
            'keep_alive' => true,
        ]);
        return $cli;
    }

    public static function post(string $url, string $body, ?array $headers = null): ?array
    {
        $parsed = parse_url($url);
        $host = $parsed['host'];
        $port = $parsed['port'] ?? (isset($parsed['scheme']) && $parsed['scheme'] === 'https' ? 443 : 80);
        $ssl = ($parsed['scheme'] ?? 'http') === 'https';
        $path = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

        $池 = self::获取池($host, $port, $ssl);

        $cli = null;
        if ($池->length() > 0) {
            $cli = $池->pop(0.01);
            if ($cli && ($cli->errCode !== 0 || !$cli->connected)) {
                $cli->close();
                $cli = null;
            }
        }
        if ($cli === null) {
            $cli = self::创建连接($host, $port, $ssl);
        }

        $cli->setHeaders($headers ?? ['Content-Type' => 'application/json']);
        $cli->post($path, $body);

        if ($cli->errCode !== 0) {
            $result = [
                'statusCode' => $cli->statusCode,
                'body' => '',
                'errCode' => $cli->errCode,
                'errMsg' => $cli->errMsg ?? '',
            ];
            $cli->close();
            return $result;
        }

        $result = [
            'statusCode' => $cli->statusCode,
            'body' => $cli->getBody(),
            'errCode' => 0,
        ];

        if ($池->length() < self::$poolSize) {
            $池->push($cli);
        } else {
            $cli->close();
        }

        return $result;
    }

    public static function delete(string $url, ?array $headers = null): ?array
    {
        $parsed = parse_url($url);
        $host = $parsed['host'];
        $port = $parsed['port'] ?? (isset($parsed['scheme']) && $parsed['scheme'] === 'https' ? 443 : 80);
        $ssl = ($parsed['scheme'] ?? 'http') === 'https';
        $path = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

        $池 = self::获取池($host, $port, $ssl);

        $cli = null;
        if ($池->length() > 0) {
            $cli = $池->pop(0.01);
            if ($cli && ($cli->errCode !== 0 || !$cli->connected)) {
                $cli->close();
                $cli = null;
            }
        }
        if ($cli === null) {
            $cli = self::创建连接($host, $port, $ssl);
        }

        $cli->setHeaders($headers ?? ['Content-Type' => 'application/json']);
        $cli->setMethod('DELETE');
        $cli->execute($path);

        if ($cli->errCode !== 0) {
            $result = [
                'statusCode' => $cli->statusCode,
                'body' => '',
                'errCode' => $cli->errCode,
                'errMsg' => $cli->errMsg ?? '',
            ];
            $cli->close();
            return $result;
        }

        $result = [
            'statusCode' => $cli->statusCode,
            'body' => $cli->getBody(),
            'errCode' => 0,
        ];

        if ($池->length() < self::$poolSize) {
            $池->push($cli);
        } else {
            $cli->close();
        }

        return $result;
    }

    public static function 关闭所有(): void
    {
        foreach (self::$pools as $池) {
            while ($池->length() > 0) {
                $cli = $池->pop(0.01);
                if ($cli) $cli->close();
            }
            $池->close();
        }
        self::$pools = [];
    }
}
