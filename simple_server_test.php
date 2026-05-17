<?php
/**
 * 临时测试用的服务器 - 绕过登录检查
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

$uri = $_SERVER['REQUEST_URI'] ?? '/';

// 定义静态文件服务
function serveStaticFile($uri) {
    $staticMap = [
        '/admin/index.html' => __DIR__ . '/admin/index.html',
        '/admin/cute-theme.css' => __DIR__ . '/admin/cute-theme.css',
        '/admin/cute-app.js' => __DIR__ . '/admin/cute-app.js',
        '/admin/styles.css' => __DIR__ . '/admin/styles.css'
    ];
    
    if (isset($staticMap[$uri]) && file_exists($staticMap[$uri])) {
        $ext = pathinfo($staticMap[$uri], PATHINFO_EXT);
        $types = [
            'html' => 'text/html; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8'
        ];
        header('Content-Type: ' . ($types[$ext] ?? 'text/plain'));
        readfile($staticMap[$uri]);
        return true;
    }
    return false;
}

if (str_starts_with($uri, '/admin')) {
    // 处理管理后台请求
    $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // 如果是静态文件，直接提供（绕过登录）
    if (serveStaticFile($uri)) {
        exit;
    }
    
    // 如果是根目录，重定向到 index.html
    if ($uri === '/admin' || $uri === '/admin/') {
        header('Location: /admin/index.html');
        exit;
    }
    
    // 处理 API 请求
    if (str_starts_with($uri, '/admin/api/')) {
        // 模拟 Swoole 的 request/response
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
            public $content = '';
            
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
            // 反射调用 handleApi 方法
            $reflection = new ReflectionClass($adminController);
            $handleApiMethod = $reflection->getMethod('handleApi');
            $handleApiMethod->setAccessible(true);
            $handleApiMethod->invoke($adminController, $mockRequest, $mockResponse, $uri);
        } catch (Throwable $e) {
            http_response_code(500);
            echo 'Internal Server Error: ' . $e->getMessage() . "\n";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
        exit;
    }
    
    // 其他请求 404
    http_response_code(404);
    echo 'Not Found';
} else {
    // 默认重定向到管理后台
    header('Location: /admin/');
}
