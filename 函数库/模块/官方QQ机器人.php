<?php
require_once __DIR__ . '/sqlite_db.php';
require_once __DIR__ . '/../../admin/数据库.php';

use Swoole\Coroutine as Co;
use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;
use function Swoole\Coroutine\Http\get;
use function Swoole\Coroutine\Http\post;
use function Swoole\Coroutine\Http\request;
use Swoole\Coroutine\Http\Client;
use Swoole\Timer;

class 官方QQ机器人 {
    public $配置信息;
    public $当前账号;
    public $来源ID;
    public $信息ID;
    public $事件ID;
    public $用户ID;
    public $用户信息;
    public $事件类型;
    public $令牌信息;
    public $按钮来源;
    public $公共访问头 = [];
    private ?数据库 $数据库实例 = null;
    private string $数据库路径;
    public $响应;
    public $预返回信息;

    public function __construct($配置信息)
    {
        $this->配置信息 = $配置信息;
        $this->数据库路径 = __DIR__ . '/../../数据/数据库';
    }

    public function 获取API地址(): string
    {
        // 检查当前账号是否设置为沙箱模式
        if (!empty($this->当前账号['sandbox']) && $this->当前账号['sandbox'] === true) {
            return 'https://sandbox.api.sgroup.qq.com';
        }
        return 'https://api.sgroup.qq.com';
    }

    public function 数据库(string $操作, string $路径, mixed $数据 = null): mixed
    {
        // 延迟实例化（单例）
        if ($this->数据库实例 === null) {
            $this->数据库实例 = new 数据库($this->数据库路径);
        }
        
        // 直接调用数据库实例的 __invoke 方法
        return ($this->数据库实例)($操作, $路径, $数据);
    }

    private function 记录日志($消息类型, $内容, $用户ID = null, $群ID = null)
    {
        try {
            $db = new SQLiteDatabase();
            $pdo = $db->getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO message_logs (bot_type, bot_id, user_id, group_id, message_type, content, created_at)
                VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
            ");
            $stmt->execute([
                'qq',
                $this->当前账号['appid'] ?? '',
                $用户ID,
                $群ID,
                $消息类型,
                is_array($内容) ? json_encode($内容, JSON_UNESCAPED_UNICODE) : $内容
            ]);
        } catch (Throwable $e) {
            // 忽略日志记录错误
        }
    }

    public function 主入口($请求, $响应)
    {
        $this->响应 = $响应;
        $appId = $请求->header['x-bot-appid'];
        $当前账号 = null;
        foreach ($this->配置信息 as $账号) {
            if ($账号['appid'] == $appId) {
                $当前账号 = $账号;
                break;
            }
        }
        if (!$当前账号) return;

        $正文 = $请求->rawContent();
        $解析 = json_decode($正文, true);

        $this->当前账号 = $当前账号;
        $this->公共访问头 = array(
            "Content-Type"  => "application/json",
            "Authorization" => "QQBot " . $this->获取令牌(),
            "X-Union-Appid" => $this->当前账号["appid"]
        );

        // op13 鉴权事件必须同步返回
        if (($解析['op'] ?? -1) === 13) {
            $响应->header('Content-Type', 'application/json');
            $响应->status(200);
            $响应->end($this->鉴权($解析));
            return;
        }

        if ($解析["t"] == "ShengBot_MSG") {

        } else {
            $响应->status(200);
            $响应->end(json_encode(['msg' => 'ok']));
        }

        go(function () use ($解析) {
            $this->处理($解析);
        });
    }



