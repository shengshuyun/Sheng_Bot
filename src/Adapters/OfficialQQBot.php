<?php
declare(strict_types=1);

namespace ShengBot\Adapters;

use ShengBot\Core\PluginLoader;
use ShengBot\Core\HttpClientPool;

class OfficialQQBot extends BaseAdapter
{
    public string $事件ID = '';
    public string $用户昵称 = '';
    public ?array $令牌信息 = null;
    public string $按钮来源 = '';
    public string $按钮数据 = '';
    public string $按钮ID = '';
    public array $公共访问头 = [];
    public mixed $响应 = null;
    public mixed $预返回信息 = null;
    private array $发送记录 = []; // 按用户ID记录最后发送的消息ID
    private static array $tokenCache = [];

    public function 获取API地址(): string
    {
        if (!empty($this->当前账号['sandbox']) && $this->当前账号['sandbox'] === true) {
            return 'https://sandbox.api.sgroup.qq.com';
        }
        return 'https://api.sgroup.qq.com';
    }

    public function 主入口(\Swoole\Http\Request $请求, \Swoole\Http\Response $响应): void
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
        $this->公共访问头 = [
            "Content-Type"  => "application/json",
            "Authorization" => "QQBot " . $this->获取令牌(),
            "X-Union-Appid" => $this->当前账号["appid"]
        ];

        if (($解析['op'] ?? -1) === 13) {
            $响应->header('Content-Type', 'application/json');
            $响应->status(200);
            $响应->end($this->鉴权($解析));
            return;
        }

        if (($解析["t"] ?? '') !== "ShengBot_MSG") {
            $响应->status(200);
            $响应->end(json_encode(['msg' => 'ok']));
        }

