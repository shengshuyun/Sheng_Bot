<?php
require_once __DIR__ . '/../admin/数据库.php';
require_once __DIR__ . '/模块/官方QQ机器人.php';

class AdminController
{
    private $db;
    private $sessionIdKey = 'ShengBotSession';
    private $csrfTokenKey = 'csrf_token';
    private $frameworkConfig = [];
    
    public function __construct()
    {
        $this->db = new SQLiteDatabase();
    }
    
    public function setFrameworkConfig(array $config)
    {
        $this->frameworkConfig = $config;
    }
    
    private function getSessionId($request)
    {
        $cookies = $request->cookie ?? [];
        return $cookies[$this->sessionIdKey] ?? uniqid('sb_', true);
    }
    
    private function setSession($response, $sessionId, $data)
    {
        $this->db->setSession($sessionId, $data, 3600);
        $response->cookie($this->sessionIdKey, $sessionId, time() + 3600, '/');
    }
    
    private function getSession($request)
    {
        $sessionId = $this->getSessionId($request);
        return $this->db->getSession($sessionId);
    }
    
    private function destroySession($request, $response)
    {
        $sessionId = $this->getSessionId($request);
        $this->db->deleteSession($sessionId);
        $response->cookie($this->sessionIdKey, '', time() - 3600, '/');
    }
    
    private function isLoggedIn($request)
    {
        $session = $this->getSession($request);
        return $session && isset($session['admin_id']);
    }
    
    private function generateCsrfToken($request)
    {
        $session = $this->getSession($request);
        if (!$session) {
            $token = bin2hex(random_bytes(32));
            return $token;
        }
        
        if (isset($session[$this->csrfTokenKey])) {
            return $session[$this->csrfTokenKey];
        }
        
        $token = bin2hex(random_bytes(32));
        $session[$this->csrfTokenKey] = $token;
        
        $sessionId = $this->getSessionId($request);
        $this->db->setSession($sessionId, $session);
        
        return $token;
    }
    
    private function validateCsrfToken($request)
    {
        $session = $this->getSession($request);
        if (!$session || !isset($session[$this->csrfTokenKey])) {
            return false;
        }
        
        $post = $request->post ?? [];
        $headers = $request->header ?? [];
        
        $token = $post['_csrf'] ?? $headers['x-csrf-token'] ?? '';
        
        return hash_equals($session[$this->csrfTokenKey], $token);
    }
    
    public function handle($request, $response)
    {
        $uri = $request->server['request_uri'] ?? '/';
        $uri = strtok($uri, '?');
        
        // Serve static files
        if ($this->serveStatic($request, $response, $uri)) {
            return;
        }
        
        // Redirect to cute frontend
        if ($uri === '/admin' || $uri === '/admin/') {
            $this->redirect($response, '/admin/index.html');
            return;
        }
        
        // API endpoints
        if (str_starts_with($uri, '/admin/api/')) {
            $this->handleApi($request, $response, $uri);
            return;
        }
        
        // Legacy admin (fallback)
        $isInstalled = $this->db->isInstalled();
        
        if ($isInstalled && substr($uri, 0, 14) === '/admin/install' && $uri !== '/admin/install/do') {
            $this->redirect($response, '/admin/index.html');
            return;
        }
        
        if (!$isInstalled && substr($uri, 0, 14) !== '/admin/install') {
            $this->redirect($response, '/admin/install');
            return;
        }
        
        if ($isInstalled && !$this->isLoggedIn($request) && 
            $uri !== '/admin/login' && $uri !== '/admin/login/do') {
            $this->redirect($response, '/admin/login');
            return;
        }
        
        if ($uri === '/admin/install') {
            $this->installPage($request, $response);
        } elseif ($uri === '/admin/install/do') {
            $this->doInstall($request, $response);
        } elseif ($uri === '/admin/login') {
            $this->loginPage($request, $response);
        } elseif ($uri === '/admin/login/do') {
            $this->doLogin($request, $response);
        } else {
            $response->status(404);
            $response->end('Not Found');
        }
    }
    