    public function 处理($解析)
    {
        //$解析 = json_decode($数据, true);
        //print_r($this->当前账号) . PHP_EOL;
        //print_r($解析) . PHP_EOL;

        $this->事件类型 = $解析["t"];
        switch ($解析["op"]) {
            case 0:
                switch ($解析["t"]) {
                    // 好友事件
                    case "FRIEND_ADD":
                        $this->事件ID = $解析["id"];
                        $this->来源ID = $解析["d"]["openid"];
                        break;

                    case "FRIEND_DEL":
                        break;

                    // 群组事件
                    case "GUILD_CREATE":
                    case "GROUP_ADD_ROBOT":
                        $this->事件ID = $解析["id"];
                        $this->来源ID = $解析["d"]["group_openid"];
                        break;

                    case "GUILD_DELETE":
                    case "GROUP_DEL_ROBOT":
                        $groupId = $解析["d"]["id"] ?? $解析["d"]["group_id"] ?? null;
                        break;

                    case "C2C_MESSAGE_CREATE":          // C2C 单聊
                        $this->来源ID   = $解析["d"]["author"]["id"];
                        $this->信息ID   = $解析["d"]["id"];
                        $this->事件ID   = $解析["id"];
                        $this->用户ID   = $解析["d"]["author"]["id"];
                        $this->用户信息 = ltrim($解析["d"]["content"], '/');
                        echo "[单聊信息]{$this->用户ID}: {$this->用户信息}\n";
                        break;

                    case "DIRECT_MESSAGE_CREATE":       // 私信
                        $this->用户ID   = $解析["d"]["author"]["id"];
                        $this->用户信息 = ltrim($解析["d"]["content"], '/');
                        $this->来源ID   = $解析["d"]["guild_id"];   // 来源频道
                        $this->信息ID   = $解析["d"]["id"];
                        $this->事件ID   = $解析["id"];
                        echo "[频道私信]{$this->用户ID}: {$this->用户信息}\n";
                        break;

                    case "GROUP_AT_MESSAGE_CREATE":     // QQ 群内 @机器人
                        $this->用户ID   = $解析["d"]["author"]["id"];
                        $this->用户信息 = ltrim(trim($解析["d"]["content"]), '/');
                        $this->来源ID   = $解析["d"]["group_id"];
                        $this->信息ID   = $解析["d"]["id"];
                        $this->事件ID   = $解析["id"];
                        echo "[群聊信息]{$this->用户ID}: {$this->用户信息}\n";
                        break;

                    case "AT_MESSAGE_CREATE":           // 频道内 @机器人
                        print_r($解析);
                        $this->用户ID   = $解析["d"]["author"]["id"];
                        $content = $解析["d"]["content"];
                        $纯文本 = preg_replace('/^<@!\d+>\s*/', '', $content);
                        $this->用户信息 = ltrim($纯文本, '/');
                        $this->来源ID   = $解析["d"]["channel_id"];
                        $this->信息ID   = $解析["d"]["id"];
                        $this->事件ID   = $解析["id"];
                        echo "[频道@信息]{$this->来源ID}: {$this->用户信息}\n";
                        break;

                    case "INTERACTION_CREATE":
                        $this->事件ID   = $解析["id"];
                        $this->按钮来源 = $解析["d"]["scene"];
                        switch ($解析["d"]["scene"]) {
                            case "c2c":
                                $this->来源ID   = $解析["d"]["user_openid"];
                                break;
                            case "group":
                                $this->来源ID   = $解析["d"]["group_openid"];
                                break;
                        }
                        $this->数据库("写", "无限主动ID/{$this->来源ID}/被动ID", $this->事件ID);
                        break;

                    case "ShengBot_MSG":
                        $this->按钮来源 = "group";
                        $this->用户ID = $解析["d"]["user_id"];
                        $this->用户信息 = $解析["d"]["message"];
                        $this->来源ID = $解析["d"]["group_id"];
                        $this->事件ID = $this->数据库("读", "无限主动ID/{$this->来源ID}/被动ID");
                        break;

                    case "MESSAGE_CREATE":              // 频道内普通消息
                        print_r($解析);
                        $this->用户ID   = $解析["d"]["author"]["id"];
                        $this->用户信息 = ltrim(trim($解析["d"]["content"]), '/');
                        $this->来源ID   = $解析["d"]["channel_id"];
                        $this->信息ID   = $解析["d"]["id"];
                        $this->事件ID   = $解析["id"];
                        echo "[频道信息]{$this->来源ID}｜{$this->用户ID}: {$this->用户信息}\n";
                        break;

                    case "MESSAGE_REACTION_ADD":
                    case "MESSAGE_REACTION_REMOVE":
                        // 如有需要可扩展
                        break;

                    default:
                        echo "OP0未兼容事件" . PHP_EOL;
                        print_r($解析);
                        break;
                }
                break;

            default:
                return "Zzzz...ing";
        }
        $插件列表 = [];
        $目录迭代器 = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__ . '/../../插件/官方', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($目录迭代器 as $文件信息) {
            if ($文件信息->isFile() && $文件信息->getExtension() === 'php') {
                $插件列表[] = $文件信息->getPathname();
            }
        }
        foreach ($插件列表 as $文件路径) {
            require $文件路径;
        }
        return '';
    }

