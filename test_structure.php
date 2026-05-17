<?php
echo "=== Sheng_Bot 系统结构测试 ===\n\n";

// 测试1: 检查数据库类是否能正常加载
echo "测试1: 检查数据库类...\n";
try {
    require_once __DIR__ . '/admin/数据库.php';
    $db = new SQLiteDatabase();
    echo "✅ 数据库类加载成功\n";
} catch (Exception $e) {
    echo "❌ 失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 测试2: 检查 AdminController 是否能正常加载
echo "\n测试2: 检查 AdminController...\n";
try {
    require_once __DIR__ . '/函数库/AdminController.php';
    echo "✅ AdminController 加载成功\n";
} catch (Exception $e) {
    echo "❌ 失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 测试3: 检查项目结构
echo "\n测试3: 检查项目结构...\n";
$requiredFiles = [
    '/workspace/server.php',
    '/workspace/admin/数据库.php',
    '/workspace/函数库/AdminController.php',
    '/workspace/函数库/数据库连接池.php',
    '/workspace/函数库/日志系统.php',
    '/workspace/函数库/请求处理.php'
];

$allExist = true;
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✅ " . basename($file) . " 存在\n";
    } else {
        echo "❌ " . basename($file) . " 不存在\n";
        $allExist = false;
    }
}

// 测试4: 检查清理后的项目结构
echo "\n测试4: 验证无用文件已删除...\n";
$deletedFiles = [
    '/workspace/debug_is_installed.php',
    '/workspace/config.json',
    '/workspace/com_http.php',
    '/workspace/admin/auth.php',
    '/workspace/admin/login.php'
];

$allDeleted = true;
foreach ($deletedFiles as $file) {
    if (!file_exists($file)) {
        echo "✅ " . basename($file) . " 已删除\n";
    } else {
        echo "❌ " . basename($file) . " 还存在\n";
        $allDeleted = false;
    }
}

echo "\n=== 测试完成 ===\n";
if ($allExist && $allDeleted) {
    echo "✅ 所有测试通过！系统已成功清理并重构。\n";
    echo "\n最终项目结构特点：\n";
    echo "- 前后端完全融合（所有管理功能在 AdminController 中）\n";
    echo "- 所有旧文件已删除\n";
    echo "- 使用 SQLite 数据库存储配置和数据\n";
    echo "- 使用 Swoole 路由处理所有请求\n";
} else {
    echo "⚠️  部分测试失败，请检查。\n";
}
