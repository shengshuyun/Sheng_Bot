<?php
date_default_timezone_set('Asia/Shanghai');

use Swoole\Coroutine\Http\Server;
use Swoole\Coroutine;
use Swoole\Runtime;

// require_once __DIR__ . '/函数库/运行环境检测.php';
require_once __DIR__ . '/函数库/AdminController.php';
require_once __DIR__ . '/admin/数据库.php';

Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

Coroutine\run(function () {
    $db = new SQLiteDatabase();
    $config = [
        'domain' => $db->getConfig('domain', '0.0.0.0'),
        'http_port' => $db->getConfig('http_port', 9501),
        'https_port' => $db->getConfig('https_port', 9502),
        'ssl_cert' => __DIR__ . '/证书/pem',
        'ssl_key' => __DIR__ . '/证书/key',
        'framework' => [
            'QQBOT' => [],
            'napcat' => []
        ]
    ];
    
    $pdo = $db->getConnection();
    $qqBots = $pdo->query("SELECT * FROM qq_bots")->fetchAll();
    foreach ($qqBots as $bot) {
        $config['framework']['QQBOT'][] = [
            'appid' => $bot['appid'],
            'secret' => $bot['secret'],
            'sandbox' => (bool)$bot['sandbox']
        ];
    }
    
    $napcatBots = $pdo->query("SELECT * FROM napcat_bots")->fetchAll();
    foreach ($napcatBots as $bot) {
        $config['framework']['napcat'][] = [
            'qq' => $bot['qq'],
            'http_url' => $bot['http_url'],
            'token' => $bot['token']
        ];
    }
    
    foreach ([$config['http_port'], $config['https_port']] as $port) {
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if ($fp) {
            fclose($fp);
            throw new RuntimeException("端口 {$port} 已被占用，请释放后再运行");
        }
    }
    
    $处理程序 = require_once __DIR__ . '/函数库/请求处理.php';
    $adminController = new AdminController();
    
    $回调 = function ($request, $response) use ($处理程序, $config, $adminController) {
        try {
            $uri = $request->server['request_uri'] ?? '/';
            
            if (str_starts_with($uri, '/admin')) {
                $adminController->handle($request, $response);
                return;
            }
            
            $处理程序($request, $response, $config['framework']);
        } catch (Throwable $e) {
            $response->status(500);
            $response->end('Internal Server Error: ' . $e->getMessage());
        }
    };
    
    go(function () use ($config, $回调) {
        try {
            $srv = new Server($config['domain'], $config['http_port'], false);
            $srv->handle('/', $回调);
            echo "✅ HTTP 服务启动：http://{$config['domain']}:{$config['http_port']}\n";
            echo "📊 管理后台：http://{$config['domain']}:{$config['http_port']}/admin/\n";
            $srv->start();
        } catch (Throwable $e) {
            exit("❌ HTTP 服务挂了：{$e->getMessage()}\n");
        }
    });
    
    go(function () use ($config, $回调) {
        try {
            if (file_exists($config['ssl_cert']) && file_exists($config['ssl_key'])) {
                $srv = new Server($config['domain'], $config['https_port'], true);
                $srv->set([
                    'ssl_cert_file' => $config['ssl_cert'],
                    'ssl_key_file' => $config['ssl_key'],
                ]);
                $srv->handle('/', $回调);
                echo "✅ HTTPS 服务启动：https://{$config['domain']}:{$config['https_port']}\n";
                $srv->start();
            } else {
                echo "ℹ️ SSL证书不存在，跳过HTTPS服务启动\n";
            }
        } catch (Throwable $e) {
            echo "⚠️ HTTPS 服务启动失败：{$e->getMessage()}\n";
        }
    });
});
