<?php
require_once __DIR__ . '/../admin/数据库.php';

class AdminController
{
    private $db;
    private $sessions = [];
    private $sessionIdKey = 'ShengBotSession';
    
    public function __construct()
    {
        $this->db = new SQLiteDatabase();
    }
    
    private function getSessionId($request)
    {
        $cookies = $request->cookie ?? [];
        return $cookies[$this->sessionIdKey] ?? uniqid('sb_', true);
    }
    
    private function setSession($response, $sessionId, $data)
    {
        $this->sessions[$sessionId] = [
            'data' => $data,
            'expire' => time() + 3600
        ];
        
        $response->cookie($this->sessionIdKey, $sessionId, time() + 3600, '/');
    }
    
    private function getSession($request)
    {
        $sessionId = $this->getSessionId($request);
        $session = $this->sessions[$sessionId] ?? null;
        
        if ($session && $session['expire'] < time()) {
            unset($this->sessions[$sessionId]);
            return null;
        }
        
        return $session['data'] ?? null;
    }
    
    private function destroySession($request, $response)
    {
        $sessionId = $this->getSessionId($request);
        unset($this->sessions[$sessionId]);
        $response->cookie($this->sessionIdKey, '', time() - 3600, '/');
    }
    
    private function isLoggedIn($request)
    {
        $session = $this->getSession($request);
        return $session && isset($session['admin_id']);
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
      
      // QQ Bots CRUD
      if ($endpoint === 'qq-bots') {
        $pdo = $this->db->getConnection();
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
      } elseif (preg_match('#^qq-bots/(\d+)$#', $endpoint, $matches)) {
        $id = (int)$matches[1];
        $pdo = $this->db->getConnection();
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
      }
      
      // NapCat Bots CRUD
      elseif ($endpoint === 'napcat-bots') {
        $pdo = $this->db->getConnection();
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
      } elseif (preg_match('#^napcat-bots/(\d+)$#', $endpoint, $matches)) {
        $id = (int)$matches[1];
        $pdo = $this->db->getConnection();
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
      }
      
      // Stats
      elseif ($endpoint === 'stats') {
        $pdo = $this->db->getConnection();
        $data = [
          'qqBots' => $pdo->query("SELECT COUNT(*) FROM qq_bots")->fetchColumn(),
          'napcatBots' => $pdo->query("SELECT COUNT(*) FROM napcat_bots")->fetchColumn(),
          'messageLogs' => $pdo->query("SELECT COUNT(*) FROM message_logs")->fetchColumn(),
          'systemLogs' => $pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn(),
          'phpVersion' => PHP_VERSION,
          'swooleVersion' => defined('SWOOLE_VERSION') ? SWOOLE_VERSION : 'Unknown'
        ];
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
      
      // System Settings
      elseif ($endpoint === 'settings') {
        if ($method === 'GET') {
          $data['settings'] = $this->db->getAllConfigs();
        } elseif ($method === 'POST') {
          $changedKeys = [];
          foreach ($post as $key => $value) {
            $oldValue = $this->db->getConfig($key);
            if ($oldValue !== $value) {
              $changedKeys[] = $key;
            }
            $this->db->setConfig($key, $value);
          }
          $data['message'] = '系统设置保存成功';
          $this->db->addSystemLog('info', '保存系统设置', ['changed_keys' => $changedKeys]);
        }
      }
      
      else {
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
        $content = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>安装</title>';
        $content .= '<style>body{font-family:system-ui;background:#FFF0F5;padding:40px;text-align:center}';
        $content .= '.card{background:white;padding:40px;border-radius:20px;max-width:500px;margin:0 auto;box-shadow:0 10px 40px rgba(255,107,157,0.2)}';
        $content .= 'h1{color:#FF6B9D}';
        $content .= 'input{padding:12px;margin:8px;border:2px solid #FFB6C1;border-radius:10px;width:100%;box-sizing:border-box}';
        $content .= 'button{background:linear-gradient(135deg,#FF9EB1,#FF6B9D);color:white;border:none;padding:14px 30px;border-radius:25px;font-size:18px;cursor:pointer;margin-top:20px}';
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
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, datetime('now'))");
        $stmt->execute([$username, $passwordHash]);
        
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
        $content = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>登录</title>';
        $content .= '<style>body{font-family:system-ui;background:#FFF0F5;padding:40px;text-align:center}';
        $content .= '.card{background:white;padding:40px;border-radius:20px;max-width:400px;margin:0 auto;box-shadow:0 10px 40px rgba(255,107,157,0.2)}';
        $content .= 'h1{color:#FF6B9D}';
        $content .= 'input{padding:12px;margin:8px;border:2px solid #FFB6C1;border-radius:10px;width:100%;box-sizing:border-box}';
        $content .= 'button{background:linear-gradient(135deg,#FF9EB1,#FF6B9D);color:white;border:none;padding:14px 30px;border-radius:25px;font-size:18px;cursor:pointer;margin-top:20px}';
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
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
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