        $this->异步处理($解析, function (array $解析) {
            $this->处理($解析);
        });
    }

    public function 处理(array $解析): void
    {
        $this->事件类型 = $解析["t"] ?? '';
        switch ($解析["op"] ?? -1) {
            case 0:
                switch ($this->事件类型) {
                    case "FRIEND_ADD":
                        $this->事件ID = $解析["id"];
                        $this->来源ID = $解析["d"]["openid"];
                        break;

                    case "FRIEND_DEL":
                        break;

                    case "GUILD_CREATE":
                    case "GROUP_ADD_ROBOT":
                        $this->事件ID = $解析["id"];
                        $this->来源ID = $解析["d"]["group_openid"];
                        break;

                    case "GUILD_DELETE":
                    case "GROUP_DEL_ROBOT":
                        break;

                    case "C2C_MESSAGE_CREATE":
                        $this->来源ID   = $解析["d"]["author"]["id"];
                        $this->信息ID   = $解析["d"]["id"];
                        $this->事件ID   = $解析["id"];
                        $this->用户ID   = $解析["d"]["author"]["id"];
                        $this->用户信息 = ltrim(trim($解析["d"]["content"]), '/');
                        $this->logger->info("[单聊信息]{$this->用户ID}: {$this->用户信息}");
                        break;

                    case "DIRECT_MESSAGE_CREATE":
                        $this->用户ID   = $解析["d"]["author"]["id"];
                        $this->用户信息 = ltrim($解析["d"]["content"], '/');
                        $this->来源ID   = $解析["d"]["guild_id"];
                        $this->信息ID   = $解析["d"]["id"];
                        $this->事件ID   = $解析["id"];
                        $this->logger->info("[频道私信]{$this->用户ID}: {$this->用户信息}");
                        break;

                    case "GROUP_AT_MESSAGE_CREATE":
                        $this->用户ID   = $解析["d"]["author"]["id"];
                        $this->用户信息 = ltrim(trim($解析["d"]["content"]), '/');
                        $this->来源ID   = $解析["d"]["group_id"];
                        $this->信息ID   = $解析["d"]["id"];
                        $this->事件ID   = $解析["id"];
                        $this->logger->info("[群聊信息]{$this->用户ID}: {$this->用户信息}");
                        break;

                    case "AT_MESSAGE_CREATE":
                        $this->用户ID   = $解析["d"]["author"]["id"];
                        $content = $解析["d"]["content"];
                        $纯文本 = preg_replace('/^<@!\d+>\s*/', '', $content);
                        $this->用户信息 = ltrim($纯文本, '/');
                        $this->来源ID   = $解析["d"]["channel_id"];
                        $this->信息ID   = $解析["d"]["id"];
                        $this->事件ID   = $解析["id"];
                        $this->logger->info("[频道@信息]{$this->来源ID}: {$this->用户信息}");
                        break;

                    case "INTERACTION_CREATE":
                        $this->事件ID   = $解析["id"];
                        $this->按钮来源 = $解析["d"]["scene"];
                        $this->按钮数据 = $解析["d"]["data"]["resolved"]["button_data"];
                        $this->按钮ID   = $解析["d"]["data"]["resolved"]["button_id"];
                        switch ($解析["d"]["scene"]) {
                            case "c2c":
                                $this->来源ID = $解析["d"]["user_openid"];
                                break;
                            case "group":
                                $this->来源ID = $解析["d"]["group_openid"];
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

                    case "MESSAGE_CREATE":
                        $this->用户ID   = $解析["d"]["author"]["id"];
                        $this->用户信息 = ltrim(trim($解析["d"]["content"]), '/');
                        $this->来源ID   = $解析["d"]["channel_id"];
                        $this->信息ID   = $解析["d"]["id"];
                        $this->事件ID   = $解析["id"];
                        $this->logger->info("[频道信息]{$this->来源ID}｜{$this->用户ID}: {$this->用户信息}");
                        break;

                    case "GROUP_MESSAGE_CREATE":
                        $this->用户ID   = $解析["d"]["author"]["id"];
                        $this->用户昵称 = $解析["d"]["author"]["username"] ?? '';
                        $content = $解析["d"]["content"] ?? '';
                        
                        // 检测艾特信息
                        if (preg_match('/<@([A-F0-9]+)>/i', $content, $matches)) {
                            $this->艾特用户 = $matches[1];
                            // 去除艾特标记获取纯文本
                            $纯文本 = preg_replace('/<@[A-F0-9]+>\s*/i', '', $content);
                            $this->用户信息 = ltrim(trim($纯文本), '/');
                        } else {
                            $this->艾特用户 = '';
                            $this->用户信息 = ltrim(trim($content), '/');
                        }
                        
                        $this->来源ID   = $解析["d"]["group_id"];
                        $this->信息ID   = $解析["d"]["id"];
                        $this->事件ID   = $解析["id"];
                        $this->logger->info("[群聊全量信息：{$this->来源ID}]{$this->用户昵称}: {$this->用户信息}");
                        break;

                    case "MESSAGE_REACTION_ADD":
                    case "MESSAGE_REACTION_REMOVE":
                        break;

                    default:
                        $this->logger->warning("OP0未兼容事件: " . json_encode($解析, JSON_UNESCAPED_UNICODE));
                        break;
                }
                break;

            default:
                return;
        }

        $this->logger->info("[处理] 事件:{$this->事件类型} 用户:{$this->用户ID} 内容:{$this->用户信息}");
        $this->加载插件();
    }

    private function 加载插件(): void
    {
        PluginLoader::加载(__DIR__ . '/../../插件/官方', $this, $this->logger);
    }

    public function 获取令牌(): string
    {
        $appId = $this->当前账号['appid'] ?? 0;
        $expiresAt = self::$tokenCache[$appId]['token_expires_at'] ?? 0;
        if (!empty(self::$tokenCache[$appId]['access_token'])
            && $expiresAt > time() + 60
        ) {
            return self::$tokenCache[$appId]['access_token'];
        }
        return $this->刷新令牌();
    }

    private function 刷新令牌(): string
    {
        try {
            $appId  = $this->当前账号["appid"];
            $secret = $this->当前账号["secret"];
            $body = json_encode([
                'appId'        => "$appId",
                'clientSecret' => $secret,
            ]);

            $响应 = $this->httpPost('https://bots.qq.com/app/getAppAccessToken', $body, ['Content-Type' => 'application/json']);

            $令牌数组 = json_decode($响应->getBody(), true);
            if (empty($令牌数组['access_token'])) {
                $this->logger->error("获取令牌失败: " . $响应->getBody());
                return '';
            }
            self::$tokenCache[$appId] = $令牌数组;
            return $令牌数组["access_token"];
        } catch (\Throwable $e) {
            $this->logger->error("[刷新令牌失败] " . $e->getMessage());
            return '';
        }
    }

    public function 鉴权(array $数据): string
    {
        $种子 = $this->当前账号["secret"];
        while (strlen($种子) < SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            $种子 .= $种子;
        }
        $私钥 = sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(substr($种子, 0, SODIUM_CRYPTO_SIGN_SEEDBYTES)));
        $签名 = bin2hex(sodium_crypto_sign_detached($数据['d']['event_ts'] . $数据['d']['plain_token'], $私钥));
        return json_encode(['plain_token' => $数据['d']['plain_token'], 'signature' => $签名]);
    }

    private function 上传文件(string $文件url, int $文件类型): ?string
    {
        try {
            $API地址 = $this->获取API地址();
            if ($this->事件类型 == "MESSAGE_CREATE" || $this->事件类型 == "AT_MESSAGE_CREATE") {
                $url = "{$API地址}/channel_id/{$this->来源ID}/files";
            } elseif (in_array($this->事件类型, ["C2C_MESSAGE_CREATE", "FRIEND_ADD", "FRIEND_DEL"])) {
                $url = "{$API地址}/v2/users/{$this->来源ID}/files";
            } else {
                $url = "{$API地址}/v2/groups/{$this->来源ID}/files";
            }

            $data = ["file_type" => $文件类型, "url" => $文件url, "srv_send_msg" => false];
            $响应 = $this->httpPost($url, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $this->公共访问头);
            if ($响应 === null) {
                $this->logger->error("[上传文件] HTTP请求失败");
                return null;
            }
            $body = $响应->getBody();
            $结果 = json_decode($body, true);

            if (empty($结果['file_info'])) {
                $this->logger->error("[上传文件失败] 响应: " . $body);
                return null;
            }

            return $结果['file_info'];
        } catch (\Throwable $e) {
            $this->logger->error("[上传文件失败] " . $e->getMessage());
            return null;
        }
    }

    public function 发送(string $类型, mixed $主内容 = null, mixed $附加1 = null, mixed $附加2 = null): ?string
    {
        $API地址 = $this->获取API地址();
        $content = null;
        $msg_type = 0;
        $markdown = null;
        $keyboard = null;
        $media = null;
        $ark = null;
        $stream = null;
        $message_reference = null;

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
                    $markdown = [
                        "markdown" => [
                            "custom_template_id" => $主内容,
                            "params" => $附加1 ?? []
                        ]
                    ];
                } else {
                    $msg_type = 2;
                    $markdown = [
                        "markdown" => [
                            "content" => $附加1
                        ]
                    ];
                }
                if (!empty($附加2)) {
                    if (is_array($附加2)) {
                        if (isset($附加2['content'])) {
                            $keyboard = ["keyboard" => $附加2];
                        } elseif (isset($附加2['rows'])) {
                            $keyboard = ["keyboard" => ["content" => $附加2]];
                        } else {
                            $keyboard = ["keyboard" => ["id" => (string)$附加2]];
                        }
                    } else {
                        $keyboard = ["keyboard" => ["id" => (string)$附加2]];
                    }
                }
                break;
            case "图片":
            case "视频":
            case "语音":
            case "文件":
                $file_type = ["图片" => 1, "视频" => 2, "语音" => 3, "文件" => 4][$类型];
                $上传结果 = $this->上传文件($主内容, $file_type);
                if ($上传结果) {
                    $msg_type = 7;
                    $media = [
                        "media" => ["file_info" => $上传结果],
                        "content" => $附加1
                    ];
                } else {
                    $this->发送失败通知("{$类型}上传失败，资源可能无法访问");
                    return null;
                }
                break;
            case "直发":
                $msg_type = 7;
                $media = [
                    "media" => ["url" => $主内容],
                    "content" => $附加1
                ];
                break;
            default:
                return null;
        }

        switch ($this->事件类型) {
            case "GROUP_ADD_ROBOT":
            case "FRIEND_ADD":
            case "INTERACTION_CREATE":
            case "ShengBot_MSG":
                if ($this->上次发送ID !== null) {
                    $data = [
                        "content" => $content,
                        "msg_type" => $msg_type,
                        "msg_id" => $this->上次发送ID,
                        "msg_seq" => rand(1, 99999)
                    ];
                } else {
                    $data = [
                        "content" => $content,
                        "msg_type" => $msg_type,
                        "event_id" => $this->事件ID
                    ];
                }
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

        if ($media !== null) $data = array_merge($data, $media);
        if ($ark !== null) $data = array_merge($data, $ark);
        if ($markdown !== null) $data = array_merge($data, $markdown);
        if ($keyboard !== null) $data = array_merge($data, $keyboard);
        if ($stream !== null) $data = array_merge($data, $stream);
        if ($message_reference !== null) $data = array_merge($data, $message_reference);

        $url = match($this->事件类型) {
            "C2C_MESSAGE_CREATE", "FRIEND_ADD", "FRIEND_DEL"
                => "{$API地址}/v2/users/{$this->来源ID}/messages",
            "GROUP_AT_MESSAGE_CREATE", "GROUP_ADD_ROBOT", "GROUP_DEL_ROBOT", "GROUP_MESSAGE_CREATE"
                => "{$API地址}/v2/groups/{$this->来源ID}/messages",
            "INTERACTION_CREATE", "ShengBot_MSG"
                => ($this->按钮来源 === "c2c")
                    ? "{$API地址}/v2/users/{$this->来源ID}/messages"
                    : "{$API地址}/v2/groups/{$this->来源ID}/messages",
            "DIRECT_MESSAGE_CREATE"
                => "{$API地址}/dms/{$this->来源ID}/messages",
            "AT_MESSAGE_CREATE", "MESSAGE_CREATE"
                => "{$API地址}/channels/{$this->来源ID}/messages",
            default => null
        };

        if ($url === null) return null;

        $用户ID = $this->用户ID;
        try {
            $响应 = $this->httpPost($url, json_encode($data, JSON_UNESCAPED_UNICODE), $this->公共访问头);
            $发送请求 = $响应->getBody();
            $this->预返回信息 = $发送请求;

            $结果 = json_decode($发送请求, true);

            if (!empty($结果['code'])) {
                $this->logger->error("[发送失败] code={$结果['code']} msg={$结果['message']}");
                $this->发送失败通知($结果['message'] ?? '未知错误');
                return null;
            }

            $消息ID = $结果['id'] ?? null;
            if ($消息ID) {
                $this->发送记录[$用户ID] = $消息ID;
            }

            if ($this->事件类型 == "ShengBot_MSG") {
                $this->响应->header('Content-Type', 'application/json');
                $this->响应->status(200);
                $this->响应->end(json_encode(['msg' => $this->预返回信息], JSON_UNESCAPED_UNICODE));
            }
            $this->logger->info("[发送] " . $发送请求);
            return $消息ID;
        } catch (\Throwable $e) {
            $this->logger->error("[发送失败] " . $e->getMessage());
            return null;
        }
    }

    private function 发送失败通知(string $原因): void
    {
        try {
            $API地址 = $this->获取API地址();
            $url = match($this->事件类型) {
                "C2C_MESSAGE_CREATE", "FRIEND_ADD", "FRIEND_DEL"
                    => "{$API地址}/v2/users/{$this->来源ID}/messages",
                "GROUP_AT_MESSAGE_CREATE", "GROUP_ADD_ROBOT", "GROUP_DEL_ROBOT", "GROUP_MESSAGE_CREATE"
                    => "{$API地址}/v2/groups/{$this->来源ID}/messages",
                "INTERACTION_CREATE", "ShengBot_MSG"
                    => ($this->按钮来源 === "c2c")
                        ? "{$API地址}/v2/users/{$this->来源ID}/messages"
                        : "{$API地址}/v2/groups/{$this->来源ID}/messages",
                "DIRECT_MESSAGE_CREATE"
                    => "{$API地址}/dms/{$this->来源ID}/messages",
                "AT_MESSAGE_CREATE", "MESSAGE_CREATE"
                    => "{$API地址}/channels/{$this->来源ID}/messages",
                default => null
            };
            if ($url === null) return;

            $data = [
                "content" => "发送失败: {$原因}",
                "msg_type" => 0,
                "msg_id" => $this->信息ID,
                "msg_seq" => rand(1, 99999)
            ];

            $this->httpPost($url, json_encode($data, JSON_UNESCAPED_UNICODE), $this->公共访问头);
        } catch (\Throwable $e) {
            $this->logger->error("[失败通知发送异常] " . $e->getMessage());
        }
    }

    public function unicode编码(string $str): string
    {
        return preg_replace_callback('/./u', function ($m) {
            $cp = mb_ord($m[0], 'UTF-8');
            return sprintf('\u%04X', $cp);
        }, $str);
    }

    /**
     * 撤回消息
     * @param string $消息ID 要撤回的消息ID
     * @return bool 是否撤回成功
     */
    public function 撤回(string $消息ID): bool
    {
        try {
            $API地址 = $this->获取API地址();

            $url = match($this->事件类型) {
                "C2C_MESSAGE_CREATE", "FRIEND_ADD", "FRIEND_DEL"
                    => "{$API地址}/v2/users/{$this->来源ID}/messages/{$消息ID}",
                "GROUP_AT_MESSAGE_CREATE", "GROUP_ADD_ROBOT", "GROUP_DEL_ROBOT", "GROUP_MESSAGE_CREATE"
                    => "{$API地址}/v2/groups/{$this->来源ID}/messages/{$消息ID}",
                "INTERACTION_CREATE", "ShengBot_MSG"
                    => ($this->按钮来源 === "c2c")
                        ? "{$API地址}/v2/users/{$this->来源ID}/messages/{$消息ID}"
                        : "{$API地址}/v2/groups/{$this->来源ID}/messages/{$消息ID}",
                "DIRECT_MESSAGE_CREATE"
                    => "{$API地址}/dms/{$this->来源ID}/messages/{$消息ID}",
                "AT_MESSAGE_CREATE", "MESSAGE_CREATE"
                    => "{$API地址}/channels/{$this->来源ID}/messages/{$消息ID}",
                default => null
            };

            if ($url === null) {
                $this->logger->warning("[撤回] 不支持的事件类型: {$this->事件类型}");
                return false;
            }

            $响应 = HttpClientPool::delete($url, $this->公共访问头);
            if ($响应 === null) {
                $this->logger->error("[撤回] HTTP请求失败");
                return false;
            }

            $this->logger->info("[撤回] 消息ID: {$消息ID} 状态码: {$响应['statusCode']}");
            return $响应['statusCode'] === 200;
        } catch (\Throwable $e) {
            $this->logger->error("[撤回失败] " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取当前用户最后发送的消息ID
     */
    public function 获取上次发送ID(): ?string
    {
        return $this->发送记录[$this->用户ID] ?? null;
    }

    /**
     * 发送互动召回消息（仅单聊）
     * 当用户主动与机器人对话后，机器人可下发召回消息提醒用户
     * 周期：当天、1-3天、3-7天、7-30天，每周期1条
     * 
     * @param string $内容 消息内容
     * @return bool 是否发送成功
     */
    public function 发送召回(string $内容): bool
    {
        if ($this->事件类型 !== 'C2C_MESSAGE_CREATE') {
            $this->logger->warning("[召回] 仅支持单聊场景");
            return false;
        }

        try {
            $API地址 = $this->获取API地址();
            $url = "{$API地址}/v2/users/{$this->来源ID}/messages";

            $data = [
                "content" => $内容,
                "msg_type" => 0,
                "is_wakeup" => true
            ];

            $响应 = $this->httpPost($url, json_encode($data, JSON_UNESCAPED_UNICODE), $this->公共访问头);
            if ($响应 === null) {
                $this->logger->error("[召回] HTTP请求失败");
                return false;
            }

            $body = $响应->getBody();
            $结果 = json_decode($body, true);

            if (!empty($结果['code'])) {
                $this->logger->error("[召回失败] code={$结果['code']} msg={$结果['message']}");
                return false;
            }

            $this->logger->info("[召回] 发送成功: {$body}");
            return true;
        } catch (\Throwable $e) {
            $this->logger->error("[召回失败] " . $e->getMessage());
            return false;
        }
    }

    public function 流式(int $状态, string $内容, ?string $流ID = null, int $序号 = 0, bool $重置 = false): ?string
    {
        try {
            $API地址 = $this->获取API地址();
            if (!in_array($this->事件类型, ["C2C_MESSAGE_CREATE", "GROUP_AT_MESSAGE_CREATE"])) {
                $this->logger->warning("[流式消息] 仅支持单聊");
                return null;
            }

            $stream = [
                "stream" => [
                    "state" => $状态,
                    "id"    => $流ID,
                    "index" => $序号,
                    "reset" => $重置
                ]
            ];

            $data = [
                "content"  => $内容,
                "msg_type" => 0,
                "msg_id"   => $this->信息ID,
                "msg_seq"  => rand(1, 99999)
            ];

            $data = array_merge($data, $stream);

            $url = ($this->事件类型 == "C2C_MESSAGE_CREATE")
                ? "{$API地址}/v2/users/{$this->来源ID}/messages"
                : "{$API地址}/v2/groups/{$this->来源ID}/messages";

            $响应 = $this->httpPost($url, json_encode($data, JSON_UNESCAPED_UNICODE), $this->公共访问头)->getBody();
            $结果 = json_decode($响应, true);

            $this->logger->info("[流式发送] 状态:{$状态} 序号:{$序号} 响应:{$响应}");

            return $结果['id'] ?? null;
        } catch (\Throwable $e) {
            $this->logger->error("[流式发送失败] " . $e->getMessage());
            return null;
        }
    }
}
