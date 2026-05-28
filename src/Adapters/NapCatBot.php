<?php
declare(strict_types=1);

namespace ShengBot\Adapters;

use ShengBot\Protobuf\Serializer;
use ShengBot\Protobuf\Deserializer;
use ShengBot\Core\PluginLoader;
use function Swoole\Coroutine\Http\post;

class NapCatBot extends BaseAdapter
{
    public int $信息SEQ = 0;
    public string $用户权限 = '';
    public object $消息内容;
    public array $原始消息 = [];
    public string $群名称 = '';
    public string $框架类型 = 'napcat';

    public function 主入口(\Swoole\Http\Request $请求, \Swoole\Http\Response $响应): void
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
        $this->当前账号 = $当前账号;

        $响应->status(200);
        $响应->end(json_encode(['msg' => 'ok']));

        $this->异步处理($解析, function (array $解析) use ($qq) {
            $this->处理($解析, $qq);
        });
    }

    public function 处理(array $解析, string $机器人QQ): void
    {
        if (($解析['post_type'] ?? '') !== 'message') return;

        switch ($解析["message_type"]) {
            case "group":
                $this->来源ID = (string)$解析["group_id"];
                $this->信息ID = (string)$解析["message_id"];
                $this->信息SEQ = $解析["message_seq"] ?? 0;
                $this->用户ID = (string)$解析["sender"]["user_id"];
                $this->用户权限 = $解析["sender"]["role"] ?? 'member';
                $this->用户信息 = $解析["sender"];
                $this->原始消息 = $解析["message"] ?? [];
                $this->消息内容 = $this->解析消息($this->原始消息);
                $this->群名称 = $解析["group_name"] ?? '';
                $this->事件类型 = "群消息";
                $this->打印终端($机器人QQ);
                $this->加载插件();
                break;

            case "private":
                $this->来源ID = (string)$解析["user_id"];
                $this->信息ID = (string)$解析["message_id"];
                $this->用户ID = (string)$解析["sender"]["user_id"];
                $this->用户信息 = $解析["sender"];
                $this->原始消息 = $解析["message"] ?? [];
                $this->消息内容 = $this->解析消息($this->原始消息);
                $this->群名称 = '';
                $this->事件类型 = "私聊消息";
                $this->打印终端($机器人QQ);
                $this->加载插件();
                break;
        }
    }

    public function 打印终端(string $机器人QQ): void
    {
        $群号 = $this->来源ID;
        $群名 = $this->群名称 ?: $群号;
        $发言人 = $this->用户信息['nickname'] ?? $this->用户ID;
        $消息 = $this->消息内容->完整文本 ?? '';

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

    private function 加载插件(): void
    {
        PluginLoader::加载(__DIR__ . '/../../插件/猫猫', $this, $this->logger);
    }

    public function 解析消息(array $消息数组): object
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
                    $结果['艾特列表'][] = [
                        'qq' => $数据['qq'] ?? '',
                        'name' => $数据['name'] ?? ''
                    ];
                    $结果['完整文本'] .= '@' . ($数据['name'] ?? $数据['qq'] ?? '');
                    break;
                case 'image':
                    $结果['图片列表'][] = [
                        'file' => $数据['file'] ?? '',
                        'url' => $数据['url'] ?? '',
                        'file_id' => $数据['file_id'] ?? '',
                        'file_size' => $数据['file_size'] ?? 0,
                        'file_unique' => $数据['file_unique'] ?? ''
                    ];
                    $结果['完整文本'] .= '[图片]';
                    break;
                case 'video':
                    $结果['视频列表'][] = [
                        'file' => $数据['file'] ?? '',
                        'url' => $数据['url'] ?? '',
                        'file_id' => $数据['file_id'] ?? ''
                    ];
                    $结果['完整文本'] .= '[视频]';
                    break;
                case 'record':
                    $结果['语音列表'][] = [
                        'file' => $数据['file'] ?? '',
                        'url' => $数据['url'] ?? '',
                        'file_id' => $数据['file_id'] ?? ''
                    ];
                    $结果['完整文本'] .= '[语音]';
                    break;
                case 'file':
                    $结果['文件列表'][] = [
                        'file' => $数据['file'] ?? '',
                        'file_id' => $数据['file_id'] ?? '',
                        'file_size' => $数据['file_size'] ?? 0,
                        'file_url' => $数据['url'] ?? ''
                    ];
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
        return $this->是否艾特((string)$this->当前账号['qq']);
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

    public function 发送群消息(int|string $群号, string|array $消息, ?int $回复ID = null): ?array
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

    public function 发送私聊消息(int|string $QQ, string|array $消息): ?array
    {
        return $this->调用API('send_private_msg', [
            'user_id' => $QQ,
            'message' => $this->构建消息($消息)
        ]);
    }

    public function 回复(string|array $消息): ?array
    {
        if ($this->事件类型 === '私聊消息') {
            return $this->发送私聊消息($this->来源ID, $消息);
        }
        return $this->发送群消息($this->来源ID, $消息, (int)$this->信息ID);
    }

    private function 构建消息(string|array $内容): array
    {
        if (is_string($内容)) {
            return [['type' => 'text', 'data' => ['text' => $内容]]];
        }
        return $内容;
    }

    public function 调用API(string $接口, array $参数): ?array
    {
        $url = $this->当前账号['http_url'] . '/' . $接口;
        try {
            $响应 = post($url, json_encode($参数), null, [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . ($this->当前账号['token'] ?? '')
            ]);
            return json_decode($响应->getBody(), true);
        } catch (\Throwable $e) {
            $this->logger->error("[API调用失败] {$接口}: " . $e->getMessage());
            return null;
        }
    }

    public function 发包(string $cmd, string|array $pb): ?string
    {
        try {
            $jsonData = is_string($pb) ? json_decode($pb, true) : $pb;
            $serializedData = Serializer::serializeJsonToProtobuf($jsonData);
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
            $input = $result["data"] ?? null;
            if ($input === null) return null;
            $binaryData = hex2bin($input);
            $deserializedData = Deserializer::deserialize($binaryData);
            $jsonReadyData = convertForJson($deserializedData);
            return json_encode($jsonReadyData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            $this->logger->error("[发包失败] {$cmd}: " . $e->getMessage());
            return null;
        }
    }

    public function 消息段(string $类型, mixed $数据, mixed $额外 = null): array
    {
        return match($类型) {
            '文本' => ["type" => "text", "data" => ["text" => $数据]],
            '图片' => ["type" => "image", "data" => ["file" => $数据]],
            '视频' => ["type" => "video", "data" => ["file" => $数据]],
            '艾特', '@' => ["type" => "at", "data" => ["qq" => $数据]],
            '回复' => ["type" => "reply", "data" => ["id" => $数据]],
            '表情' => ["type" => "face", "data" => ["id" => $数据]],
            '音乐' => ["type" => "music", "data" => ["type" => (string)$数据, "id" => (string)$额外]],
            '卡片' => ["type" => "json", "data" => ["data" => $数据]],
            default => throw new \Exception("未知类型: {$类型}")
        };
    }

    public function 伪造(int|string $QQ, string $name, mixed ...$msgs): array
    {
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

    public function 群伪造(int|string $group, mixed ...$msgs): ?array
    {
        $json = [
            "group_id" => $group,
            "messages" => []
        ];
        foreach ($msgs as $msg) {
            $json["messages"][] = $msg;
        }
        return $this->调用API("send_group_forward_msg", $json);
    }
}
