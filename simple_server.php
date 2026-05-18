<?php
/**
 * 简单版服务器 - 模仿真实 server.php 的行为
 * 不依赖 Swoole 扩展
 */

date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/admin/数据库.php';
require_once __DIR__ . '/函数库/AdminController.php';

$db = new SQLiteDatabase();
$config = [
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

$adminController = new AdminController();
$adminController->setFrameworkConfig($config['framework']);

// 添加启动日志
$db->addSystemLog('info', 'Sheng_Bot 服务器启动', [
    'php_version' => PHP_VERSION,
    'http_port' => 9501
]);

$uri = $_SERVER['REQUEST_URI'] ?? '/';

// 静态文件服务
$staticMap = [
    '/admin/index.html' => __DIR__ . '/admin/index.html',
    '/admin/cute-app.js' => __DIR__ . '/admin/cute-app.js',
    '/admin/cute-theme.css' => __DIR__ . '/admin/cute-theme.css',
    '/admin/styles.css' => __DIR__ . '/admin/styles.css'
];

// 静态文件优先
if (isset($staticMap[$uri])) {
    $path = $staticMap[$uri];
    if (file_exists($path)) {
        // 根据 URI 直接确定内容类型
        if (str_ends_with($uri, '.html')) {
            header('Content-Type: text/html; charset=utf-8');
        } elseif (str_ends_with($uri, '.js')) {
            header('Content-Type: application/javascript; charset=utf-8');
        } elseif (str_ends_with($uri, '.css')) {
            header('Content-Type: text/css; charset=utf-8');
        } else {
            header('Content-Type: text/plain');
        }
        readfile($path);
        exit;
    }
}

// 管理后台请求
if (str_starts_with($uri, '/admin')) {
    if ($uri === '/admin' || $uri === '/admin/') {
        header('Location: /admin/index.html');
        exit;
    }
    
    // 模拟 Swoole request/response
    $mockRequest = new class {
        public $server = [];
        public $header = [];
        public $get = [];
        public $post = [];
        public $files = [];
        public $cookie = [];
        
        public function __construct() {
            $this->server = $_SERVER;
            $this->get = $_GET;
            $this->post = $_POST;
            $this->files = $_FILES;
            $this->header = getallheaders() ?: [];
            $this->cookie = $_COOKIE;
        }
        
        public function rawContent() {
            return file_get_contents('php://input');
        }
        
        public function getMethod() {
            return $_SERVER['REQUEST_METHOD'] ?? 'GET';
        }
    };
    
    $mockResponse = new class {
        public $headers = [];
        public $statusCode = 200;
        
        public function status($code) {
            $this->statusCode = $code;
            http_response_code($code);
        }
        
        public function header($key, $value) {
            $this->headers[$key] = $value;
            header("$key: $value");
        }
        
        public function cookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false) {
            setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
        }
        
        public function end($content) {
            echo $content;
            exit;
        }
    };
    
    try {
        $adminController->handle($mockRequest, $mockResponse);
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Internal Server Error: ' . $e->getMessage();
    }
    exit;
}

// 默认重定向
header('Location: /admin/');