    private function serveStatic($request, $response, $uri)
    {
        $staticMap = [
            '/admin/index.html' => __DIR__ . '/../admin/index.html',
            '/admin/cute-theme.css' => __DIR__ . '/../admin/cute-theme.css',
            '/admin/cute-app.js' => __DIR__ . '/../admin/cute-app.js',
            '/admin/styles.css' => __DIR__ . '/../admin/styles.css'
        ];
        
        if (isset($staticMap[$uri]) && file_exists($staticMap[$uri])) {
            // 登录检查
            $isInstalled = $this->db->isInstalled();
            if ($isInstalled && !$this->isLoggedIn($request)) {
                $this->redirect($response, '/admin/login');
                return true;
            }
            
            $content = file_get_contents($staticMap[$uri]);
            
            // Set correct Content-Type
            $ext = pathinfo($staticMap[$uri], PATHINFO_EXTENSION);
            $types = [
                'html' => 'text/html; charset=utf-8',
                'css' => 'text/css; charset=utf-8',
                'js' => 'application/javascript; charset=utf-8'
            ];
            $response->header('Content-Type', $types[$ext] ?? 'text/plain');
            
            $response->end($content);
            return true;
        }
        
        return false;
    }
    
    private function handleApi($request, $response, $uri)
    {
      $response->header('Content-Type', 'application/json; charset=utf-8');
      
      // API登录检查
      $isInstalled = $this->db->isInstalled();
      if ($isInstalled && !$this->isLoggedIn($request)) {
        $response->status(401);
        $response->end(json_encode(['success' => false, 'error' => '请先登录'], JSON_UNESCAPED_UNICODE));
        return;
      }
      
      $method = $request->server['request_method'] ?? 'GET';
      $endpoint = substr($uri, strlen('/admin/api/'));
      $data = ['success' => true];
      
      try {
        // 获取 POST 数据 - 支持 form-data 和 x-www-form-urlencoded
        $post = $request->post ?? [];
        
        // CSRF 验证 - 对所有非 GET 请求
        if ($method !== 'GET' && !$this->validateCsrfToken($request)) {
          $response->status(403);
          $data = ['success' => false, 'error' => 'CSRF 令牌无效'];
          $this->db->addSystemLog('warning', 'CSRF 验证失败', ['endpoint' => $endpoint]);
          $response->end(json_encode($data, JSON_UNESCAPED_UNICODE));
          return;
        }
      
      // QQ Bots CRUD
      if ($endpoint === 'qq-bots') {
        $pdo = $this->db->getConnection();
        try {
          if ($method === 'GET') {
            $data['bots'] = $pdo->query("SELECT * FROM qq_bots ORDER BY id DESC")->fetchAll();
            $this->db->addSystemLog('info', '获取QQ机器人列表', ['count' => count($data['bots'])]);
          } elseif ($method === 'POST') {
            $stmt = $pdo->prepare("INSERT INTO qq_bots (appid, secret, sandbox) VALUES (?, ?, ?)");
            $appid = $post['appid'] ?? '';
            $sandbox = isset($post['sandbox']) ? (int)$post['sandbox'] : 1;
            $stmt->execute([
              $appid,
              $post['secret'] ?? '',
              $sandbox
            ]);
            $data['id'] = $pdo->lastInsertId();
            $data['message'] = 'QQ机器人添加成功';
            $this->db->addSystemLog('info', '添加QQ机器人', ['appid' => $appid, 'id' => $data['id'], 'sandbox' => $sandbox]);
          }
        } finally {
          $this->db->releaseConnection($pdo);
        }
      } elseif (preg_match('#^qq-bots/(\d+)$#', $endpoint, $matches)) {
        $id = (int)$matches[1];
        $pdo = $this->db->getConnection();
        try {
          if ($method === 'DELETE') {
            // 先获取被删除的机器人信息
            $getBot = $pdo->prepare("SELECT * FROM qq_bots WHERE id = ?");
            $getBot->execute([$id]);
            $botInfo = $getBot->fetch();
            
            $stmt = $pdo->prepare("DELETE FROM qq_bots WHERE id = ?");
            $stmt->execute([$id]);
            $data['message'] = 'QQ机器人删除成功';
            $this->db->addSystemLog('warning', '删除QQ机器人', ['id' => $id, 'appid' => $botInfo['appid'] ?? 'unknown']);
          }
        } finally {
          $this->db->releaseConnection($pdo);
        }
      }
      
      // NapCat Bots CRUD
      elseif ($endpoint === 'napcat-bots') {
        $pdo = $this->db->getConnection();
        try {
          if ($method === 'GET') {
            $data['bots'] = $pdo->query("SELECT * FROM napcat_bots ORDER BY id DESC")->fetchAll();
            $this->db->addSystemLog('info', '获取NapCat机器人列表', ['count' => count($data['bots'])]);
          } elseif ($method === 'POST') {
            $qq = $post['qq'] ?? '';
            $httpUrl = $post['http_url'] ?? '';
            $stmt = $pdo->prepare("INSERT INTO napcat_bots (qq, http_url, token) VALUES (?, ?, ?)");
            $stmt->execute([
              $qq,
              $httpUrl,
              $post['token'] ?? ''
            ]);
            $data['id'] = $pdo->lastInsertId();
            $data['message'] = 'NapCat机器人添加成功';
            $this->db->addSystemLog('info', '添加NapCat机器人', ['qq' => $qq, 'http_url' => $httpUrl, 'id' => $data['id']]);
          }
        } finally {
          $this->db->releaseConnection($pdo);
        }
      } elseif (preg_match('#^napcat-bots/(\d+)$#', $endpoint, $matches)) {
        $id = (int)$matches[1];
        $pdo = $this->db->getConnection();
        try {
          if ($method === 'DELETE') {
            // 先获取被删除的机器人信息
            $getBot = $pdo->prepare("SELECT * FROM napcat_bots WHERE id = ?");
            $getBot->execute([$id]);
            $botInfo = $getBot->fetch();
            
            $stmt = $pdo->prepare("DELETE FROM napcat_bots WHERE id = ?");
            $stmt->execute([$id]);
            $data['message'] = 'NapCat机器人删除成功';
            $this->db->addSystemLog('warning', '删除NapCat机器人', ['id' => $id, 'qq' => $botInfo['qq'] ?? 'unknown']);
          }
        } finally {
          $this->db->releaseConnection($pdo);
        }
      }
      
      // Stats
      elseif ($endpoint === 'stats') {
        $pdo = $this->db->getConnection();
        try {
          $data = [
            'qqBots' => $pdo->query("SELECT COUNT(*) FROM qq_bots")->fetchColumn(),
            'napcatBots' => $pdo->query("SELECT COUNT(*) FROM napcat_bots")->fetchColumn(),
            'messageLogs' => $pdo->query("SELECT COUNT(*) FROM message_logs")->fetchColumn(),
            'systemLogs' => $pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn(),
            'phpVersion' => PHP_VERSION,
            'swooleVersion' => defined('SWOOLE_VERSION') ? SWOOLE_VERSION : 'Unknown'
          ];
        } finally {
          $this->db->releaseConnection($pdo);
        }
      }
      
      // Message Logs
      elseif ($endpoint === 'message-logs') {
        $pdo = $this->db->getConnection();
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $data['logs'] = $this->db->getMessageLogs($limit);
      }
      
      // System Logs
      elseif ($endpoint === 'system-logs') {
        $pdo = $this->db->getConnection();
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $data['logs'] = $this->db->getSystemLogs($limit);
      }
      
      // CSRF Token
        elseif ($endpoint === 'csrf-token' && $method === 'GET') {
          $data['csrf_token'] = $this->generateCsrfToken($request);
        }
        
        // System Stats - Connection Pool & Cache
        elseif ($endpoint === 'system/stats' && $method === 'GET') {
          $data['pool_stats'] = $this->db->getPoolStats();
          $data['cache_stats'] = $this->db->getCacheStats();
        }
        
        // System Settings
        elseif ($endpoint === 'settings') {
          if ($method === 'GET') {
            $data['settings'] = $this->db->getAllConfigs();
            $data['csrf_token'] = $this->generateCsrfToken($request);
          } elseif ($method === 'POST') {
            $changedKeys = [];
            $configUpdates = [];
            
            foreach ($post as $key => $value) {
              // 转换布尔值和数字
              if (in_array($value, ['true', 'false'], true)) {
                $processedValue = $value === 'true';
              } elseif (is_numeric($value)) {
                $processedValue = (int)$value;
              } else {
                $processedValue = $value;
              }
              
              $oldValue = $this->db->getConfig($key);
              if ($oldValue !== $processedValue) {
                $changedKeys[] = $key;
                $configUpdates[$key] = $processedValue;
              }
              
              $this->db->setConfig($key, $processedValue);
            }
            
            // 更新数据库实例的配置
            if (!empty($configUpdates)) {
              $this->db->updateConfig($configUpdates);
            }
            
            $data['message'] = '系统设置保存成功';
            $data['settings'] = $this->db->getAllConfigs();
            $this->db->addSystemLog('info', '保存系统设置', ['changed_keys' => $changedKeys]);
          }
        }
      
      // Test API - Message simulation (QQ 官方机器人消息模拟)
      elseif ($endpoint === 'test/send-message' && $method === 'POST') {
        $botId = (int)($post['bot_id'] ?? 0);
        $eventType = $post['event_type'] ?? 'C2C_MESSAGE_CREATE';
        $senderId = $post['sender_id'] ?? '';
        $groupId = $post['group_id'] ?? '';
        $channelId = $post['channel_id'] ?? '';
        $guildId = $post['guild_id'] ?? '';
        $content = $post['content'] ?? '';
        $eventTs = $post['event_ts'] ?? (string)time();
        $plainToken = $post['plain_token'] ?? '';

        // Verify bot exists
        $pdo = $this->db->getConnection();
        try {
          $stmt = $pdo->prepare("SELECT * FROM qq_bots WHERE id = ?");
          $stmt->execute([$botId]);
          $bot = $stmt->fetch();

          if (!$bot) {
            $response->status(404);
            $data['success'] = false;
            $data['error'] = '机器人不存在';
            $this->db->addSystemLog('warning', '测试失败: 机器人不存在', ['bot_id' => $botId]);
          } else {
            // Build QQ 官方格式消息对象
            $officialEvent = [
              'op' => 0,
              't' => '', // 事件类型
              'id' => uniqid('test_', true),
              'd' => [
                'id' => uniqid('msg_', true),
                'timestamp' => date('c'),
              ]
            ];
            
            // 处理鉴权事件 (op=13)
            if ($eventType === 'AUTH_OP_13') {
              $officialEvent['op'] = 13;
              $officialEvent['d'] = [
                'plain_token' => $plainToken,
                'event_ts' => $eventTs
              ];
              $officialEvent['t'] = 'READY';
            } else {
              // 处理其他类型事件
              $officialEvent['t'] = $eventType;
              
              // 根据事件类型构建 d 字段
              switch ($eventType) {
                // 单聊消息
                case 'C2C_MESSAGE_CREATE':
                  $officialEvent['d']['author'] = [
                    'id' => $senderId,
                    'username' => '测试用户' . $senderId
                  ];
                  $officialEvent['d']['content'] = $content;
                  break;
                  
                // 群聊@消息
                case 'GROUP_AT_MESSAGE_CREATE':
                  $officialEvent['d']['author'] = [
                    'id' => $senderId,
                    'username' => '测试用户' . $senderId
                  ];
                  $officialEvent['d']['content'] = '<@!' . $bot['appid'] . '> ' . $content;
                  $officialEvent['d']['group_openid'] = $groupId;
                  $officialEvent['d']['group_id'] = $groupId;
                  break;
                  
                // 频道私信
                case 'DIRECT_MESSAGE_CREATE':
                  $officialEvent['d']['author'] = [
                    'id' => $senderId,
                    'username' => '测试用户' . $senderId
                  ];
                  $officialEvent['d']['content'] = $content;
                  $officialEvent['d']['guild_id'] = $guildId;
                  $officialEvent['d']['channel_id'] = $channelId;
                  break;
                  
                // 频道@消息
                case 'AT_MESSAGE_CREATE':
                  $officialEvent['d']['author'] = [
                    'id' => $senderId,
                    'username' => '测试用户' . $senderId
                  ];
                  $officialEvent['d']['content'] = '<@!' . $bot['appid'] . '> ' . $content;
                  $officialEvent['d']['channel_id'] = $channelId;
                  $officialEvent['d']['guild_id'] = $guildId;
                  break;
                  
                // 频道普通消息
                case 'MESSAGE_CREATE':
                  $officialEvent['d']['author'] = [
                    'id' => $senderId,
                    'username' => '测试用户' . $senderId
                  ];
                  $officialEvent['d']['content'] = $content;
                  $officialEvent['d']['channel_id'] = $channelId;
                  $officialEvent['d']['guild_id'] = $guildId;
                  break;
                  
                // 添加好友
                case 'FRIEND_ADD':
                  $officialEvent['d']['openid'] = $senderId;
                  break;
                  
                // 删除好友
                case 'FRIEND_DEL':
                  $officialEvent['d']['openid'] = $senderId;
                  break;
                  
                // 加入群聊
                case 'GROUP_ADD_ROBOT':
                  $officialEvent['d']['group_openid'] = $groupId;
                  break;
                  
                // 退出群聊
                case 'GROUP_DEL_ROBOT':
                  $officialEvent['d']['group_id'] = $groupId;
                  $officialEvent['d']['id'] = $groupId;
                  break;
                  
                // 加入频道
                case 'GUILD_CREATE':
                  $officialEvent['d']['id'] = $guildId;
                  break;
                  
                // 退出频道
                case 'GUILD_DELETE':
                  $officialEvent['d']['id'] = $guildId;
                  break;
                  
                // 交互事件
                case 'INTERACTION_CREATE':
                  $officialEvent['d']['scene'] = 'group';
                  $officialEvent['d']['group_openid'] = $groupId;
                  $officialEvent['d']['user_openid'] = $senderId;
                  break;
                  
                // 表情事件
                case 'MESSAGE_REACTION_ADD':
                case 'MESSAGE_REACTION_REMOVE':
                  $officialEvent['d']['user_id'] = $senderId;
                  $officialEvent['d']['channel_id'] = $channelId;
                  $officialEvent['d']['guild_id'] = $guildId;
                  break;
              }
            }

            // 记录消息日志
            $logGroupId = null;
            $contentType = 'text';
            if (in_array($eventType, ['GROUP_AT_MESSAGE_CREATE', 'GROUP_ADD_ROBOT', 'GROUP_DEL_ROBOT'])) {
              $logGroupId = $groupId;
            } elseif (in_array($eventType, ['DIRECT_MESSAGE_CREATE', 'AT_MESSAGE_CREATE', 'MESSAGE_CREATE', 'GUILD_CREATE', 'GUILD_DELETE'])) {
              $logGroupId = $channelId;
            }

            if (in_array($eventType, ['C2C_MESSAGE_CREATE', 'GROUP_AT_MESSAGE_CREATE', 'DIRECT_MESSAGE_CREATE', 'AT_MESSAGE_CREATE', 'MESSAGE_CREATE'])) {
              $this->db->addMessageLog(
                'qq',
                $bot['appid'],
                $senderId,
                $logGroupId,
                $contentType,
                $content
              );
            }

            $data['success'] = true;
            $data['message'] = '模拟事件推送成功';
            $data['event'] = $officialEvent;
            $this->db->addSystemLog('info', '模拟QQ官方格式事件推送', [
              'bot_id' => $botId,
              'bot_appid' => $bot['appid'],
              'event_type' => $eventType,
              'sender' => $senderId,
              'group' => $groupId,
              'channel' => $channelId
            ]);
            
            // ===============================
            // 关键：真正调用机器人处理消息！
            // ===============================
            
            // 检查是否有配置的QQ机器人
            if (isset($this->frameworkConfig['QQBOT']) && !empty($this->frameworkConfig['QQBOT'])) {
              $qqBotConfig = $this->frameworkConfig['QQBOT'];
              
              // 创建一个临时请求对象，让机器人来处理
              $mockRequest = new class($bot['appid'], $officialEvent) {
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
              
              try {
                // 创建机器人实例并调用主入口
                $机器人 = new 官方QQ机器人($qqBotConfig);
                
                // 对于鉴权事件，直接调用鉴权方法
                if ($eventType === 'AUTH_OP_13') {
                  $authResult = $机器人->鉴权($officialEvent);
                  $data['processed'] = true;
                  $data['auth_response'] = $authResult;
                  $data['robot_response'] = $authResult;
                  $mockResponse->end($authResult);
                  $this->db->addSystemLog('info', '测试鉴权事件已成功调用机器人处理');
                } else {
                  // 直接调用主入口方法
                  $机器人->主入口($mockRequest, $mockResponse);
                  $data['processed'] = true;
                  $data['robot_response'] = $mockResponse->content;
                  $this->db->addSystemLog('info', '测试事件已成功调用机器人处理');
                }
              } catch (Throwable $e) {
                $data['processed'] = false;
                $data['process_error'] = $e->getMessage();
                $this->db->addSystemLog('warning', '测试事件调用机器人处理失败', [
                  'error' => $e->getMessage()
                ]);
              }
            } else {
              $data['processed'] = false;
              $data['process_note'] = '没有配置QQ机器人';
            }
          }
        } finally {
          $this->db->releaseConnection($pdo);
        }
      } else {
        $response->status(404);
        $data = ['success' => false, 'error' => 'Unknown endpoint'];
        $this->db->addSystemLog('error', '访问未知API端点', ['endpoint' => $endpoint]);
      }
    } catch (Exception $e) {
      $response->status(500);
      $data = ['success' => false, 'error' => $e->getMessage()];
      $this->db->addSystemLog('error', 'API操作异常', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
    }
    
    $response->end(json_encode($data, JSON_UNESCAPED_UNICODE));
  }
    
    private function redirect($response, $url)
    {
        $response->status(302);
        $response->header('Location', $url);
        $response->end('');
    }
    
    private function installPage($request, $response)
    {
        $content = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>安装</title>';
        $content .= '<style>body{font-family:system-ui;background:#FFF0F5;padding:20px;text-align:center;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center}';
        $content .= '.card{background:white;padding:30px;border-radius:20px;width:100%;max-width:500px;margin:0 auto;box-shadow:0 10px 40px rgba(255,107,157,0.2);box-sizing:border-box}';
        $content .= 'h1{color:#FF6B9D;margin:0 0 20px 0;font-size:24px}';
        $content .= 'input{padding:14px;margin:8px 0;border:2px solid #FFB6C1;border-radius:10px;width:100%;box-sizing:border-box;font-size:16px}';
        $content .= 'button{background:linear-gradient(135deg,#FF9EB1,#FF6B9D);color:white;border:none;padding:14px 30px;border-radius:25px;font-size:18px;cursor:pointer;margin-top:20px;width:100%}';
        $content .= '</style></head><body><div class="card"><h1>🌸 Sheng_Bot 安装向导</h1>';
        $content .= '<form method="POST" action="/admin/install/do">';
        $content .= '<input type="text" name="username" required placeholder="管理员用户名"><br>';
        $content .= '<input type="password" name="password" required placeholder="密码 (至少6位)"><br>';
        $content .= '<input type="password" name="confirm_password" required placeholder="确认密码"><br>';
        $content .= '<button type="submit">开始安装 ✨</button>';
        $content .= '</form></div></body></html>';
        
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->end($content);
    }
    
    private function doInstall($request, $response)
    {
        if ($this->db->isInstalled()) {
            $this->redirect($response, '/admin/index.html');
            return;
        }
        
        $post = $request->post ?? [];
        $username = trim($post['username'] ?? '');
        $password = $post['password'] ?? '';
        $confirmPassword = $post['confirm_password'] ?? '';
        
        if (empty($username) || strlen($password) < 6 || $password !== $confirmPassword) {
            $this->db->addSystemLog('warning', '安装失败：参数无效', ['username' => $username]);
            $this->redirect($response, '/admin/install');
            return;
        }
        
        $pdo = $this->db->getConnection();
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, datetime('now'))");
            $stmt->execute([$username, $passwordHash]);
        } finally {
            $this->db->releaseConnection($pdo);
        }
        
        $configs = [
            'site_name' => 'Sheng_Bot',
            'domain' => '0.0.0.0',
            'http_port' => 9501,
            'https_port' => 9502,
            'db_pool_max_size' => 10,
            'db_pool_min_size' => 2,
            'db_pool_timeout' => 5,
            'query_cache_enabled' => true,
            'query_cache_ttl' => 300,
            'query_cache_max_size' => 1000,
            'log_level' => 'info',
            'log_max_file_size' => 10 * 1024 * 1024,
            'log_max_files' => 10,
            'log_to_database' => true,
            'log_to_file' => true,
            'installed' => true
        ];
        
        foreach ($configs as $key => $value) {
            $this->db->setConfig($key, $value);
        }
        
        $this->db->addSystemLog('info', '系统安装完成', ['username' => $username]);
        
        $sessionId = $this->getSessionId($request);
        $this->setSession($response, $sessionId, ['admin_id' => 1, 'username' => $username]);
        
        $this->redirect($response, '/admin/index.html');
    }
    
