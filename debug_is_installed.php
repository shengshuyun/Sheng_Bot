<?php
require_once 'admin/数据库.php';

// 删除数据库重新开始
if (file_exists('数据/sheng_bot.db')) {
    unlink('数据/sheng_bot.db');
    echo "✓ 数据库已删除\n\n";
}

echo "=== 步骤1: 实例化 SQLiteDatabase ===\n";
$db = new SQLiteDatabase();
var_dump($db->isInstalled());

echo "\n=== 步骤2: 再次检查 isInstalled() ===\n";
var_dump($db->isInstalled());

echo "\n=== 步骤3: 检查数据库内容 ===\n";
$pdo = $db->getConnection();

echo "\nConfig 表:\n";
$stmt = $pdo->query("SELECT * FROM config");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "共 " . count($rows) . " 条记录\n";
foreach ($rows as $row) {
    echo "  " . $row['config_key'] . " = " . $row['config_value'] . "\n";
}

echo "\nAdmins 表:\n";
$stmt = $pdo->query("SELECT * FROM admins");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "共 " . count($rows) . " 条记录\n";
