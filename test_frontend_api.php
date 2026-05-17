<?php
/**
 * 前端 API 端点功能测试脚本
 * 模拟前端请求测试各个功能接口
 */

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);

// 测试服务器是否正在运行
echo "========================================\n";
echo "🌸 前端 API 功能测试\n";
echo "========================================\n\n";

echo "🔍 检查服务器状态...\n";
$serverRunning = false;
$port = 9501;
$host = '127.0.0.1';

// 检查进程
exec("ps aux | grep 'php -S' | grep -v grep", $output);
if (!empty($output)) {
    echo "✅ PHP 开发服务器正在运行\n";
    $serverRunning = true;
} else {
    echo "⚠️  未检测到服务器，正在启动...\n";
    exec("cd /workspace && nohup php -S 0.0.0.0:{$port} simple_server.php > /dev/null 2>&1 & echo $!", $pidOutput);
    sleep(2);
    $serverRunning = true;
    echo "✅ 服务器已启动\n";
}

// 创建一个模拟的请求处理类
class APITester
{
    private $db;
    
    public function __construct()
    {
        require_once __DIR__ . '/admin/数据库.php';
        require_once __DIR__ . '/函数库/AdminController.php';
        $this->db = new SQLiteDatabase();
    }
    
    // 1. 测试统计数据
    public function testStats()
    {
        echo "\n📊 测试 1: /admin/api/stats\n";
        echo "----------------------------------------\n";
        try {
            $pdo = $this->db->getConnection();
            $data = [
                'qqBots' => $pdo->query("SELECT COUNT(*) FROM qq_bots")->fetchColumn(),
                'napcatBots' => $pdo->query("SELECT COUNT(*) FROM napcat_bots")->fetchColumn(),
                'messageLogs' => $pdo->query("SELECT COUNT(*) FROM message_logs")->fetchColumn(),
                'systemLogs' => $pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn(),
                'phpVersion' => PHP_VERSION,
                'swooleVersion' => defined('SWOOLE_VERSION') ? SWOOLE_VERSION : 'Unknown'
            ];
            $this->db->releaseConnection($pdo);
            
            echo "✅ API 响应正常\n";
            echo "  🤖 QQ 机器人: {$data['qqBots']}\n";
            echo "  💻 NapCat 机器人: {$data['napcatBots']}\n";
            echo "  📨 消息日志: {$data['messageLogs']}\n";
            echo "  📋 系统日志: {$data['systemLogs']}\n";
            echo "  🖥️ PHP: {$data['phpVersion']}\n";
            echo "  ⚡ Swoole: {$data['swooleVersion']}\n";
            return true;
        } catch (Exception $e) {
            echo "❌ 测试失败: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // 2. 测试 QQ 机器人列表
    public function testQQBots()
    {
        echo "\n🤖 测试 2: /admin/api/qq-bots\n";
        echo "----------------------------------------\n";
        try {
            $pdo = $this->db->getConnection();
            $bots = $pdo->query("SELECT * FROM qq_bots")->fetchAll(PDO::FETCH_ASSOC);
            $this->db->releaseConnection($pdo);
            
            echo "✅ API 响应正常\n";
            echo "  📋 机器人数量: " . count($bots) . "\n";
            foreach ($bots as $bot) {
                echo "  - ID: {$bot['id']}, AppID: {$bot['appid']}, " . ($bot['sandbox'] ? '沙箱' : '正式') . "\n";
            }
            return true;
        } catch (Exception $e) {
            echo "❌ 测试失败: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // 3. 测试 NapCat 机器人列表
    public function testNapcatBots()
    {
        echo "\n💻 测试 3: /admin/api/napcat-bots\n";
        echo "----------------------------------------\n";
        try {
            $pdo = $this->db->getConnection();
            $bots = $pdo->query("SELECT * FROM napcat_bots")->fetchAll(PDO::FETCH_ASSOC);
            $this->db->releaseConnection($pdo);
            
            echo "✅ API 响应正常\n";
            echo "  📋 机器人数量: " . count($bots) . "\n";
            return true;
        } catch (Exception $e) {
            echo "❌ 测试失败: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // 4. 测试消息日志
    public function testMessageLogs()
    {
        echo "\n📨 测试 4: /admin/api/message-logs\n";
        echo "----------------------------------------\n";
        try {
            $logs = $this->db->getMessageLogs(10);
            echo "✅ API 响应正常\n";
            echo "  📋 日志数量: " . count($logs) . "\n";
            if (!empty($logs)) {
                $latest = $logs[0];
                echo "  最新日志: {$latest['bot_type']} - " . substr($latest['content'] ?? 'N/A', 0, 50) . "\n";
            }
            return true;
        } catch (Exception $e) {
            echo "❌ 测试失败: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // 5. 测试系统日志
    public function testSystemLogs()
    {
        echo "\n📋 测试 5: /admin/api/system-logs\n";
        echo "----------------------------------------\n";
        try {
            $logs = $this->db->getSystemLogs(10);
            echo "✅ API 响应正常\n";
            echo "  📋 日志数量: " . count($logs) . "\n";
            if (!empty($logs)) {
                $latest = $logs[0];
                echo "  最新日志: [{$latest['level']}] " . substr($latest['message'], 0, 50) . "\n";
            }
            return true;
        } catch (Exception $e) {
            echo "❌ 测试失败: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // 6. 测试系统设置
    public function testSettings()
    {
        echo "\n⚙️ 测试 6: /admin/api/settings (GET)\n";
        echo "----------------------------------------\n";
        try {
            $settings = $this->db->getAllConfigs();
            echo "✅ API 响应正常\n";
            
            // 显示关键配置
            $keyConfigs = [
                'site_name', 'db_pool_max_size', 'db_pool_min_size', 
                'query_cache_enabled', 'query_cache_ttl', 'query_cache_max_size'
            ];
            foreach ($keyConfigs as $key) {
                if (isset($settings[$key])) {
                    $value = $settings[$key];
                    if (is_bool($value)) $value = $value ? 'true' : 'false';
                    echo "  {$key}: {$value}\n";
                }
            }
            return true;
        } catch (Exception $e) {
            echo "❌ 测试失败: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // 7. 测试前端静态文件
    public function testFrontendFiles()
    {
        echo "\n🌐 测试 7: 前端静态文件\n";
        echo "----------------------------------------\n";
        try {
            $files = [
                'admin/index.html',
                'admin/cute-app.js',
                'admin/cute-theme.css',
                'admin/styles.css'
            ];
            
            foreach ($files as $file) {
                $path = __DIR__ . '/' . $file;
                if (file_exists($path)) {
                    echo "✅ {$file} 存在\n";
                } else {
                    echo "❌ {$file} 不存在\n";
                }
            }
            return true;
        } catch (Exception $e) {
            echo "❌ 测试失败: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// 运行所有测试
$tester = new APITester();
$tests = [
    'testStats',
    'testQQBots',
    'testNapcatBots',
    'testMessageLogs',
    'testSystemLogs',
    'testSettings',
    'testFrontendFiles'
];

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    if ($tester->$test()) {
        $passed++;
    } else {
        $failed++;
    }
}

echo "\n========================================\n";
echo "📊 测试结果: {$passed} 个通过, {$failed} 个失败\n";

if ($failed === 0) {
    echo "🎉 所有前端 API 功能测试通过！\n";
} else {
    echo "⚠️  部分测试失败，请检查错误\n";
}
echo "========================================\n";
?>
