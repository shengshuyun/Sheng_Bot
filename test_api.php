<?php
/**
 * Sheng_Bot 功能测试脚本
 * 用于验证核心功能是否正常工作
 */

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);

echo "========================================\n";
echo "🌸 Sheng_Bot 核心功能测试\n";
echo "========================================\n\n";

// ================== 测试 1: 数据库 ==================
echo "🗄️ 测试 1: 数据库\n";
echo "----------------------------------------\n";
try {
    require_once __DIR__ . '/admin/数据库.php';
    $db = new SQLiteDatabase();
    echo "✅ SQLiteDatabase 初始化成功\n";
    
    // 测试获取配置
    $siteName = $db->getConfig('site_name', 'Sheng_Bot');
    echo "✅ 配置读取: site_name = " . $siteName . "\n";
    
    // 测试统计数据 (直接使用 PDO)
    $pdo = $db->getConnection();
    $qqBots = $pdo->query("SELECT COUNT(*) FROM qq_bots")->fetchColumn();
    $napcatBots = $pdo->query("SELECT COUNT(*) FROM napcat_bots")->fetchColumn();
    $messageLogs = $pdo->query("SELECT COUNT(*) FROM message_logs")->fetchColumn();
    $systemLogs = $pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn();
    $db->releaseConnection($pdo);
    
    echo "✅ 统计数据读取成功\n";
    echo "  🤖 QQ 机器人: " . $qqBots . "\n";
    echo "  💻 NapCat 机器人: " . $napcatBots . "\n";
    echo "  📨 消息日志: " . $messageLogs . "\n";
    echo "  📋 系统日志: " . $systemLogs . "\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
echo "\n";

// ================== 测试 2: 数据库连接池 ==================
echo "🏊 测试 2: 数据库连接池\n";
echo "----------------------------------------\n";
try {
    require_once __DIR__ . '/函数库/DatabaseConnectionPool.php';
    
    // 创建连接池实例
    $pool = DatabaseConnectionPool::getInstance(
        __DIR__ . '/数据/sheng_bot.db',
        [
            'db_pool_min_size' => 2,
            'db_pool_max_size' => 10,
            'db_pool_timeout' => 5
        ]
    );
    
    echo "✅ DatabaseConnectionPool 创建成功\n";
    
    // 获取连接
    $pdo = $pool->getConnection();
    echo "✅ 从连接池获取连接成功\n";
    
    // 测试查询
    $stmt = $pdo->query("SELECT COUNT(*) FROM config");
    $count = $stmt->fetchColumn();
    echo "✅ 查询成功: config 表有 " . $count . " 条记录\n";
    
    // 释放连接
    $pool->releaseConnection($pdo);
    echo "✅ 连接释放回连接池\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
echo "\n";

// ================== 测试 3: 查询缓存 ==================
echo "💾 测试 3: 查询缓存\n";
echo "----------------------------------------\n";
try {
    require_once __DIR__ . '/函数库/QueryCache.php';
    
    // 创建缓存实例
    $cache = QueryCache::getInstance([
        'query_cache_enabled' => true,
        'query_cache_ttl' => 300,
        'query_cache_max_size' => 1000
    ]);
    
    echo "✅ QueryCache 创建成功\n";
    
    // 测试缓存写入
    $cacheKey = 'test_key_' . time();
    $cache->set($cacheKey, ['test' => 'data']);
    echo "✅ 缓存写入成功\n";
    
    // 测试缓存读取
    $data = $cache->get($cacheKey);
    if ($data && $data['test'] === 'data') {
        echo "✅ 缓存读取成功\n";
    }
    
    // 测试缓存清除
    $cache->clear();
    echo "✅ 缓存清除成功\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
echo "\n";

// ================== 测试 4: 会话管理 ==================
echo "🔑 测试 4: 会话管理\n";
echo "----------------------------------------\n";
try {
    require_once __DIR__ . '/admin/数据库.php';
    $db = new SQLiteDatabase();
    
    // 生成会话 ID
    $sessionId = bin2hex(random_bytes(16));
    
    // 创建会话
    $sessionData = ['user_id' => 123, 'username' => 'test'];
    $db->setSession($sessionId, $sessionData, 3600);
    echo "✅ 会话创建并保存成功 (Session ID: " . substr($sessionId, 0, 16) . "...)\n";
    
    // 读取会话
    $loadedSession = $db->getSession($sessionId);
    if ($loadedSession && $loadedSession['user_id'] === 123) {
        echo "✅ 会话读取成功\n";
    }
    
    // 更新会话
    $sessionData['last_visit'] = time();
    $db->setSession($sessionId, $sessionData, 3600);
    echo "✅ 会话更新成功\n";
    
    // 删除会话
    $db->deleteSession($sessionId);
    echo "✅ 会话删除成功\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
echo "\n";

// ================== 测试 5: AdminController 会话和 CSRF ==================
echo "🛡️ 测试 5: AdminController 会话和 CSRF\n";
echo "----------------------------------------\n";
try {
    require_once __DIR__ . '/函数库/AdminController.php';
    $controller = new AdminController();
    
    // 创建模拟请求和响应
    $request = new class {
        public $cookie = [];
        public $header = [];
        public $post = [];
    };
    
    $response = new class {
        public $headers = [];
        public function cookie($name, $value, $expire, $path) {}
        public function end($content) {}
    };
    
    // 测试 CSRF 令牌生成
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('generateCsrfToken');
    $method->setAccessible(true);
    $token = $method->invoke($controller, $request);
    echo "✅ CSRF 令牌生成成功\n";
    
    // 测试 CSRF 令牌验证
    $validateMethod = $reflection->getMethod('validateCsrfToken');
    $validateMethod->setAccessible(true);
    
    // 设置请求头
    $request->header['x-csrf-token'] = $token;
    $isValid = $validateMethod->invoke($controller, $request);
    if ($isValid) {
        echo "✅ CSRF 令牌验证成功\n";
    }
    
    // 测试无效令牌
    $request->header['x-csrf-token'] = 'invalid-token';
    $isValidInvalid = $validateMethod->invoke($controller, $request);
    if (!$isValidInvalid) {
        echo "✅ 无效令牌验证失败 (正常行为)\n";
    }
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
echo "\n";

// ================== 测试 6: 日志系统 ==================
echo "📝 测试 6: 日志系统\n";
echo "----------------------------------------\n";
try {
    require_once __DIR__ . '/admin/数据库.php';
    $db = new SQLiteDatabase();
    
    // 添加系统日志
    $db->addSystemLog('info', '功能测试日志', ['test' => true]);
    echo "✅ 系统日志添加成功\n";
    
    // 获取系统日志
    $logs = $db->getSystemLogs();
    echo "✅ 系统日志读取成功，共 " . count($logs) . " 条\n";
    
    // 添加消息日志
    $db->addMessageLog('QQ', 1, 'user_123', 'group_456', '这是一条测试消息');
    echo "✅ 消息日志添加成功\n";
    
    // 获取消息日志
    $msgLogs = $db->getMessageLogs();
    echo "✅ 消息日志读取成功，共 " . count($msgLogs) . " 条\n";
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
echo "\n";

// ================== 测试 7: 系统设置 ==================
echo "⚙️ 测试 7: 系统设置\n";
echo "----------------------------------------\n";
try {
    require_once __DIR__ . '/admin/数据库.php';
    $db = new SQLiteDatabase();
    
    // 获取所有设置
    $settings = $db->getAllConfigs();
    echo "✅ 系统设置读取成功\n";
    
    // 检查连接池设置
    if (isset($settings['db_pool_max_size'])) {
        echo "🗄️ db_pool_max_size: " . $settings['db_pool_max_size'] . "\n";
    }
    if (isset($settings['db_pool_min_size'])) {
        echo "🗄️ db_pool_min_size: " . $settings['db_pool_min_size'] . "\n";
    }
    
    // 检查缓存设置
    if (isset($settings['query_cache_enabled'])) {
        echo "💾 query_cache_enabled: " . ($settings['query_cache_enabled'] ? 'true' : 'false') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
echo "\n";

echo "========================================\n";
echo "🎉 所有核心功能测试完成！\n";
echo "========================================\n";
?>