    public function 获取令牌()
    {
        if (!empty($this->令牌信息['access_token']) && !empty($this->令牌信息['token_expires_at']) && $this->令牌信息['token_expires_at'] > time() + 60) {
            return $this->令牌信息['access_token'];
        }
        return $this->刷新令牌();
    }

    private function 刷新令牌()
    {
        $appId  = $this->当前账号["appid"];
        $secret = $this->当前账号["secret"];
        $body = json_encode([
            'appId'        => "$appId",
            'clientSecret' => $secret,
        ]);

        $响应 = post('https://bots.qq.com/app/getAppAccessToken', $body, null, ['Content-Type' => 'application/json']);

        $令牌数组 = json_decode($响应->getBody(), true);
        $this->令牌信息 = $令牌数组;
        return $令牌数组["access_token"];
    }

    public function 鉴权($数据)
    {
        $种子 = $this->当前账号["secret"];
        while (strlen($种子) < SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            $种子 .= $种子;
        }
        $私钥 = sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(substr($种子, 0, SODIUM_CRYPTO_SIGN_SEEDBYTES)));
        $签名 = bin2hex(sodium_crypto_sign_detached($数据['d']['event_ts'] . $数据['d']['plain_token'], $私钥));
        $输出签名json = json_encode(['plain_token' => $数据['d']['plain_token'],'signature' => $签名]);
        return $输出签名json;
    }

    private function 上传文件($文件url, $文件类型)
    {
        $API地址 = $this->获取API地址();
        if ($this->事件类型 == "MESSAGE_CREATE" || $this->事件类型 == "AT_MESSAGE_CREATE") {
            $url = "{$API地址}/channel_id/{$this->来源ID}/files";
        }
        if (in_array($this->事件类型, ["C2C_MESSAGE_CREATE", "FRIEND_ADD", "FRIEND_DEL"])) {
            $url = "{$API地址}/v2/users/{$this->来源ID}/files";
        } else {
            $url = "{$API地址}/v2/groups/{$this->来源ID}/files";
        }

        $data = ["file_type" => $文件类型, "url" => $文件url, "srv_send_msg" => false];
        $响应 = post($url, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), null, $this->公共访问头)->getBody();
        $结果 = json_decode($响应, true);
        print_r($结果);

        return $结果['file_info'] ?? null;
    }

    public function 发送($类型, $主内容=null, $附加1=null, $附加2=null)
    {
        $API地址 = $this->获取API地址();
        switch ($类型) {
            case "文本":
                $msg_type = 0;
                $content = $主内容;
                break;
            case "MD":
            case "md":
            case "markdown":
            case "MarkDown":
                $content = "";
                if ($主内容 && !empty($主内容)) {
                    $msg_type = 2;
                    $markdown = array(
                        "markdown" => array(
                            "custom_template_id" => $主内容,
                            "params" => $附加1 ?? []
                        )
                    );
                } else {
                    $msg_type = 2;
                    $markdown = array(
                        "markdown" => array(
                            "content" => $附加1
                        )
                    );
                }
                if ($附加2 != null) {
                    $keyboard = array(
                        "keyboard" => array(
                            "id" => $附加2
                        )
                    );
                }
                break;
            case "图片":
            case "视频":
            case "语音":
                $file_type = array("图片"=>1, "视频"=>2, "语音"=>3)[$类型];
                $上传结果 = $this->上传文件($主内容, $file_type);
                if ($上传结果) {
                    $msg_type = 7; // 富媒体消息
                    $media = array(
                        "media" => array("file_info" => $上传结果),
                        "content" => $附加1
                    );
                } else {
                    return; // 上传失败就不发了
                }
                break;
            case "直发":
                $msg_type = 7; // 富媒体消息
                $media = array(
                    "media" => array("url" => $主内容),
                    "content" => $附加1
                );
                break;
            default:

                break;
        }
        switch ($this->事件类型) {
            case "GROUP_ADD_ROBOT":
            case "FRIEND_ADD":
            case "INTERACTION_CREATE":
            case "ShengBot_MSG":
                $data = [
                    "content" => $content,
                    "msg_type" => $msg_type,
                    "event_id" => $this->事件ID
                ];
                break;
            default:
                $data = [
                    "content" => $content,
                    "msg_type" => $msg_type,
                    "msg_id" => $this->信息ID,
                    "msg_seq" => rand(1, 99999)
                ];
                break;
        }

        if (isset($media)) $data = array_merge($data, $media);
        if (isset($ark)) $data = array_merge($data, $ark);
        if (isset($markdown)) $data = array_merge($data, $markdown);
        if (isset($keyboard)) $data = array_merge($data, $keyboard);
        if (isset($stream)) $data = array_merge($data, $stream);
        if (isset($message_reference)) $data = array_merge($data, $message_reference);
        print_r(PHP_EOL . PHP_EOL . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL . PHP_EOL);

        switch ($this->事件类型) {
            case "C2C_MESSAGE_CREATE":
            case "FRIEND_ADD":
            case "FRIEND_DEL":
                $url = "{$API地址}/v2/users/{$this->来源ID}/messages";
                break;
            case "GROUP_AT_MESSAGE_CREATE":
            case "GROUP_ADD_ROBOT":
            case "GROUP_DEL_ROBOT":
                $url = "{$API地址}/v2/groups/{$this->来源ID}/messages";
                break;
            case "INTERACTION_CREATE":
            case "ShengBot_MSG":
                switch ($this->按钮来源) {
                    case "c2c":
                        $url = "{$API地址}/v2/users/{$this->来源ID}/messages";
                        break;
                    case "group":
                        $url = "{$API地址}/v2/groups/{$this->来源ID}/messages";
                        break;
                }
                break;
            case "DIRECT_MESSAGE_CREATE":
                $url = "{$API地址}/dms/{$this->来源ID}/messages";
                break;
            case "AT_MESSAGE_CREATE":
            case "MESSAGE_CREATE":
                $url = "{$API地址}/channels/{$this->来源ID}/messages";
                break;
            case "GUILD_CREATE":
            case "GUILD_DELETE":
                break;
            default:
                break;
        }

        go(function () use ($url, $data) {
            $发送请求 = post($url, json_encode($data, JSON_UNESCAPED_UNICODE), null, $this->公共访问头)->getBody();
            $this->预返回信息 = $发送请求;
            if ($this->事件类型 == "ShengBot_MSG") {
                $this->响应->header('Content-Type', 'application/json');
                $this->响应->status(200);
                $this->响应->end(json_encode(['msg' => $this->预返回信息], JSON_UNESCAPED_UNICODE));
            }
            print_r("[发送 " . date('Y/m/d H:i:s'). "] ：" . $发送请求);
            echo PHP_EOL;
        });

    }

    public function unicode编码(string $str): string
    {
        return preg_replace_callback('/./u', function ($m) {
            $cp = mb_ord($m[0], 'UTF-8');   // 码位
            return sprintf('\u%04X', $cp);  // 固定 4 位，不足补 0
        }, $str);
    }

    public function 定时器(string $操作, ...$参数)
    {
        switch ($操作) {
            case '延迟':
            case 'after':
                $毫秒 = (int)($参数[0] ?? 1000);
                $回调 = $参数[1] ?? function(){};
                $定时器ID = Timer::after($毫秒, function() use ($回调, $毫秒) {
                    go(function() use ($回调, $毫秒) {
                        try {
                            $回调();
                        } catch (\Throwable $e) {
                            echo "[定时器错误] 延迟{$毫秒}ms执行失败: " . $e->getMessage() . PHP_EOL;
                        }
                    });
                });
                echo "[定时器] 已设置延迟{$毫秒}ms，ID: {$定时器ID}" . PHP_EOL;
                return $定时器ID;
    
            case '循环':
            case 'tick':
                $毫秒 = (int)($参数[0] ?? 1000);
                $回调 = $参数[1] ?? function(){};
                $定时器ID = Timer::tick($毫秒, function() use ($回调, $毫秒) {
                    go(function() use ($回调, $毫秒) {
                        try {
                            $回调();
                        } catch (\Throwable $e) {
                            echo "[定时器错误] 循环间隔{$毫秒}ms执行失败: " . $e->getMessage() . PHP_EOL;
                        }
                    });
                });
                echo "[定时器] 已设置循环间隔{$毫秒}ms，ID: {$定时器ID}" . PHP_EOL;
                return $定时器ID;
    
            case '清除':
            case 'clear':
                $定时器ID = $参数[0] ?? null;
                if ($定时器ID && Timer::clear($定时器ID)) {
                    echo "[定时器] 已清除ID: {$定时器ID}" . PHP_EOL;
                    return true;
                }
                return false;
    
            case '清除全部':
            case 'clearAll':
                Timer::clearAll();
                echo "[定时器] 已清除全部定时器" . PHP_EOL;
                return true;
    
            case '信息':
            case 'info':
                $定时器ID = $参数[0] ?? null;
                return $定时器ID ? Timer::info($定时器ID) : Timer::list();
    
            default:
                echo "[定时器] 未知操作类型: {$操作}" . PHP_EOL;
                return null;
        }
    }

    public function 流式($状态, $内容, $流ID = null, $序号 = 0, $重置 = false)
    {
        $API地址 = $this->获取API地址();
        // 状态: 1=生成中, 10=结束
        if (!in_array($this->事件类型, ["C2C_MESSAGE_CREATE", "GROUP_AT_MESSAGE_CREATE"])) {
            echo "[流式消息] 仅支持单聊" . PHP_EOL;
            return;
        }

        $stream = [
            "stream" => [
                "state" => (int)$状态,
                "id"    => $流ID,
                "index" => (int)$序号,
                "reset" => (bool)$重置
            ]
        ];

        $data = [
            "content"  => $内容,
            "msg_type" => 0,
            "msg_id"   => $this->信息ID,
            "msg_seq"  => rand(1, 99999)
        ];

        $data = array_merge($data, $stream);

        if ($this->事件类型 == "C2C_MESSAGE_CREATE") {
            $url = "{$API地址}/v2/users/{$this->来源ID}/messages";
        } else {
            $url = "{$API地址}/v2/groups/{$this->来源ID}/messages";
        }

        $响应 = post($url, json_encode($data, JSON_UNESCAPED_UNICODE), null, $this->公共访问头)->getBody();
        $结果 = json_decode($响应, true);

        echo "[流式发送 " . date('Y/m/d H:i:s') . "] 状态:{$状态} 序号:{$序号} 响应:" . $响应 . PHP_EOL;

        return $结果['id'] ?? null;
    }



}


