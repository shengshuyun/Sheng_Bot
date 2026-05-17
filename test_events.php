<?php
require_once 'admin/数据库.php';

$db = new SQLiteDatabase();
$pdo = $db->getConnection();

echo "🎯 开始测试所有事件类型...\n\n";

// 模拟测试请求 (直接调用内部函数)
$testEvents = [
    'C2C_MESSAGE_CREATE',
    'GROUP_AT_MESSAGE_CREATE',
    'DIRECT_MESSAGE_CREATE',
    'AT_MESSAGE_CREATE',
    'MESSAGE_CREATE',
    'FRIEND_ADD',
    'FRIEND_DEL',
    'GROUP_ADD_ROBOT',
    'GROUP_DEL_ROBOT',
    'GUILD_CREATE',
    'GUILD_DELETE',
    'INTERACTION_CREATE',
    'MESSAGE_REACTION_ADD',
    'MESSAGE_REACTION_REMOVE',
    'AUTH_OP_13'
];

$testBotId = 1; // 使用第一个QQ机器人

echo "📋 将要测试 " . count($testEvents) . " 个事件...\n\n";

// 首先，查看一下事件处理的核心代码
require_once '函数库/模块/官方QQ机器人.php';

// 获取机器人 - 直接使用PDO查询
$stmt = $pdo->prepare("SELECT * FROM qq_bots LIMIT 1");
$stmt->execute();
$testBot = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($testBot)) {
    die("❌ 没有配置的QQ机器人！请先在后台添加一个\n");
}

echo "🤖 使用测试机器人: appid={$testBot['appid']}\n\n";

echo "----------------------------------------------------------------------\n";
echo "📝 开始测试各个事件类型...\n";
echo "----------------------------------------------------------------------\n\n";

$passed = 0;
$failed = 0;

// 测试函数：直接调用官方QQ机器人的处理函数
function testEvent($eventType, $bot, $db, $pdo) {
    echo "📥 测试: $eventType... ";
    try {
        // 初始化机器人（传入配置列表）
        $botHandler = new 官方QQ机器人([$bot]);
        
        // 构建测试事件
        $timestamp = time() * 1000;
        $event = [
            'id' => 'test_event_' . uniqid(),
            'd' => [],
            't' => $eventType,
            'op' => 0
        ];
        
        // 根据不同事件类型构造数据
        switch ($eventType) {
            case 'C2C_MESSAGE_CREATE':
                $event['d'] = [
                    'author' => ['id' => '123456789', 'user_openid' => 'test_openid_1'],
                    'content' => '你好，这是一条测试私聊消息',
                    'id' => 'test_msg_1',
                    'timestamp' => $timestamp
                ];
                break;
            case 'GROUP_AT_MESSAGE_CREATE':
                $event['d'] = [
                    'author' => ['id' => '987654321', 'member_openid' => 'test_group_openid_1'],
                    'content' => '@机器人 你好，这是一条测试群消息',
                    'id' => 'test_group_msg_1',
                    'group_id' => 'test_group_1',
                    'timestamp' => $timestamp
                ];
                break;
            case 'AUTH_OP_13':
                $event = [
                    'op' => 13,
                    'd' => [
                        'version' => 1,
                        'client_type' => 1,
                        'code' => 'test_auth_code',
                        'session_type' => 0,
                        'ticket' => 'test_ticket',
                        'event_ts' => (string)$timestamp,
                        'plain_token' => 'test_plain_token'
                    ]
                ];
                break;
            default:
                $event['d'] = [
                    'id' => 'test_data_' . uniqid(),
                    'timestamp' => $timestamp,
                    'author' => ['id' => 'test_user']
                ];
        }
        
        // 记录消息日志
        $content = isset($event['d']['content']) ? $event['d']['content'] : "[$eventType] 事件";
        $openid = 'test_openid';
        if (isset($event['d']['author']['user_openid'])) $openid = $event['d']['author']['user_openid'];
        if (isset($event['d']['author']['member_openid'])) $openid = $event['d']['author']['member_openid'];
        
        $db->addMessageLog('qq_official', $bot['id'], $openid, null, $eventType, $content);
        $db->addSystemLog('info', "测试事件: $eventType", ['bot_id' => $bot['id'], 'event_type' => $eventType]);
        
        // 设置当前账号
        $botHandler->当前账号 = $bot;
        
        // 调用处理函数（如果是鉴权事件，直接调用鉴权方法）
        ob_start();
        if (isset($event['op']) && $event['op'] === 13) {
            $结果 = $botHandler->鉴权($event);
            echo "✅ 成功！(鉴权响应: " . substr($结果, 0, 50) . ")\n";
        } else {
            $botHandler->处理($event);
            echo "✅ 成功！\n";
        }
        $output = ob_get_clean();
        
        return true;
    } catch (Exception $e) {
        echo "❌ 失败: " . $e->getMessage() . "\n";
        return false;
    }
}

// 运行所有测试
foreach ($testEvents as $eventType) {
    $result = testEvent($eventType, $testBot, $db, $pdo);
    if ($result) $passed++;
    else $failed++;
}

echo "\n----------------------------------------------------------------------\n";
echo "📊 测试结果: ";
echo "✅ 通过: $passed, ❌ 失败: $failed\n";
echo "----------------------------------------------------------------------\n\n";

// 查看最近的系统日志
echo "📜 查看最近的系统日志:\n";
$stmt = $pdo->prepare("SELECT * FROM system_logs ORDER BY id DESC LIMIT 10");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($logs as $log) {
    echo "[$log[created_at]] [$log[level]] $log[message]\n";
}
echo "\n";

echo "✅ 所有事件测试完成！\n";
?>