<?php
/**
 * 调试脚本 - 测试 AdminController 的 handle 方法
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/admin/数据库.php';
require_once __DIR__ . '/函数库/AdminController.php';

echo "=== 调试 AdminController ===\n\n";

$db = new SQLiteDatabase();
$adminController = new AdminController();

// 模拟 request/response
$mockRequest = new class {
    public $server = [
        'REQUEST_URI' => '/admin/login',
        'REQUEST_METHOD' => 'GET'
    ];
    public $header = [];
    public $get = [];
    public $post = [];
    public $files = [];
    public $cookie = [];
    
    public function rawContent() { return ''; }
    public function getMethod() { return 'GET'; }
};

$mockResponse = new class {
    public $headers = [];
    public $statusCode = 200;
    public $content = '';
    public $output = '';
    
    public function status($code) {
        $this->statusCode = $code;
        echo "[status] $code\n";
    }
    
    public function header($key, $value) {
        $this->headers[$key] = $value;
        echo "[header] $key: $value\n";
    }
    
    public function cookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false) {
        echo "[cookie] $name = $value (expire: $expire)\n";
    }
    
    public function end($content) {
        $this->content = $content;
        echo "[end] Length: " . strlen($content) . "\n";
        echo "--- Content Start ---\n$content\n--- Content End ---\n";
    }
};

echo "=== 开始处理请求 ===\n";
try {
    $adminController->handle($mockRequest, $mockResponse);
} catch (Throwable $e) {
    echo "\n=== 错误 ===\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
echo "\n=== 完成 ===\n";
