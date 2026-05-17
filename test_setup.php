<?php
require_once __DIR__ . '/admin/数据库.php';

$db = new SQLiteDatabase();
$pdo = $db->getConnection();

// 先检查并清空现有数据
$pdo->exec("DELETE FROM admins WHERE username = 'testuser'");
$pdo->exec("DELETE FROM config WHERE config_key = 'installed'");

// 添加测试用户
$passwordHash = password_hash('testpass', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
$stmt->execute(['testuser', $passwordHash]);

// 标记为已安装
$db->setConfig('installed', true);

echo "✅ 测试用户已创建: testuser / testpass\n";
