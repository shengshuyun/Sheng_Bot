<?php
require_once 'admin/数据库.php';

$db = new SQLiteDatabase();
echo "=== 检查 isInstalled() ===\n";
var_dump($db->isInstalled());

echo "\n=== 检查数据库内容 ===\n";
$pdo = $db->getConnection();

echo "Config 表:\n";
$stmt = $pdo->query("SELECT * FROM config");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    var_dump($row);
}

echo "\nAdmins 表:\n";
$stmt = $pdo->query("SELECT * FROM admins");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    var_dump($row);
}
