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
        
        $method = $request->server['request_method'] ?? 'GET';
        $endpoint = substr($uri, strlen('/admin/api/'));
        $data = ['success' => true];
        
        try {
            // QQ Bots CRUD
            if ($endpoint === 'qq-bots') {
                $pdo = $this->db->getConnection();
                if ($method === 'GET') {
                    $data['bots'] = $pdo->query("SELECT * FROM qq_bots ORDER BY id DESC")->fetchAll();
                } elseif ($method === 'POST') {
                    $post = $request->post ?? [];
                    $stmt = $pdo->prepare("INSERT INTO qq_bots (appid, secret, sandbox) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $post['appid'] ?? '',
                        $post['secret'] ?? '',
                        isset($post['sandbox']) ? (int)$post['sandbox'] : 1
                    ]);
                    $data['id'] = $pdo->lastInsertId();
                    $data['message'] = 'QQ机器人添加成功';
                }
            } elseif (preg_match('#^qq-bots/(\d+)$#', $endpoint, $matches)) {
                $id = (int)$matches[1];
                $pdo = $this->db->getConnection();
                if ($method === 'DELETE') {
                    $stmt = $pdo->prepare("DELETE FROM qq_bots WHERE id = ?");
                    $stmt->execute([$id]);
                    $data['message'] = 'QQ机器人删除成功';
                }
            }
            
            // NapCat Bots CRUD
            elseif ($endpoint === 'napcat-bots') {
                $pdo = $this->db->getConnection();
                if ($method === 'GET') {
                    $data['bots'] = $pdo->query("SELECT * FROM napcat_bots ORDER BY id DESC")->fetchAll();
                } elseif ($method === 'POST') {
                    $post = $request->post ?? [];
                    $stmt = $pdo->prepare("INSERT INTO napcat_bots (qq, http_url, token) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $post['qq'] ?? '',
                        $post['http_url'] ?? '',
                        $post['token'] ?? ''
                    ]);
                    $data['id'] = $pdo->lastInsertId();
                    $data['message'] = 'NapCat机器人添加成功';
                }
            } elseif (preg_match('#^napcat-bots/(\d+)$#', $endpoint, $matches)) {
                $id = (int)$matches[1];
                $pdo = $this->db->getConnection();
                if ($method === 'DELETE') {
                    $stmt = $pdo->prepare("DELETE FROM napcat_bots WHERE id = ?");
                    $stmt->execute([$id]);
                    $data['message'] = 'NapCat机器人删除成功';
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
                    'swooleVersion' => SWOOLE_VERSION ?? 'Unknown'
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
                    $post = $request->post ?? [];
                    foreach ($post as $key => $value) {
                        $this->db->setConfig($key, $value);
                    }
                    $data['message'] = '系统设置保存成功';
                }
            }
            
            else {
                $response->status(404);
                $data = ['success' => false, 'error' => 'Unknown endpoint'];
            }
        } catch (Exception $e) {
            $response->status(500);
            $data = ['success' => false, 'error' => $e->getMessage()];
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
            $this->redirect($response, '/admin/login');
            return;
        }
        
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $this->redirect($response, '/admin/login');
            return;
        }
        
        $sessionId = $this->getSessionId($request);
        $this->setSession($response, $sessionId, ['admin_id' => $admin['id'], 'username' => $admin['username']]);
        
        $this->redirect($response, '/admin/index.html');
    }
}