    private function loginPage($request, $response)
    {
        $content = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>登录</title>';
        $content .= '<style>body{font-family:system-ui;background:#FFF0F5;padding:20px;text-align:center;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center}';
        $content .= '.card{background:white;padding:30px;border-radius:20px;width:100%;max-width:400px;margin:0 auto;box-shadow:0 10px 40px rgba(255,107,157,0.2);box-sizing:border-box}';
        $content .= 'h1{color:#FF6B9D;margin:0 0 20px 0;font-size:24px}';
        $content .= 'input{padding:14px;margin:8px 0;border:2px solid #FFB6C1;border-radius:10px;width:100%;box-sizing:border-box;font-size:16px}';
        $content .= 'button{background:linear-gradient(135deg,#FF9EB1,#FF6B9D);color:white;border:none;padding:14px 30px;border-radius:25px;font-size:18px;cursor:pointer;margin-top:20px;width:100%}';
        $content .= '</style></head><body><div class="card"><h1>🌸 Sheng_Bot 登录</h1>';
        $content .= '<form method="POST" action="/admin/login/do">';
        $content .= '<input type="text" name="username" required placeholder="用户名"><br>';
        $content .= '<input type="password" name="password" required placeholder="密码"><br>';
        $content .= '<button type="submit">登录 💕</button>';
        $content .= '</form></div></body></html>';
        
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->end($content);
    }
    
    private function doLogin($request, $response)
    {
        $post = $request->post ?? [];
        $username = trim($post['username'] ?? '');
        $password = $post['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $this->db->addSystemLog('warning', '登录失败：参数为空', ['username' => $username]);
            $this->redirect($response, '/admin/login');
            return;
        }
        
        $pdo = $this->db->getConnection();
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
        } finally {
            $this->db->releaseConnection($pdo);
        }
        
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $this->db->addSystemLog('warning', '登录失败：用户名或密码错误', ['username' => $username]);
            $this->redirect($response, '/admin/login');
            return;
        }
        
        $this->db->addSystemLog('info', '管理员登录成功', ['username' => $username, 'admin_id' => $admin['id']]);
        
        $sessionId = $this->getSessionId($request);
        $this->setSession($response, $sessionId, ['admin_id' => $admin['id'], 'username' => $admin['username']]);
        
        $this->redirect($response, '/admin/index.html');
    }
}
