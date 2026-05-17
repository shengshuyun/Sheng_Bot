<?php
echo "测试页面！\n";
echo "PHP 版本: " . PHP_VERSION . "\n";

// 测试文件访问
echo "\n=== 检查文件 ===\n";
$files = [
    __DIR__ . '/admin/index.html',
    __DIR__ . '/admin/cute-app.js',
    __DIR__ . '/admin/cute-theme.css',
];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file 存在\n";
    } else {
        echo "❌ $file 不存在\n";
    }
}
