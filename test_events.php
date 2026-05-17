<?php
require_once 'admin/数据库.php';

// 使用 Swoole 协程运行
\Swoole\Coroutine\run(function() {
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
        
        // 测试参数
        $senderId = '123456789';
        $groupId = '987654321';
        $guildId = 'test_guild_1';
        $channelId = 'test_channel_1';
        $content = '你好，这是一条测试消息！';
        $plainToken = 'test_plain_token';
        $eventTs = (string)$timestamp;
        
        // 根据不同事件类型构造数据
        switch ($eventType) {
            case 'C2C_MESSAGE_CREATE':
                $event['d'] = [
                    'author' => ['id' => $senderId, 'username' => '测试用户' . $senderId],
                    'content' => $content,
                    'id' => 'test_msg_' . uniqid(),
                    'timestamp' => date('c')
                ];
                break;
            case 'GROUP_AT_MESSAGE_CREATE':
                $event['d'] = [
                    'author' => ['id' => $senderId, 'username' => '测试用户' . $senderId],
                    'content' => '<@!' . $bot['appid'] . '> ' . $content,
                    'group_openid' => $groupId,
                    'group_id' => $groupId,
                    'id' => 'test_group_msg_' . uniqid(),
                    'timestamp' => date('c')
                ];
                break;
            case 'DIRECT_MESSAGE_CREATE':
                $event['d'] = [
                    'author' => ['id' => $senderId, 'username' => '测试用户' . $senderId],
                    'content' => $content,
                    'guild_id' => $guildId,
                    'channel_id' => $channelId,
                    'id' => 'test_dm_msg_' . uniqid(),
                    'timestamp' => date('c')
                ];
                break;
            case 'AT_MESSAGE_CREATE':
                $event['d'] = [
                    'author' => ['id' => $senderId, 'username' => '测试用户' . $senderId],
                    'content' => '<@!' . $bot['appid'] . '> ' . $content,
                    'channel_id' => $channelId,
                    'guild_id' => $guildId,
                    'id' => 'test_at_msg_' . uniqid(),
                    'timestamp' => date('c')
                ];
                break;
            case 'MESSAGE_CREATE':
                $event['d'] = [
                    'author' => ['id' => $senderId, 'username' => '测试用户' . $senderId],
                    'content' => $content,
                    'channel_id' => $channelId,
                    'guild_id' => $guildId,
                    'id' => 'test_msg_' . uniqid(),
                    'timestamp' => date('c')
                ];
                break;
            case 'FRIEND_ADD':
            case 'FRIEND_DEL':
                $event['d'] = [
                    'openid' => $senderId
                ];
                break;
            case 'GROUP_ADD_ROBOT':
                $event['d'] = [
                    'group_openid' => $groupId
                ];
                break;
            case 'GROUP_DEL_ROBOT':
                $event['d'] = [
                    'group_id' => $groupId,
                    'id' => $groupId
                ];
                break;
            case 'GUILD_CREATE':
            case 'GUILD_DELETE':
                $event['d'] = [
                    'id' => $guildId
                ];
                break;
            case 'INTERACTION_CREATE':
                $event['d'] = [
                    'scene' => 'group',
                    'group_openid' => $groupId,
                    'user_openid' => $senderId
                ];
                break;
            case 'MESSAGE_REACTION_ADD':
            case 'MESSAGE_REACTION_REMOVE':
                $event['d'] = [
                    'user_id' => $senderId,
                    'channel_id' => $channelId,
                    'guild_id' => $guildId
                ];
                break;
            case 'AUTH_OP_13':
                $event = [
                    'op' => 13,
                    't' => 'READY',
                    'id' => 'test_auth_' . uniqid(),
                    'd' => [
                        'plain_token' => $plainToken,
                        'event_ts' => $eventTs
                    ]
                ];
                break;
            default:
                $event['d'] = [
                    'id' => 'test_data_' . uniqid(),
                    'timestamp' => date('c'),
                    'author' => ['id' => $senderId, 'username' => '测试用户']
                ];
        }
        
        // 记录消息日志
        $contentLog = isset($event['d']['content']) ? $event['d']['content'] : "[$eventType] 事件";
        $openid = 'test_openid';
        if (isset($event['d']['openid'])) $openid = $event['d']['openid'];
        if (isset($event['d']['user_openid'])) $openid = $event['d']['user_openid'];
        if (isset($event['d']['author']) && isset($event['d']['author']['id'])) $openid = $event['d']['author']['id'];
        
        $logGroupId = null;
        if (in_array($eventType, ['GROUP_AT_MESSAGE_CREATE', 'GROUP_ADD_ROBOT', 'GROUP_DEL_ROBOT'])) {
            $logGroupId = $groupId;
        } elseif (in_array($eventType, ['DIRECT_MESSAGE_CREATE', 'AT_MESSAGE_CREATE', 'MESSAGE_CREATE', 'GUILD_CREATE', 'GUILD_DELETE'])) {
            $logGroupId = $channelId;
        }
        
        if (in_array($eventType, ['C2C_MESSAGE_CREATE', 'GROUP_AT_MESSAGE_CREATE', 'DIRECT_MESSAGE_CREATE', 'AT_MESSAGE_CREATE', 'MESSAGE_CREATE'])) {
            $db->addMessageLog('qq', $bot['appid'], $openid, $logGroupId, 'text', $contentLog);
        }
        $db->addSystemLog('info', "测试事件: $eventType", ['bot_id' => $bot['id'], 'event_type' => $eventType]);
        
        // 和管理后台一致的调用方式：使用主入口
        $qqBotConfig = [$bot];
        
        // 创建一个临时请求对象
        $mockRequest = new class($bot['appid'], $event) {
            public $header = [];
            private $rawContent;
            
            public function __construct($appId, $event) {
                $this->header['x-bot-appid'] = $appId;
                $this->rawContent = json_encode($event, JSON_UNESCAPED_UNICODE);
            }
            
            public function rawContent() {
                return $this->rawContent;
            }
            
            public function getMethod() {
                return 'POST';
            }
        };
        
        $mockResponse = new class {
            public $headers = [];
            public $statusCode = 200;
            public $content = '';
            
            public function status($code) {
                $this->statusCode = $code;
            }
            
            public function header($key, $value) {
                $this->headers[$key] = $value;
            }
            
            public function end($content) {
                $this->content = $content;
            }
        };
        
        // 调用机器人处理
        ob_start();
        if ($eventType === 'AUTH_OP_13') {
            // 对于鉴权事件，直接调用鉴权方法
            $authResult = $botHandler->鉴权($event);
            echo "✅ 成功！(鉴权响应: " . substr($authResult, 0, 80) . ")\n";
        } else {
            // 调用主入口
            $botHandler->主入口($mockRequest, $mockResponse);
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
});
?>