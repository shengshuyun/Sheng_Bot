<?php
date_default_timezone_set('Asia/Shanghai');

echo "=== 1. 删除数据库 ===\n";
if (file_exists(__DIR__ . '/数据/sheng_bot.db')) {
    unlink(__DIR__ . '/数据/sheng_bot.db');
    echo "✓ 数据库已删除\n\n";
}

echo "=== 2. 模拟 server.php 的启动步骤（不含swoole） ===\n";

require_once __DIR__ . '/函数库/AdminController.php';
require_once __DIR__ . '/admin/数据库.php';

echo "\n=== 3. 实例化 SQLiteDatabase ===\n";
$db = new SQLiteDatabase();

echo "\n=== 4. 检查是否已安装 ===\n";
var_dump($db->isInstalled());

echo "\n=== 5. 调用 getConfig() 读取配置（不会修改数据库） ===\n";
$config = [
    'domain' => $db->getConfig('domain', '0.0.0.0'),
    'http_port' => $db->getConfig('http_port', 9501),
    'https_port' => $db->getConfig('https_port', 9502),
];
var_dump($config);

echo "\n=== 6. 再次检查是否已安装 ===\n";
var_dump($db->isInstalled());

echo "\n=== 7. 检查数据库内容 ===\n";
$pdo = $db->getConnection();
$stmt = $pdo->query("SELECT * FROM config");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "config 表记录数: " . count($rows) . "\n";
foreach ($rows as $row) {
    echo "  {$row['config_key']} = {$row['config_value']}\n";
}

$stmt = $pdo->query("SELECT * FROM admins");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nadmins 表记录数: " . count($rows) . "\n";
