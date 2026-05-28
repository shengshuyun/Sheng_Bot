# Sheng Bot

基于 Swoole 协程的 QQ 机器人框架，同时支持 **QQ官方Bot API** 和 **NapCat (OneBot)** 两个后端。

## 特性

- Swoole 协程驱动，高并发低延迟
- 双框架支持：官方QQBot API + NapCat
- 插件化架构，修改插件无需重启服务器
- HTTP/HTTPS 双端口自动启动
- 进程守护（watchdog），崩溃自动重启
- HTTP 连接池，API 调用复用连接
- Token 静态缓存，避免重复请求
- 协程安全的 JSON 数据库
- 日志批量写入，减少锁竞争

## 快速开始

### 1. 克隆项目

```bash
git clone https://github.com/shengshuyun/Sheng_Bot.git sheng
cd sheng
```

### 2. 安装依赖

```bash
composer install
```

### 3. 配置

```bash
cp config.example.json config.json
```

编辑 `config.json`，填入你的配置：

- **官方QQBot**：在 [QQ开放平台](https://q.qq.com) 创建机器人，获取 `appid` 和 `secret`
- **NapCat**：安装 [NapCat](https://napneko.github.io/)，获取 `http_url` 和 `token`
- **SSL 证书**：将证书文件放入 `证书/` 目录

不需要的框架可以删除对应配置项或留空数组 `[]`。

### 4. 运行

```bash
# 直接运行
php server.php

# 或使用进程守护（推荐）
php swoole_watchdog.php
```

### 5. 配置 Webhook

将你的服务器地址填入 QQ 开放平台的 Webhook 回调地址：

```
https://your-domain.com/
```

## 环境要求

- PHP >= 8.4
- Swoole 扩展 >= 6.0
- sodium 扩展（用于 Ed25519 鉴权）
- SSL 证书（HTTPS 必须）

## 配置项说明

| 字段 | 类型 | 说明 |
|------|------|------|
| `域名` | string | 服务器域名，用于启动 HTTP/HTTPS 服务 |
| `http端口` | int | HTTP 监听端口，默认 80 |
| `https端口` | int | HTTPS 监听端口，默认 443 |
| `ssl证书` | string | SSL 证书文件路径（相对于项目根目录） |
| `ssl密钥` | string | SSL 密钥文件路径 |
| `连接池大小` | int | HTTP 连接池最大连接数，默认 8 |
| `连接超时` | int | HTTP 连接超时秒数，默认 10 |
| `框架.QQBOT` | array | 官方QQBot 账号配置，支持多个 |
| `框架.napcat` | array | NapCat 账号配置，支持多个 |

### QQBot 账号配置

| 字段 | 说明 |
|------|------|
| `appid` | 机器人应用 ID |
| `secret` | 机器人密钥 |
| `sandbox` | 是否使用沙箱环境（`true`/`false`） |

### NapCat 账号配置

| 字段 | 说明 |
|------|------|
| `qq` | 机器人 QQ 号 |
| `http_url` | NapCat HTTP API 地址 |
| `token` | NapCat API Token |

## 运行

### 直接运行

```bash
php server.php
```

### 使用进程守护（推荐）

```bash
php swoole_watchdog.php
```

进程守护功能：
- 主进程崩溃自动重启（退避策略：3s、6s、9s...最大60s）
- 最多重启 10 次，避免无限循环
- 端口占用时自动停止，不重试
- 支持 Ctrl+C 优雅关闭

### 端口被占用时

启动时如果端口被占用，会提示：

```
端口 80 已被占用
  端口 80 被进程 PID=12345 占用
是否释放端口并重启？(y/N):
```

输入 `y` 自动杀掉占用进程并启动。

## 目录结构

```
sheng/
├── server.php              # 入口文件
├── swoole_watchdog.php     # 进程守护
├── config.json             # 配置文件
├── composer.json           # Composer 配置
├── .gitignore
├── src/                    # 核心代码
│   ├── Core/
│   │   ├── Router.php          # 请求路由
│   │   ├── Logger.php          # 日志（批量写入）
│   │   ├── PluginLoader.php    # 插件加载器
│   │   └── HttpClientPool.php  # HTTP 连接池
│   ├── Adapters/
│   │   ├── AdapterInterface.php  # 适配器接口
│   │   ├── BaseAdapter.php       # 适配器基类
│   │   ├── OfficialQQBot.php     # 官方QQ适配器
│   │   └── NapCatBot.php         # NapCat适配器
│   ├── Traits/
│   │   ├── TimerTrait.php        # 定时器
│   │   └── DatabaseTrait.php     # 数据库
│   ├── Database/
│   │   └── JsonDatabase.php      # JSON 文件数据库
│   └── Protobuf/
│       ├── Serializer.php        # Protobuf 序列化
│       ├── Deserializer.php      # Protobuf 反序列化
│       └── helpers.php
├── 插件/
│   ├── 官方/               # 官方QQ插件目录
│   └── 猫猫/               # NapCat插件目录
├── 数据/数据库/             # JSON 数据存储
├── 日志/                   # 日志文件
└── 证书/                   # SSL 证书
```

---

## 插件开发

插件是放在 `插件/官方/` 或 `插件/猫猫/` 目录下的 PHP 文件。每次收到消息时，框架会自动加载并执行对应目录下的所有插件。

### 基本结构

```php
<?php
// 插件/官方/我的插件.php

// $this 指向当前适配器实例，可以访问所有属性和方法
if ($this->用户信息 == "你好") {
    $this->发送("文本", "你好！");
}
```

### 执行流程

1. 用户发消息 → QQ/NapCat 推送到你的服务器
2. 框架识别来源（官方QQ / NapCat）
3. 解析消息，设置 `$this->用户信息`、`$this->来源ID` 等属性
4. 按顺序执行插件目录下所有 `.php` 文件
5. 插件通过 `$this->发送()` 回复用户

### 注意事项

- 插件是**串行执行**的，按文件名字母顺序
- 所有插件共享同一个 `$this` 实例
- 修改插件文件后**下一次请求自动生效**，无需重启
- 插件内的异常会被捕获，不会导致服务器崩溃

---

## 官方QQ 插件 API

### 可读属性

| 属性 | 类型 | 说明 |
|------|------|------|
| `$this->用户信息` | string | 用户发送的消息文本（已去除前缀 `/`） |
| `$this->用户ID` | string | 发送者的用户 ID |
| `$this->用户昵称` | string | 发送者的昵称（仅群聊全量消息） |
| `$this->来源ID` | string | 来源 ID（群号/频道ID/用户ID） |
| `$this->信息ID` | string | 消息 ID（用于被动回复） |
| `$this->事件ID` | string | 事件 ID |
| `$this->事件类型` | string | 事件类型（见下方列表） |
| `$this->当前账号` | array | 当前账号配置（含 `appid`、`secret`） |
| `$this->按钮来源` | string | 按钮点击来源（`c2c`/`group`） |
| `$this->按钮数据` | string | 按钮自定义数据 |
| `$this->按钮ID` | string | 按钮 ID |

### 事件类型

| 事件类型 | 说明 |
|----------|------|
| `C2C_MESSAGE_CREATE` | 单聊消息 |
| `GROUP_AT_MESSAGE_CREATE` | 群内 @机器人 消息 |
| `GROUP_MESSAGE_CREATE` | 群全量消息 |
| `DIRECT_MESSAGE_CREATE` | 频道私信 |
| `AT_MESSAGE_CREATE` | 频道 @机器人 消息 |
| `MESSAGE_CREATE` | 频道普通消息 |
| `FRIEND_ADD` | 好友添加 |
| `GROUP_ADD_ROBOT` | 机器人被添加到群 |
| `INTERACTION_CREATE` | 按钮点击交互 |
| `ShengBot_MSG` | 框架内部转发消息 |

### 发送消息

```php
$this->发送(类型, 主内容, 附加1, 附加2);
```

#### 发送文本

```php
$this->发送("文本", "Hello World");
```

#### 发送 Markdown

```php
// 使用模板 ID
$this->发送("md", "模板ID", [
    ['key' => 'text', 'values' => ['内容']]
]);

// 使用原始 Markdown 内容
$this->发送("md", null, "# 标题\n正文内容");

// 带按钮
$this->发送("md", "模板ID", $参数, "按钮模板ID");

// 带原生按钮
$按钮 = [
    "rows" => [[
        "buttons" => [[
            "render_data" => ["label" => "点我", "visited_label" => "已点", "style" => 1],
            "action" => [
                "type" => 1,
                "permission" => ["type" => 0, "specify_user_ids" => [$this->用户ID]],
                "data" => "按钮数据"
            ],
            "id" => "btn_001"
        ]]
    ]]
];
$this->发送("md", null, "Markdown内容", $按钮);
```

#### 发送图片/视频/语音/文件

```php
$this->发送("图片", "https://example.com/image.jpg");
$this->发送("图片", "https://example.com/image.jpg", "附带文本");
$this->发送("视频", "https://example.com/video.mp4");
$this->发送("语音", "https://example.com/audio.mp3");
$this->发送("文件", "https://example.com/file.zip");
```

#### 直发（不上传，直接用 URL）

```php
$this->发送("直发", "https://example.com/image.jpg", "附带文本");
```

### 流式消息

用于单聊场景，模拟打字效果：

```php
$流ID = $this->流式(1, "正在思考...\n", null, 0);       // 开始
$this->定时器("延迟", 500, function() use ($流ID) {
    $this->流式(1, "思考完毕\n", $流ID, 1);              // 续接
    $this->定时器("延迟", 300, function() use ($流ID) {
        $this->流式(10, "最终结果", $流ID, 2);            // 结束（state=10）
    });
});
```

| 参数 | 说明 |
|------|------|
| state | `1` = 生成中，`10` = 结束 |
| 内容 | 消息文本 |
| 流ID | 首次传 `null`，后续用返回的 ID |
| 序号 | 递增序号 |
| 重置 | `true` 重置之前的内容 |

### 定时器

```php
// 延迟执行（毫秒）
$this->定时器("延迟", 3000, function() {
    $this->发送("文本", "3秒后发送");
});

// 循环执行
$定时器ID = $this->定时器("循环", 60000, function() {
    echo "每分钟执行一次\n";
});

// 清除定时器
$this->定时器("清除", $定时器ID);

// 清除所有定时器
$this->定时器("清除全部");
```

### 数据库

基于 JSON 文件的键值存储，路径用 `/` 分隔：

```php
// 写入
$this->数据库("写", "用户/{$this->用户ID}/签到次数", 1);

// 读取（不存在返回 null）
$次数 = $this->数据库("读", "用户/{$this->用户ID}/签到次数");

// 删除
$this->数据库("删", "用户/{$this->用户ID}/签到次数");
```

数据存储在 `数据/数据库/` 目录下，第一个路径段为文件名。例如 `用户/123/签到次数` 对应 `数据/数据库/用户.json` 中的 `["用户"]["123"]["签到次数"]`。

---

## NapCat 插件 API

### 可读属性

| 属性 | 类型 | 说明 |
|------|------|------|
| `$this->用户信息` | array | 发送者信息（含 `user_id`、`nickname`、`role`） |
| `$this->用户ID` | string | 发送者 QQ 号 |
| `$this->来源ID` | string | 群号或用户 QQ 号 |
| `$this->信息ID` | string | 消息 ID |
| `$this->事件类型` | string | `群消息` 或 `私聊消息` |
| `$this->当前账号` | array | 当前账号配置 |
| `$this->群名称` | string | 群名称 |
| `$this->用户权限` | string | 用户权限（`owner`/`admin`/`member`） |
| `$this->消息内容` | object | 解析后的消息对象 |
| `$this->原始消息` | array | 原始消息段数组 |

### 消息内容对象

`$this->消息内容` 包含以下字段：

| 字段 | 类型 | 说明 |
|------|------|------|
| `->纯文本` | string | 纯文本内容（去除图片等） |
| `->完整文本` | string | 包含占位符的完整文本 |
| `->图片列表` | array | 图片信息数组 |
| `->视频列表` | array | 视频信息数组 |
| `->语音列表` | array | 语音信息数组 |
| `->文件列表` | array | 文件信息数组 |
| `->艾特列表` | array | 被 @的用户数组 |
| `->表情列表` | array | 表情 ID 数组 |
| `->是否回复` | bool | 是否是回复消息 |
| `->回复消息ID` | ?string | 被回复的消息 ID |

### 快捷判断方法

```php
$this->有图片()          // 是否包含图片
$this->是否被艾特()      // 机器人是否被 @
$this->是否艾特("QQ号")  // 指定用户是否被 @
$this->是回复()          // 是否是回复消息
$this->以开头("前缀")    // 消息是否以指定文本开头
$this->包含("关键词")    // 消息是否包含指定文本
$this->匹配("/正则/")    // 正则匹配，返回匹配结果或 null
```

### 发送消息

```php
// 回复当前消息（自动引用）
$this->回复("你好！");

// 发送群消息
$this->发送群消息($this->来源ID, "Hello");

// 发送私聊消息
$this->发送私聊消息($this->用户ID, "私聊内容");

// 带回复引用
$this->发送群消息($this->来源ID, "回复内容", $this->信息ID);
```

### 消息段构造

构造复杂消息（图文混排等）：

```php
$msg = [
    $this->消息段("文本", "看看这张图："),
    $this->消息段("图片", "https://example.com/img.jpg"),
    $this->消息段("艾特", $this->用户ID),
    $this->消息段("回复", $this->信息ID),
    $this->消息段("表情", 14),       // 表情 ID
    $this->消息段("卡片", $jsonString),
    $this->消息段("音乐", "163", "31649312"),  // 网易云音乐
];
$this->回复($msg);
```

### 转发消息（伪造合并转发）

```php
$节点1 = $this->伪造(10001, "机器人", $this->消息段("文本", "消息1"));
$节点2 = $this->伪造(10001, "机器人", $this->消息段("文本", "消息2"));
$this->群伪造($this->来源ID, $节点1, $节点2);
```

### 调用 NapCat API

直接调用 NapCat 的 HTTP API：

```php
$result = $this->调用API("get_msg", [
    "message_id" => $this->信息ID,
    "raw" => true
]);

// 发送 Protobuf 数据包
$resp = $this->发包("trpc.msg.register_proxy.RegisterProxy.SsoGetGroupMsg", $packet);
```

### 数据库和定时器

与官方QQ插件用法完全相同。

---

## 内置插件说明

### 官方QQ 插件

| 文件 | 功能 |
|------|------|
| `你好.php` | 文本回复、Markdown 模板、原生按钮示例 |
| `富媒体.php` | 图片/视频/语音/文件发送示例 |
| `流式测试.php` | 流式消息（打字效果）示例 |
| `测试.php` | 定时器（延迟/循环/清除）示例 |
| `频道操作.php` | API 地址查询、直发示例 |

### NapCat 插件

| 文件 | 功能 |
|------|------|
| `测试.php` | Protobuf 发包、消息转发、按钮提取 |
| `转发.php` | 跨框架消息转发（NapCat → 官方QQ） |

---

## 错误处理

- 插件内的异常会被自动捕获，记录到日志，不影响其他插件
- API 发送失败会自动通知用户失败原因
- 文件上传失败会通知用户
- 日志文件位于 `日志/app.log`

## 性能优化

项目内置以下性能优化：

- **Token 静态缓存**：同一 Worker 内，同一 appid 只请求一次 token API
- **HTTP 连接池**：API 调用复用 TCP/TLS 连接，减少握手开销
- **协程安全锁**：数据库使用 `Swoole\Coroutine\Lock`，不阻塞 Worker
- **数据库读缓存**：同一文件在一次请求内只读一次磁盘
- **日志批量写入**：日志先写内存 buffer，批量刷盘，减少锁竞争

## License

MIT
