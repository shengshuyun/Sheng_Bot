<?php
require_once __DIR__ . '/admin/数据库.php';

$db = new SQLiteDatabase();
$pdo = $db->getConnection();

echo "=== Session 表数据 ===\n";
$stmt = $pdo->query("SELECT * FROM sessions");
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($sessions as $session) {
    echo "ID: {$session['id']}\n";
    echo "Session ID: {$session['session_id']}\n";
    echo "Data: " . substr($session['session_data'], 0, 100) . "...\n";
    echo "Expire At: " . date('Y-m-d H:i:s', $session['expire_at']) . "\n";
    echo "Created At: {$session['created_at']}\n";
    echo "Updated At: {$session['updated_at']}\n";
    echo "---\n";
}

echo "\n=== 系统日志 ===\n";
$stmt = $pdo->query("SELECT * FROM system_logs ORDER BY id DESC LIMIT 10");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($logs as $log) {
    echo "[{$log['level']}] {$log['message']} ({$log['created_at']})\n";
}
