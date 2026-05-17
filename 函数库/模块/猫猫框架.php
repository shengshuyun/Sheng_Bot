<?php
// 在你的 猫猫框架.php 顶部添加
require_once __DIR__ . '/proto_en.php';
require_once __DIR__ . '/proto_de.php';
require_once __DIR__ . '/ini数据库.php';

use Swoole\Coroutine as Co;
use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;
use function Swoole\Coroutine\Http\get;
use function Swoole\Coroutine\Http\post;
use function Swoole\Coroutine\Http\request;
use Swoole\Coroutine\Http\Client;
use Swoole\Timer;

class 猫猫框架 {
    public $配置信息;
    public $当前账号;
    public $来源ID;
    public $信息ID;
    public $用户ID;
    public $信息SEQ;
    public $用户信息;
    public $用户权限;
    public $消息内容;
    public $原始消息;
    public $事件类型;
    public $群名称;
    public $框架类型 = 'napcat';
    private ?数据库 $数据库实例 = null;
    private string $数据库路径;

    public function __construct($配置信息)
    {
        $this->配置信息 = $配置信息;
        $this->数据库路径 = __DIR__ . '/../../数据/数据库';
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

    public function 主入口($请求, $响应)
    {
        $qq = $请求->header['x-self-id'];
        $当前账号 = null;
        foreach ($this->配置信息 as $账号) {
            if ($账号['qq'] == $qq) {
                $当前账号 = $账号;
                break;
            }
        }
        if (!$当前账号) return;

        $正文 = $请求->rawContent();
        $解析 = json_decode($正文, true);
        print_r($解析);
        $this->当前账号 = $当前账号;

        // 其它事件：先 200，再异步处理
        $响应->status(200);
        $响应->end(json_encode(['msg' => 'ok']));

        go(function () use ($解析, $qq) {
            $this->处理($解析, $qq);
        });
    }

    public function 处理($解析, $机器人QQ) {
        // 只处理消息事件
        if (($解析['post_type'] ?? '') !== 'message') return;

        switch ($解析["message_type"]) {
            case "group":
                $this->来源ID = $解析["group_id"];
                $this->信息ID = $解析["message_id"];
                $this->信息SEQ = $解析["message_seq"] ?? 0;
                $this->用户ID = $解析["sender"]["user_id"];
                $this->用户权限 = $解析["sender"]["role"] ?? 'member';
                $this->用户信息 = $解析["sender"];
                $this->原始消息 = $解析["message"] ?? [];
                $this->消息内容 = $this->解析消息($this->原始消息);
                $this->群名称 = $解析["group_name"] ?? '';
                $this->事件类型 = "群消息";
                
                // 终端格式化输出
                $this->打印终端($机器人QQ);
                
                // 调用插件
                $this->调用插件();
                break;
                
            case "private":
                $this->来源ID = $解析["user_id"];
                $this->信息ID = $解析["message_id"];
                $this->用户ID = $解析["sender"]["user_id"];
                $this->用户信息 = $解析["sender"];
                $this->原始消息 = $解析["message"] ?? [];
                $this->消息内容 = $this->解析消息($this->原始消息);
                $this->群名称 = '';
                $this->事件类型 = "私聊消息";
                
                // 终端格式化输出
                $this->打印终端($机器人QQ);
                
                // 调用插件
                $this->调用插件();
                break;
        }
    }

    /**
     * 终端格式化输出
     */
    public function 打印终端($机器人QQ) {
        $群号 = $this->来源ID;
        $群名 = $this->群名称 ?: $群号;
        $发言人 = $this->用户信息['nickname'] ?? $this->用户ID;
        $消息 = $this->消息内容->完整文本 ?? '';
        
        // 私聊显示为 私聊(对方QQ)
        if ($this->群名称 === '') {
            $群名 = "私聊({$群号})";
        }
        
        $输出 = sprintf(
            "[%s][%s][%s(%s):%s]: %s\n",
            $this->框架类型,
            $机器人QQ,
            $群名,
            $群号,
            $发言人,
            $消息
        );
        
        echo $输出;
    }

    /**
     * 调用插件
     */
    public function 调用插件() {
        $插件列表 = [];
        $目录迭代器 = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__ . '/../../插件/猫猫', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($目录迭代器 as $文件信息) {
            if ($文件信息->isFile() && $文件信息->getExtension() === 'php') {
                $插件列表[] = $文件信息->getPathname();
            }
        }
        foreach ($插件列表 as $文件路径) {
            require $文件路径;
        }
    }

    /**
     * 解析消息数组
     */
    public function 解析消息($消息数组): object
    {
        $结果 = [
            '纯文本' => '',
            '完整文本' => '',
            '图片列表' => [],
            '视频列表' => [],
            '语音列表' => [],
            '文件列表' => [],
            '艾特列表' => [],
            '表情列表' => [],
            '是否回复' => false,
            '回复消息ID' => null,
            '原始消息' => $消息数组
        ];

        foreach ($消息数组 as $段) {
            $类型 = $段['type'] ?? '';
            $数据 = $段['data'] ?? [];

            switch ($类型) {
                case 'text':
                    $结果['纯文本'] .= $数据['text'] ?? '';
                    $结果['完整文本'] .= $数据['text'] ?? '';
                    break;

                case 'at':
                    $艾特信息 = [
                        'qq' => $数据['qq'] ?? '',
                        'name' => $数据['name'] ?? ''
                    ];
                    $结果['艾特列表'][] = $艾特信息;
                    $结果['完整文本'] .= '@' . ($数据['name'] ?? $数据['qq'] ?? '');
                    break;

                case 'image':
                    $图片信息 = [
                        'file' => $数据['file'] ?? '',
                        'url' => $数据['url'] ?? '',
                        'file_id' => $数据['file_id'] ?? '',
                        'file_size' => $数据['file_size'] ?? 0,
                        'file_unique' => $数据['file_unique'] ?? ''
                    ];
                    $结果['图片列表'][] = $图片信息;
                    $结果['完整文本'] .= '[图片]';
                    break;

                case 'video':
                    $视频信息 = [
                        'file' => $数据['file'] ?? '',
                        'url' => $数据['url'] ?? '',
                        'file_id' => $数据['file_id'] ?? ''
                    ];
                    $结果['视频列表'][] = $视频信息;
                    $结果['完整文本'] .= '[视频]';
                    break;

                case 'record':
                    $语音信息 = [
                        'file' => $数据['file'] ?? '',
                        'url' => $数据['url'] ?? '',
                        'file_id' => $数据['file_id'] ?? ''
                    ];
                    $结果['语音列表'][] = $语音信息;
                    $结果['完整文本'] .= '[语音]';
                    break;

                case 'file':
                    $文件信息 = [
                        'file' => $数据['file'] ?? '',
                        'file_id' => $数据['file_id'] ?? '',
                        'file_size' => $数据['file_size'] ?? 0,
                        'file_url' => $数据['url'] ?? ''
                    ];
                    $结果['文件列表'][] = $文件信息;
                    $结果['完整文本'] .= '[文件:' . ($数据['file'] ?? '') . ']';
                    break;

                case 'reply':
                    $结果['是否回复'] = true;
                    $结果['回复消息ID'] = $数据['id'] ?? null;
                    break;

                case 'face':
                    $结果['表情列表'][] = $数据['id'] ?? 0;
                    $结果['完整文本'] .= '[表情' . ($数据['id'] ?? '') . ']';
                    break;

                case 'json':
                    $结果['完整文本'] .= '[JSON卡片]';
                    break;

                case 'xml':
                    $结果['完整文本'] .= '[XML消息]';
                    break;

                default:
                    $结果['完整文本'] .= '[' . $类型 . ']';
            }
        }

        $结果['纯文本'] = trim($结果['纯文本']);
        $结果['完整文本'] = trim($结果['完整文本']);
        
        return (object)$结果;
    }

    // ============ 快捷判断方法 ============

    public function 有图片(): bool
    {
        return count($this->消息内容->图片列表) > 0;
    }

    public function 是否艾特(string $qq): bool
    {
        foreach ($this->消息内容->艾特列表 as $艾特) {
            if ($艾特['qq'] == $qq) return true;
        }
        return false;
    }

    public function 是否被艾特(): bool
    {
        return $this->是否艾特($this->当前账号['qq']);
    }

    public function 是回复(): bool
    {
        return $this->消息内容->是否回复;
    }

    public function 以开头(string $文本): bool
    {
        return mb_strpos($this->消息内容->纯文本, $文本) === 0;
    }

    public function 包含(string $文本): bool
    {
        return mb_strpos($this->消息内容->纯文本, $文本) !== false;
    }

    public function 匹配(string $正则): ?array
    {
        if (preg_match($正则, $this->消息内容->纯文本, $匹配)) {
            return $匹配;
        }
        return null;
    }

    // ============ 消息发送方法 ============

    public function 发送群消息($群号, $消息, $回复ID = null)
    {
        $数据 = [
            'group_id' => $群号,
            'message' => $this->构建消息($消息)
        ];
        if ($回复ID) {
            $数据['reply'] = $回复ID;
        }
        return $this->调用API('send_group_msg', $数据);
    }

    public function 发送私聊消息($QQ, $消息)
    {
        return $this->调用API('send_private_msg', [
            'user_id' => $QQ,
            'message' => $this->构建消息($消息)
        ]);
    }

    public function 回复($消息)
    {
        return $this->发送群消息($this->来源ID, $消息, $this->信息ID);
    }

    private function 构建消息($内容): array
    {
        if (is_string($内容)) {
            return [['type' => 'text', 'data' => ['text' => $内容]]];
        }
        if (is_array($内容)) {
            return $内容;
        }
        return $内容;
    }

    public function 调用API($接口, $参数)
    {
        $url = $this->当前账号['http_url'] . '/' . $接口;
        echo "调用api接口" . $url . PHP_EOL;
        $响应 = post($url, json_encode($参数), null, [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . ($this->当前账号['token'] ?? '')
        ]);
        return json_decode($响应->getBody(), true);
    }

    public function 发包($cmd, $pb) {
        $jsonData = is_string($pb) ? json_decode($pb, true) : $pb;
        $serializedData = ProtobufSerializer::serializeJsonToProtobuf($jsonData);
        $hex = bin2hex($serializedData);
        $payload = json_encode([
            'cmd' => $cmd,
            'data' => $hex
        ]);
        $url = $this->当前账号['http_url'] . '/send_packet';
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . ($this->当前账号['token'] ?? '')
        ];
        
        $响应 = post($url, $payload, null, $headers);
        $result = json_decode($响应->getBody(), true);
        $input = $result["data"];
        $binaryData = hex2bin($input);
        $deserializedData = ProtobufDeserializer::deserialize($binaryData);
        $jsonReadyData = convertForJson($deserializedData);
        $json = json_encode($jsonReadyData,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json;
        if (!isset($result['data'])) {
            throw new Exception("发包失败: " . ($result['message'] ?? '未知错误'));
        }
        
    }

    public function 消息段($类型, $数据, $额外 = null) {
        return match($类型) {
            '文本' => ["type" => "text", "data" => ["text" => $数据]],
            '图片' => ["type" => "image", "data" => ["file" => $数据]],
            '视频' => ["type" => "video", "data" => ["file" => $数据]],
            '艾特', '@' => ["type" => "at", "data" => ["qq" => $数据]],
            '回复' => ["type" => "reply", "data" => ["id" => $数据]],
            '表情' => ["type" => "face", "data" => ["id" => $数据]],
            '音乐' => ["type" => "music", "data" => ["type" => (string)$数据, "id" => (string)$额外]],
            '卡片' => ["type" => "json", "data" => ["data" => $数据]],
            default => throw new Exception("未知类型: {$类型}")
        };
    }

    public function 伪造($QQ,$name,...$msgs) {
        $json = [
            "type" => "node",
            "data" => [
                "uin" => $QQ,
                "name" => $name,
                "content" => []
            ]
        ];
            foreach ($msgs as $msg) {
                $json["data"]["content"][] = $msg;
            }
            return $json;
    }

    public function 群伪造($group,...$msgs) {
        $json = [
            "group_id" => $group,
            "messages" => []
        ];
        foreach ($msgs as $msg) {
            $json["messages"][] = $msg;
        }
        return $this->调用API("send_group_forward_msg",$json);
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

}
