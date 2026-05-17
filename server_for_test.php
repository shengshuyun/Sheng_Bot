<?php
/**
 * 超级稳定的测试服务器
 * 简单直接，绕过所有复杂功能，直接提供内容
 */

date_default_timezone_set('Asia/Shanghai');

$uri = $_SERVER['REQUEST_URI'] ?? '/';

// 路由表
$routes = [
    '/admin' => 'redirect_index',
    '/admin/' => 'redirect_index',
    '/admin/index.html' => 'serve_index',
    '/admin/cute-app.js' => 'serve_app_js',
    '/admin/cute-theme.css' => 'serve_theme_css',
    '/admin/styles.css' => 'serve_styles_css',
];

function redirect_index() {
    header('Location: /admin/index.html');
    exit;
}

function serve_file($path, $contentType) {
    if (file_exists($path)) {
        header('Content-Type: ' . $contentType);
        readfile($path);
    } else {
        http_response_code(404);
        echo "File not found: $path";
    }
    exit;
}

function serve_index() {
    serve_file(__DIR__ . '/admin/index.html', 'text/html; charset=utf-8');
}

function serve_app_js() {
    serve_file(__DIR__ . '/admin/cute-app.js', 'application/javascript; charset=utf-8');
}

function serve_theme_css() {
    serve_file(__DIR__ . '/admin/cute-theme.css', 'text/css; charset=utf-8');
}

function serve_styles_css() {
    serve_file(__DIR__ . '/admin/styles.css', 'text/css; charset=utf-8');
}

// 简单的 API 响应（模拟数据）
if (str_starts_with($uri, '/admin/api/')) {
    header('Content-Type: application/json; charset=utf-8');
    
    require_once __DIR__ . '/admin/数据库.php';
    $db = new SQLiteDatabase();
    $pdo = $db->getConnection();
    
    if ($uri === '/admin/api/stats') {
        echo json_encode([
            'qqBots' => $pdo->query("SELECT COUNT(*) FROM qq_bots")->fetchColumn(),
            'napcatBots' => $pdo->query("SELECT COUNT(*) FROM napcat_bots")->fetchColumn(),
            'messageLogs' => $pdo->query("SELECT COUNT(*) FROM message_logs")->fetchColumn(),
            'systemLogs' => $pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn(),
            'phpVersion' => PHP_VERSION,
            'swooleVersion' => 'Unknown (simple server)'
        ]);
        exit;
    } elseif ($uri === '/admin/api/qq-bots') {
        $bots = $pdo->query("SELECT * FROM qq_bots")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['bots' => $bots]);
        exit;
    } elseif ($uri === '/admin/api/napcat-bots') {
        $bots = $pdo->query("SELECT * FROM napcat_bots")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['bots' => $bots]);
        exit;
    } elseif ($uri === '/admin/api/message-logs') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $logs = $db->getMessageLogs($limit);
        echo json_encode(['logs' => $logs]);
        exit;
    } elseif ($uri === '/admin/api/system-logs') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $logs = $db->getSystemLogs($limit);
        echo json_encode(['logs' => $logs]);
        exit;
    } elseif ($uri === '/admin/api/settings') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo json_encode([
                'settings' => $db->getAllConfigs(),
                'csrf_token' => bin2hex(random_bytes(32))
            ]);
        }
        exit;
    } elseif ($uri === '/admin/api/csrf-token') {
        echo json_encode(['csrf_token' => bin2hex(random_bytes(32))]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Not implemented']);
    exit;
}

// 路由处理
if (isset($routes[$uri])) {
    call_user_func($routes[$uri]);
} elseif ($uri === '/') {
    redirect_index();
} else {
    http_response_code(404);
    echo "404 Not Found - $uri";
}
