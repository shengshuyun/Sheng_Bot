<?php
date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/vendor/autoload.php';

use Swoole\Coroutine\Http\Server;
use Swoole\Coroutine;
use Swoole\Runtime;
use Swoole\Process;
use ShengBot\Core\Router;
use ShengBot\Core\Logger;
use ShengBot\Core\HttpClientPool;

if (!file_exists(__DIR__ . '/config.json')) {
    if (file_exists(__DIR__ . '/config.example.json')) {
        exit("请先复制配置文件：cp config.example.json config.json，然后编辑填入你的配置\n");
    }
    exit("配置文件失踪了....Www" . PHP_EOL);
}

if (version_compare(PHP_VERSION, '8.4.0', '<')) {
    exit('PHP版本需要8.4或更高，当前版本：' . PHP_VERSION . PHP_EOL);
}

if (!extension_loaded('swoole')) {
    exit('Swoole扩展未安装' . PHP_EOL);
}

$配置 = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
if (!$配置) {
    exit("配置 JSON 解析失败\n");
}

$必需字段 = ['域名', 'http端口', 'https端口', 'ssl证书', 'ssl密钥', '框架'];
foreach ($必需字段 as $字段) {
    if (!isset($配置[$字段])) {
        exit("配置缺少必需字段: {$字段}\n");
    }
}

function 查找占用端口的进程(int $port): ?int
{
    $output = [];
    exec("lsof -ti :{$port} 2>/dev/null", $output, $ret);
    if ($ret === 0 && !empty($output)) {
        return (int)trim($output[0]);
    }
    return null;
}

function 释放端口(int $port): bool
{
    $pid = 查找占用端口的进程($port);
    if ($pid === null) return true;

    echo "正在终止占用端口 {$port} 的进程 (PID: {$pid})...\n";
    posix_kill($pid, SIGTERM);
    usleep(500000);

    if (posix_kill($pid, 0)) {
        posix_kill($pid, SIGKILL);
        usleep(200000);
    }

    return !posix_kill($pid, 0);
}

$占用端口 = [];
foreach ([$配置['http端口'], $配置['https端口']] as $port) {
    $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
    if ($fp) {
        fclose($fp);
        $占用端口[] = $port;
    }
}

if (!empty($占用端口)) {
    $端口列表 = implode(', ', $占用端口);
    echo "端口 {$端口列表} 已被占用\n";

    foreach ($占用端口 as $port) {
        $pid = 查找占用端口的进程($port);
        if ($pid) {
            echo "  端口 {$port} 被进程 PID={$pid} 占用\n";
        }
    }

    echo "是否释放端口并重启？(y/N): ";
    $input = trim(fgets(STDIN));

    if (strtolower($input) === 'y') {
        $成功 = true;
        foreach ($占用端口 as $port) {
            if (!释放端口($port)) {
                echo "❌ 无法释放端口 {$port}\n";
                $成功 = false;
            } else {
                echo "✅ 端口 {$port} 已释放\n";
            }
        }
        if (!$成功) {
            exit(1);
        }
        echo "\n";
    } else {
        exit(3);
    }
}

if (!empty($配置['ssl证书'])) {
    $certFile = __DIR__ . '/' . $配置['ssl证书'];
    if (!file_exists($certFile)) {
        echo "⚠️ SSL证书文件不存在: {$certFile}" . PHP_EOL;
    } else {
        $cert = openssl_x509_parse(file_get_contents($certFile));
        if (!$cert) {
            echo "⚠️ SSL证书文件解析失败" . PHP_EOL;
        } else {
            $remainingSeconds = $cert['validTo_time_t'] - time();
            $days = (int)($remainingSeconds / 86400);
            if ($remainingSeconds < 0) {
                echo "⚠️ SSL证书已过期 " . abs($days) . " 天，请立即更新" . PHP_EOL;
            } elseif ($days <= 7) {
                echo "⚠️ SSL证书仅剩 {$days} 天过期" . PHP_EOL;
            } else {
                echo "✅ SSL证书有效，剩余 {$days} 天" . PHP_EOL;
            }
        }
    }
}

Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

try {
    Coroutine\run(function () use ($配置) {
    $logger = new Logger();

    HttpClientPool::配置(
        $配置['连接池大小'] ?? 8,
        $配置['连接超时'] ?? 10
    );

    $回调 = function (\Swoole\Http\Request $请求, \Swoole\Http\Response $响应) use ($配置, $logger) {
        try {
            Router::分发($请求, $响应, $配置);
        } catch (Throwable $e) {
            $logger->error("[请求异常] " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $响应->status(500);
            $响应->end("Internal Server Error");
        }
    };

    $srvRef = ['http' => null, 'https' => null];
    $shouldExit = false;

    go(function () use ($配置, $回调, $srvRef) {
        try {
            $srv = new Server($配置['域名'], $配置['http端口'], false);
            $srv->handle('/', $回调);
            $srvRef['http'] = $srv;
            echo "HTTP  服务启动：http://{$配置['域名']}:{$配置['http端口']}\n";
            $srv->start();
        } catch (Throwable $e) {
            echo "HTTP 服务挂了：{$e->getMessage()}\n";
        }
    });

    go(function () use ($配置, $回调, $srvRef) {
        try {
            $srv = new Server($配置['域名'], $配置['https端口'], true);
            $srv->set([
                'ssl_cert_file' => __DIR__ . '/' . $配置['ssl证书'],
                'ssl_key_file'  => __DIR__ . '/' . $配置['ssl密钥'],
            ]);
            $srv->handle('/', $回调);
            $srvRef['https'] = $srv;
            echo "HTTPS 服务启动：https://{$配置['域名']}:{$配置['https端口']}\n";
            $srv->start();
        } catch (Throwable $e) {
            echo "HTTPS 服务挂了：{$e->getMessage()}\n";
        }
    });

    Process::signal(SIGTERM, function () use (&$shouldExit) {
        $shouldExit = true;
    });

    Process::signal(SIGINT, function () use (&$shouldExit) {
        $shouldExit = true;
    });

    go(function () use (&$shouldExit, $srvRef) {
        while (!$shouldExit) {
            Coroutine::sleep(0.5);
        }
        foreach ($srvRef as $srv) {
            if ($srv) @$srv->shutdown();
        }
        echo "已关闭\n";
        posix_kill(getmypid(), SIGKILL);
    });
});
} catch (\Swoole\ExitException $e) {
    // 正常退出
}