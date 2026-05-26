<?php
date_default_timezone_set('Asia/Shanghai');

if (!file_exists(__DIR__ . "/config.json")) {
    exit("配置文件失踪了....Www" . PHP_EOL);
}

use Swoole\Coroutine\Http\Server;
use Swoole\Coroutine;
use Swoole\Runtime;

// 运行前检查
require_once __DIR__ . '/函数库/运行环境检测.php';

// 1. 全开协程钩子
Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

// 2. 协程容器
Coroutine\run(function () {
    // 读取配置文件
    $配置 = json_decode(
        Coroutine\System::readFile(__DIR__ . '/config.json'),
        true
    );
    if (!$配置) {
        exit("配置 JSON 解析失败\n");
    }

    foreach ([$配置['http端口'], $配置['https端口']] as $port) {
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if ($fp) {
            fclose($fp);
            throw new RuntimeException("端口 {$port} 已被占用，请释放后再运行");
        }
    }

    $处理程序 = require_once __DIR__ . '/函数库/请求处理.php';

    // 统一请求回调
    $回调 = function (\Swoole\Http\Request $请求, \Swoole\Http\Response $响应) use ($处理程序, $配置) {
        try {
            $处理程序($请求, $响应, $配置["框架"]);
        } catch (Throwable $_) {
            $响应->status(500);
            $响应->end("Internal Server Error");
        }
    };

    // 启动80服务器
    go(function () use ($配置, $回调) {
    try {
            $srv = new Server($配置['域名'], $配置['http端口'], false);
            $srv->handle('/', $回调);
            echo "HTTP  服务启动：http://{$配置['域名']}:{$配置['http端口']}\n";
            $srv->start();
        } catch (Throwable $e) {
            exit("HTTP 服务挂了：{$e->getMessage()}\n");
        }
    });

    // 启动443服务器
    go(function () use ($配置, $回调) {
        try {
            $srv = new Server($配置['域名'], $配置['https端口'], true);
            $srv->set([
                'ssl_cert_file' => __DIR__ . '/' . $配置['ssl证书'],
                'ssl_key_file'  => __DIR__ . '/' . $配置['ssl密钥'],
            ]);
            $srv->handle('/', $回调);
            echo "HTTPS 服务启动：https://{$配置['域名']}:{$配置['https端口']}\n";
            $srv->start();
        } catch (Throwable $e) {
            exit("HTTPS 服务挂了：{$e->getMessage()}\n");
        }
    });

});
