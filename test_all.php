<?php

echo "=== Sheng_Bot 功能测试 ===\n\n";

$baseUrl = "http://localhost:9501";
$cookieFile = tempnam(sys_get_temp_dir(), 'sb_test_cookies');

function test($name, $url, $expectedStatus = 200, $postData = null) {
    global $baseUrl, $cookieFile;
    
    echo "测试: $name... ";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);
    curl_close($ch);
    
    if ($httpCode === $expectedStatus) {
        echo "✅ 通过 (HTTP $httpCode)\n";
        return ['success' => true, 'body' => $body];
    } else {
        echo "❌ 失败 (期望 $expectedStatus, 实际 $httpCode)\n";
        return ['success' => false, 'body' => $body];
    }
}

// 1. 测试登录页面
test("登录页面显示", "/admin/login", 200);

// 2. 测试登录
$loginResult = test("登录功能", "/admin/login/do", 302, [
    'username' => 'admin',
    'password' => 'admin123'
]);

// 3. 测试访问控制台（需要登录）
if ($loginResult['success']) {
    test("控制台页面", "/admin/", 200);
    
    // 4. 测试官方QQ机器人页面
    test("官方QQ机器人页面", "/admin/bots/qq", 200);
    
    // 5. 测试NapCat机器人页面
    test("NapCat机器人页面", "/admin/bots/napcat", 200);
    
    // 6. 测试消息日志页面
    test("消息日志页面", "/admin/logs", 200);
    
    // 7. 测试系统日志页面
    test("系统日志页面", "/admin/system/logs", 200);
    
    // 8. 测试系统设置页面
    test("系统设置页面", "/admin/settings", 200);
    
    // 9. 测试添加QQ机器人
    test("添加QQ机器人", "/admin/bots/qq/add", 302, [
        'appid' => 'test_appid_123',
        'secret' => 'test_secret_456',
        'sandbox' => 'on'
    ]);
    
    // 10. 测试添加NapCat机器人
    test("添加NapCat机器人", "/admin/bots/napcat/add", 302, [
        'qq' => '12345678',
        'http_url' => 'http://127.0.0.1:3000',
        'token' => 'test_token'
    ]);
    
    // 11. 测试保存设置
    test("保存系统设置", "/admin/settings/save", 302, [
        'site_name' => 'Test Sheng_Bot',
        'domain' => '0.0.0.0',
        'http_port' => '9501',
        'https_port' => '9502'
    ]);
    
    // 检查数据库更新
    $db = new PDO('sqlite:数据/sheng_bot.db');
    $qqBots = $db->query("SELECT * FROM qq_bots")->fetchAll();
    echo "\n数据库中的QQ机器人: " . count($qqBots) . " 个\n";
    
    $napcatBots = $db->query("SELECT * FROM napcat_bots")->fetchAll();
    echo "数据库中的NapCat机器人: " . count($napcatBots) . " 个\n";
}

// 清理
@unlink($cookieFile);

echo "\n=== 测试完成 ===\n";
