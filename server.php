<?php
date_default_timezone_set('Asia/Shanghai');

use Swoole\Coroutine\Http\Server;
use Swoole\Coroutine;
use Swoole\Runtime;

// 运行前检查
require_once __DIR__ . '/函数库/运行环境检测.php';
require_once __DIR__ . '/admin/数据库.php';

// 1. 全开协程钩子
Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

// 2. 协程容器
Coroutine\run(function () {
    $db = new SQLiteDatabase();
    
    // 读取配置文件
    $config = [
        '域名' => $db->getConfig('domain', '0.0.0.0'),
        'http端口' => $db->getConfig('http_port', 9501),
        'https端口' => $db->getConfig('https_port', 9502),
        'ssl证书' => '证书/pem',
        'ssl密钥' => '证书/key',
        '框架' => [
            'QQBOT' => [],
            'napcat' => []
        ]
    ];
    
    // 从数据库读取QQ机器人配置
    $pdo = $db->getConnection();
    $qqBots = $pdo->query("SELECT * FROM qq_bots")->fetchAll();
    foreach ($qqBots as $bot) {
        $config['框架']['QQBOT'][] = [
            'appid' => $bot['appid'],
            'secret' => $bot['secret'],
            'sandbox' => (bool)$bot['sandbox']
        ];
    }
    
    // 从数据库读取NapCat配置
    $napcatBots = $pdo->query("SELECT * FROM napcat_bots")->fetchAll();
    foreach ($napcatBots as $bot) {
        $config['框架']['napcat'][] = [
            'qq' => $bot['qq'],
            'http_url' => $bot['http_url'],
            'token' => $bot['token']
        ];
    }

    // 端口占用检查
    foreach ([$config['http端口'], $config['https端口']] as $port) {
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if ($fp) {
            fclose($fp);
            throw new RuntimeException("端口 {$port} 已被占用，请释放后再运行");
        }
    }

    $处理程序 = require_once __DIR__ . '/函数库/请求处理.php';

    // 统一请求回调
    $回调 = function (\Swoole\Http\Request $请求, \Swoole\Http\Response $响应) use ($处理程序, $config, $db) {
        try {
            $uri = $请求->server['request_uri'] ?? '/';
            
            // 处理管理后台路由
            if (strpos($uri, '/admin') === 0) {
                handleAdmin($请求, $响应, $uri);
                return;
            }
            
            $处理程序($请求, $响应, $config["框架"]);
        } catch (Throwable $e) {
            echo "Error: " . $e->getMessage() . "\n";
            $响应->status(500);
            $响应->end("Internal Server Error");
        }
    };

    // 启动HTTP服务器
    go(function () use ($config, $回调) {
        try {
            $srv = new Server($config['域名'], $config['http端口'], false);
            $srv->handle('/', $回调);
            echo "✅ HTTP 服务启动：http://{$config['域名']}:{$config['http端口']}\n";
            echo "📊 管理后台：http://{$config['域名']}:{$config['http端口']}/admin/\n";
            $srv->start();
        } catch (Throwable $e) {
            exit("❌ HTTP 服务挂了：{$e->getMessage()}\n");
        }
    });

    // 启动HTTPS服务器
    go(function () use ($config, $回调) {
        try {
            $sslCert = __DIR__ . '/' . $config['ssl证书'];
            $sslKey = __DIR__ . '/' . $config['ssl密钥'];
            
            if (file_exists($sslCert) && file_exists($sslKey)) {
                $srv = new Server($config['域名'], $config['https端口'], true);
                $srv->set([
                    'ssl_cert_file' => $sslCert,
                    'ssl_key_file' => $sslKey,
                ]);
                $srv->handle('/', $回调);
                echo "✅ HTTPS 服务启动：https://{$config['域名']}:{$config['https端口']}\n";
                $srv->start();
            } else {
                echo "ℹ️  SSL证书不存在，跳过HTTPS服务启动\n";
            }
        } catch (Throwable $e) {
            echo "⚠️ HTTPS 服务启动失败：{$e->getMessage()}\n";
        }
    });
});

function handleAdmin($request, $response, $uri)
{
    $scriptFile = __DIR__ . $uri;
    
    if (substr($scriptFile, -1) === '/') {
        $scriptFile .= 'index.php';
    }
    
    if (!file_exists($scriptFile) || pathinfo($scriptFile, PATHINFO_EXTENSION) !== 'php') {
        $response->status(404);
        $response->end("Not Found");
        return;
    }
    
    ob_start();
    try {
        chdir(dirname($scriptFile));
        require $scriptFile;
        $content = ob_get_clean();
        $response->end($content);
    } catch (Throwable $e) {
        ob_end_clean();
        $response->status(500);
        $response->end("Admin Error: " . $e->getMessage());
    }
}
