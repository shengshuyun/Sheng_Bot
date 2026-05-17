<?php
require_once __DIR__ . '/admin/数据库.php';

$db = new SQLiteDatabase();
$pdo = $db->getConnection();

echo "=== Sheng_Bot 数据库初始化 ===\n\n";

// 检查是否已经安装
$stmt = $pdo->query("SELECT COUNT(*) FROM admins");
if ($stmt->fetchColumn() > 0) {
    echo "✓ 数据库已经安装过了！\n";
    
    // 显示现有管理员
    $stmt = $pdo->query("SELECT * FROM admins");
    $admins = $stmt->fetchAll();
    foreach ($admins as $admin) {
        echo "  管理员: {$admin['username']}\n";
    }
    
    echo "\n如果需要重新安装，请删除数据/sheng_bot.db 文件后再次运行\n";
    exit;
}

// 创建默认管理员
$username = 'admin';
$password = '123456';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, datetime('now'))");
$stmt->execute([$username, $passwordHash]);

echo "✓ 创建管理员账号成功！\n";
echo "  用户名: {$username}\n";
echo "  密码: {$password}\n\n";

// 创建默认配置
$configs = [
    'site_name' => 'Sheng_Bot',
    'domain' => '0.0.0.0',
    'http_port' => 9501,
    'https_port' => 9502,
    'db_pool_max_size' => 10,
    'db_pool_min_size' => 2,
    'db_pool_timeout' => 5,
    'query_cache_enabled' => true,
    'query_cache_ttl' => 300,
    'query_cache_max_size' => 1000,
    'log_level' => 'info',
    'log_max_file_size' => 10 * 1024 * 1024,
    'log_max_files' => 10,
    'log_to_database' => true,
    'log_to_file' => true,
    'installed' => true
];

foreach ($configs as $key => $value) {
    $db->setConfig($key, $value);
}

echo "✓ 创建默认配置完成！\n\n";
echo "=== 安装完成！===\n\n";
echo "你现在可以访问 http://localhost:9501/admin/login.php\n";
echo "使用上面的账号密码登录管理后台\n";
