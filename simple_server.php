<?php
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

if (str_starts_with($uri, '/admin')) {
    // 处理管理后台请求
    $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // 模拟 Swoole 的 request/response
    $mockRequest = new class {
        public $server = [];
        public $header = [];
        public $get = [];
        public $post = [];
        public $files = [];
        
        public function __construct() {
            $this->server = $_SERVER;
            $this->get = $_GET;
            $this->post = $_POST;
            $this->files = $_FILES;
            $this->header = getallheaders() ?: [];
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
} else {
    // 静态文件处理
    $path = __DIR__ . '/admin/' . ltrim($uri, '/');
    if (file_exists($path) && is_file($path)) {
        $mime = mime_content_type($path);
        header("Content-Type: $mime");
        readfile($path);
    } else {
        // 默认重定向到管理后台
        header('Location: /admin/');
    }
}
