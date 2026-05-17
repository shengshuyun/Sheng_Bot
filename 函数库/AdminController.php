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
    
    private function html($content, $title = 'Sheng_Bot')
    {
        return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . $title . '</title><link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light">' . $content . '</body></html>';
    }
    
    public function handle($request, $response)
    {
        $uri = $request->server['request_uri'] ?? '/';
        
        $isInstalled = $this->db->isInstalled();
        
        // 如果已安装，不允许访问安装页面（除了 /admin/install/do）
        if ($isInstalled && substr($uri, 0, 14) === '/admin/install' && $uri !== '/admin/install/do') {
            $this->redirect($response, '/admin/');
            return;
        }
        
        // 如果未安装，只允许访问安装相关页面
        if (!$isInstalled && substr($uri, 0, 14) !== '/admin/install') {
            $this->redirect($response, '/admin/install');
            return;
        }
        
        // 如果已安装但未登录，只允许访问登录相关页面
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
        } elseif ($uri === '/admin/logout') {
            $this->doLogout($request, $response);
        } elseif ($uri === '/admin/' || $uri === '/admin') {
            $this->indexPage($request, $response);
        } elseif ($uri === '/admin/bots/qq') {
            $this->qqBotsPage($request, $response);
        } elseif ($uri === '/admin/bots/qq/add') {
            $this->addQqBot($request, $response);
        } elseif ($uri === '/admin/bots/qq/delete') {
            $this->deleteQqBot($request, $response);
        } elseif ($uri === '/admin/bots/napcat') {
            $this->napcatBotsPage($request, $response);
        } elseif ($uri === '/admin/bots/napcat/add') {
            $this->addNapcatBot($request, $response);
        } elseif ($uri === '/admin/bots/napcat/delete') {
            $this->deleteNapcatBot($request, $response);
        } elseif ($uri === '/admin/logs') {
            $this->messageLogsPage($request, $response);
        } elseif ($uri === '/admin/system/logs') {
            $this->systemLogsPage($request, $response);
        } elseif ($uri === '/admin/settings') {
            $this->settingsPage($request, $response);
        } elseif ($uri === '/admin/settings/save') {
            $this->saveSettings($request, $response);
        } elseif ($uri === '/admin/settings/password') {
            $this->savePassword($request, $response);
        } else {
            $response->status(404);
            $response->end($this->html('<div class="container mt-5"><h1>404 - 页面不存在</h1></div>'));
        }
    }
    
    private function redirect($response, $url)
    {
        $response->status(302);
        $response->header('Location', $url);
        $response->end('');
    }
    
    private function installPage($request, $response)
    {
        $content = '<div class="container"><div class="row justify-content-center"><div class="col-md-6"><div class="card mt-5 shadow"><div class="card-header bg-primary text-white"><h4 class="mb-0">Sheng_Bot 安装向导</h4></div><div class="card-body"><form method="POST" action="/admin/install/do"><h5 class="mb-3">管理员账号</h5><div class="mb-3"><label class="form-label">用户名</label><input type="text" name="username" class="form-control" required placeholder="请输入管理员用户名"></div><div class="mb-3"><label class="form-label">密码</label><input type="password" name="password" class="form-control" required placeholder="至少6位"></div><div class="mb-4"><label class="form-label">确认密码</label><input type="password" name="confirm_password" class="form-control" required placeholder="再次输入密码"></div><h5 class="mb-3">基本设置</h5><div class="mb-3"><label class="form-label">站点名称</label><input type="text" name="site_name" class="form-control" value="Sheng_Bot"></div><div class="row"><div class="col-md-6 mb-3"><label class="form-label">监听地址</label><input type="text" name="domain" class="form-control" value="0.0.0.0"></div><div class="col-md-3 mb-3"><label class="form-label">HTTP端口</label><input type="number" name="http_port" class="form-control" value="9501"></div><div class="col-md-3 mb-3"><label class="form-label">HTTPS端口</label><input type="number" name="https_port" class="form-control" value="9502"></div></div><button type="submit" class="btn btn-primary w-100">开始安装</button></form></div></div></div></div></div>';
        
        $response->end($this->html($content, '安装 - Sheng_Bot'));
    }
    
    private function doInstall($request, $response)
    {
        // 防双重提交：如果已经安装，直接跳转
        if ($this->db->isInstalled()) {
            $this->redirect($response, '/admin/');
            return;
        }
        
        $post = $request->post ?? [];
        $username = trim($post['username'] ?? '');
        $password = $post['password'] ?? '';
        $confirmPassword = $post['confirm_password'] ?? '';
        
        if (empty($username)) {
            return $this->showError($response, '请输入管理员用户名', '/admin/install');
        }
        
        if (strlen($password) < 6) {
            return $this->showError($response, '密码长度至少6位', '/admin/install');
        }
        
        if ($password !== $confirmPassword) {
            return $this->showError($response, '两次输入的密码不一致', '/admin/install');
        }
        
        $pdo = $this->db->getConnection();
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, datetime('now'))");
        $stmt->execute([$username, $passwordHash]);
        
        $configs = [
            'site_name' => $post['site_name'] ?? 'Sheng_Bot',
            'domain' => $post['domain'] ?? '0.0.0.0',
            'http_port' => intval($post['http_port'] ?? 9501),
            'https_port' => intval($post['https_port'] ?? 9502),
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
        
        $content = '<div class="container"><div class="row justify-content-center"><div class="col-md-6"><div class="card mt-5 shadow"><div class="card-header bg-success text-white"><h4 class="mb-0">安装成功</h4></div><div class="card-body"><p class="mb-4">恭喜，Sheng_Bot管理后台已成功安装。</p><a href="/admin/" class="btn btn-primary w-100">进入管理后台</a></div></div></div></div></div>';
        
        $response->end($this->html($content, '安装成功 - Sheng_Bot'));
    }
    
    private function loginPage($request, $response)
    {
        $content = '<div class="container"><div class="row justify-content-center"><div class="col-md-4"><div class="card mt-5 shadow"><div class="card-header bg-primary text-white"><h4 class="mb-0">Sheng_Bot 登录</h4></div><div class="card-body"><form method="POST" action="/admin/login/do"><div class="mb-3"><label class="form-label">用户名</label><input type="text" name="username" class="form-control" required></div><div class="mb-4"><label class="form-label">密码</label><input type="password" name="password" class="form-control" required></div><button type="submit" class="btn btn-primary w-100">登录</button></form></div></div></div></div></div>';
        
        $response->end($this->html($content, '登录 - Sheng_Bot'));
    }
    
    private function doLogin($request, $response)
    {
        $post = $request->post ?? [];
        $username = trim($post['username'] ?? '');
        $password = $post['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            return $this->showError($response, '请输入用户名和密码', '/admin/login');
        }
        
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            return $this->showError($response, '用户名或密码错误', '/admin/login');
        }
        
        $sessionId = $this->getSessionId($request);
        $this->setSession($response, $sessionId, ['admin_id' => $admin['id'], 'username' => $admin['username']]);
        
        $this->redirect($response, '/admin/');
    }
    
    private function doLogout($request, $response)
    {
        $this->destroySession($request, $response);
        $this->redirect($response, '/admin/login');
    }
    
    private function showError($response, $message, $backUrl)
    {
        $content = '<div class="container"><div class="row justify-content-center"><div class="col-md-6"><div class="card mt-5 shadow"><div class="card-header bg-danger text-white"><h4 class="mb-0">错误</h4></div><div class="card-body"><p class="mb-4">' . htmlspecialchars($message) . '</p><a href="' . $backUrl . '" class="btn btn-primary w-100">返回</a></div></div></div></div></div>';
        
        $response->end($this->html($content, '错误 - Sheng_Bot'));
    }
    
    private function sidebar($active = '')
    {
        $siteName = htmlspecialchars($this->db->getConfig('site_name', 'Sheng_Bot'));
        
        $menu = [
            'index' => ['title' => '控制台'],
            'qq' => ['title' => '官方QQ机器人'],
            'napcat' => ['title' => 'NapCat机器人'],
            'logs' => ['title' => '消息日志'],
            'system' => ['title' => '系统日志'],
            'settings' => ['title' => '系统设置'],
            'logout' => ['title' => '退出登录']
        ];
        
        $menuHtml = '';
        foreach ($menu as $key => $item) {
            $activeClass = $active === $key ? 'active' : '';
            $link = '/admin/';
            if ($key === 'index') $link = '/admin/';
            elseif ($key === 'qq') $link = '/admin/bots/qq';
            elseif ($key === 'napcat') $link = '/admin/bots/napcat';
            elseif ($key === 'logs') $link = '/admin/logs';
            elseif ($key === 'system') $link = '/admin/system/logs';
            elseif ($key === 'settings') $link = '/admin/settings';
            elseif ($key === 'logout') $link = '/admin/logout';
            $menuHtml .= '<li class="nav-item"><a class="nav-link ' . $activeClass . '" href="' . $link . '">' . $item['title'] . '</a></li>';
        }
        
        return '<div class="container-fluid"><div class="row"><nav class="col-md-3 col-lg-2 d-md-block sidebar collapse bg-dark text-white" style="min-height: 100vh;"><div class="position-sticky pt-3"><h5 class="px-3 mb-3">Sheng_Bot</h5><ul class="nav flex-column">' . $menuHtml . '</ul></div></nav>';
    }
    
    private function indexPage($request, $response)
    {
        $pdo = $this->db->getConnection();
        $qqBotsCount = $pdo->query("SELECT COUNT(*) FROM qq_bots")->fetchColumn();
        $napcatBotsCount = $pdo->query("SELECT COUNT(*) FROM napcat_bots")->fetchColumn();
        $messageLogsCount = $pdo->query("SELECT COUNT(*) FROM message_logs")->fetchColumn();
        
        $content = $this->sidebar('index') . '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"><h1 class="h2">控制台</h1></div><div class="row g-3 mb-4"><div class="col-md-4"><div class="card bg-primary text-white"><div class="card-body"><h5 class="card-title">官方QQ机器人</h5><h2 class="display-4">' . $qqBotsCount . '</h2></div></div></div><div class="col-md-4"><div class="card bg-success text-white"><div class="card-body"><h5 class="card-title">NapCat机器人</h5><h2 class="display-4">' . $napcatBotsCount . '</h2></div></div></div><div class="col-md-4"><div class="card bg-info text-white"><div class="card-body"><h5 class="card-title">消息日志</h5><h2 class="display-4">' . $messageLogsCount . '</h2></div></div></div></div><div class="row"><div class="col-md-6"><div class="card"><div class="card-header"><h5 class="mb-0">快速操作</h5></div><div class="card-body"><a href="/admin/bots/qq" class="btn btn-primary mb-2 w-100">添加官方QQ机器人</a><a href="/admin/bots/napcat" class="btn btn-success mb-2 w-100">添加NapCat机器人</a><a href="/admin/settings" class="btn btn-secondary w-100">系统设置</a></div></div></div><div class="col-md-6"><div class="card"><div class="card-header"><h5 class="mb-0">系统信息</h5></div><div class="card-body"><p>PHP版本: ' . PHP_VERSION . '</p><p>Swoole版本: ' . SWOOLE_VERSION . '</p><p>服务器时间: ' . date('Y-m-d H:i:s') . '</p></div></div></div></div></main></div></div>';
        
        $response->end($this->html($content, '控制台 - Sheng_Bot'));
    }
    
    private function qqBotsPage($request, $response)
    {
        $pdo = $this->db->getConnection();
        $bots = $pdo->query("SELECT * FROM qq_bots ORDER BY id DESC")->fetchAll();
        
        $botsHtml = '';
        foreach ($bots as $bot) {
            $sandbox = $bot['sandbox'] ? '<span class="badge bg-success">是</span>' : '<span class="badge bg-secondary">否</span>';
            $botsHtml .= '<tr><td>' . $bot['id'] . '</td><td>' . htmlspecialchars($bot['appid']) . '</td><td>' . htmlspecialchars(substr($bot['secret'], 0, 10)) . '...</td><td>' . $sandbox . '</td><td>' . $bot['created_at'] . '</td><td><form method="POST" action="/admin/bots/qq/delete" style="display: inline;"><input type="hidden" name="id" value="' . $bot['id'] . '"><button type="submit" class="btn btn-sm btn-danger">删除</button></form></td></tr>';
        }
        
        if (empty($bots)) {
            $botsHtml = '<tr><td colspan="6" class="text-center text-muted py-4">暂无机器人</td></tr>';
        }
        
        $content = $this->sidebar('qq') . '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"><h1 class="h2">官方QQ机器人</h1></div><div class="card mb-4"><div class="card-header"><h5 class="mb-0">添加机器人</h5></div><div class="card-body"><form method="POST" action="/admin/bots/qq/add"><div class="row"><div class="col-md-4 mb-3"><label class="form-label">AppID</label><input type="text" name="appid" class="form-control" required></div><div class="col-md-4 mb-3"><label class="form-label">Secret</label><input type="text" name="secret" class="form-control" required></div><div class="col-md-2 mb-3"><label class="form-label">&nbsp;</label><div class="form-check"><input type="checkbox" name="sandbox" class="form-check-input" id="sandbox" checked><label class="form-check-label" for="sandbox">沙箱模式</label></div></div><div class="col-md-2 mb-3"><label class="form-label">&nbsp;</label><button type="submit" class="btn btn-primary w-100">添加</button></div></div></form></div></div><div class="card"><div class="card-header"><h5 class="mb-0">机器人列表</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>ID</th><th>AppID</th><th>Secret</th><th>沙箱模式</th><th>添加时间</th><th>操作</th></tr></thead><tbody>' . $botsHtml . '</tbody></table></div></div></div></main></div></div>';
        
        $response->end($this->html($content, '官方QQ机器人 - Sheng_Bot'));
    }
    
    private function addQqBot($request, $response)
    {
        $post = $request->post ?? [];
        $appid = trim($post['appid'] ?? '');
        $secret = trim($post['secret'] ?? '');
        $sandbox = isset($post['sandbox']) ? 1 : 0;
        
        if ($appid && $secret) {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("INSERT INTO qq_bots (appid, secret, sandbox, created_at) VALUES (?, ?, ?, datetime('now'))");
            $stmt->execute([$appid, $secret, $sandbox]);
        }
        
        $this->redirect($response, '/admin/bots/qq');
    }
    
    private function deleteQqBot($request, $response)
    {
        $post = $request->post ?? [];
        $id = intval($post['id'] ?? 0);
        if ($id) {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("DELETE FROM qq_bots WHERE id = ?");
            $stmt->execute([$id]);
        }
        $this->redirect($response, '/admin/bots/qq');
    }
    
    private function napcatBotsPage($request, $response)
    {
        $pdo = $this->db->getConnection();
        $bots = $pdo->query("SELECT * FROM napcat_bots ORDER BY id DESC")->fetchAll();
        
        $botsHtml = '';
        foreach ($bots as $bot) {
            $token = $bot['token'] ? htmlspecialchars(substr($bot['token'], 0, 10)) . '...' : '-';
            $botsHtml .= '<tr><td>' . $bot['id'] . '</td><td>' . htmlspecialchars($bot['qq']) . '</td><td>' . htmlspecialchars($bot['http_url']) . '</td><td>' . $token . '</td><td>' . $bot['created_at'] . '</td><td><form method="POST" action="/admin/bots/napcat/delete" style="display: inline;"><input type="hidden" name="id" value="' . $bot['id'] . '"><button type="submit" class="btn btn-sm btn-danger">删除</button></form></td></tr>';
        }
        
        if (empty($bots)) {
            $botsHtml = '<tr><td colspan="6" class="text-center text-muted py-4">暂无机器人</td></tr>';
        }
        
        $content = $this->sidebar('napcat') . '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"><h1 class="h2">NapCat机器人</h1></div><div class="card mb-4"><div class="card-header"><h5 class="mb-0">添加机器人</h5></div><div class="card-body"><form method="POST" action="/admin/bots/napcat/add"><div class="row"><div class="col-md-3 mb-3"><label class="form-label">QQ号</label><input type="text" name="qq" class="form-control" required></div><div class="col-md-5 mb-3"><label class="form-label">HTTP地址</label><input type="text" name="http_url" class="form-control" placeholder="http://127.0.0.1:3000" required></div><div class="col-md-3 mb-3"><label class="form-label">Token</label><input type="text" name="token" class="form-control"></div><div class="col-md-1 mb-3"><label class="form-label">&nbsp;</label><button type="submit" class="btn btn-success w-100">添加</button></div></div></form></div></div><div class="card"><div class="card-header"><h5 class="mb-0">机器人列表</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>ID</th><th>QQ号</th><th>HTTP地址</th><th>Token</th><th>添加时间</th><th>操作</th></tr></thead><tbody>' . $botsHtml . '</tbody></table></div></div></div></main></div></div>';
        
        $response->end($this->html($content, 'NapCat机器人 - Sheng_Bot'));
    }
    
    private function addNapcatBot($request, $response)
    {
        $post = $request->post ?? [];
        $qq = trim($post['qq'] ?? '');
        $httpUrl = trim($post['http_url'] ?? '');
        $token = trim($post['token'] ?? '');
        
        if ($qq && $httpUrl) {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("INSERT INTO napcat_bots (qq, http_url, token, created_at) VALUES (?, ?, ?, datetime('now'))");
            $stmt->execute([$qq, $httpUrl, $token]);
        }
        
        $this->redirect($response, '/admin/bots/napcat');
    }
    
    private function deleteNapcatBot($request, $response)
    {
        $post = $request->post ?? [];
        $id = intval($post['id'] ?? 0);
        if ($id) {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare("DELETE FROM napcat_bots WHERE id = ?");
            $stmt->execute([$id]);
        }
        $this->redirect($response, '/admin/bots/napcat');
    }
    
    private function messageLogsPage($request, $response)
    {
        $content = $this->sidebar('logs') . '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"><h1 class="h2">消息日志</h1></div><div class="card mb-4"><div class="card-body"><div class="card"><div class="card-body">消息日志功能开发中...</div></div></div></div></main></div></div>';
        
        $response->end($this->html($content, '消息日志 - Sheng_Bot'));
    }
    
    private function systemLogsPage($request, $response)
    {
        $content = $this->sidebar('system') . '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"><h1 class="h2">系统日志</h1></div><div class="card mb-4"><div class="card-body"><div class="card"><div class="card-body">系统日志功能开发中...</div></div></div></div></main></div></div>';
        
        $response->end($this->html($content, '系统日志 - Sheng_Bot'));
    }
    
    private function settingsPage($request, $response)
    {
        $siteName = htmlspecialchars($this->db->getConfig('site_name', 'Sheng_Bot'));
        $domain = htmlspecialchars($this->db->getConfig('domain', '0.0.0.0'));
        $httpPort = $this->db->getConfig('http_port', 9501);
        $httpsPort = $this->db->getConfig('https_port', 9502);
        
        $content = $this->sidebar('settings') . '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"><h1 class="h2">系统设置</h1></div><div class="row"><div class="col-md-6"><div class="card mb-4"><div class="card-header"><h5 class="mb-0">基本设置</h5></div><div class="card-body"><form method="POST" action="/admin/settings/save"><div class="mb-3"><label class="form-label">站点名称</label><input type="text" name="site_name" class="form-control" value="' . $siteName . '"></div><div class="mb-3"><label class="form-label">监听地址</label><input type="text" name="domain" class="form-control" value="' . $domain . '"></div><div class="row"><div class="col-md-6 mb-3"><label class="form-label">HTTP端口</label><input type="number" name="http_port" class="form-control" value="' . $httpPort . '"></div><div class="col-md-6 mb-3"><label class="form-label">HTTPS端口</label><input type="number" name="https_port" class="form-control" value="' . $httpsPort . '"></div></div><button type="submit" class="btn btn-primary">保存设置</button></form></div></div><div class="card mb-4"><div class="card-header"><h5 class="mb-0">修改密码</h5></div><div class="card-body"><form method="POST" action="/admin/settings/password"><div class="mb-3"><label class="form-label">原密码</label><input type="password" name="old_password" class="form-control" required></div><div class="mb-3"><label class="form-label">新密码</label><input type="password" name="new_password" class="form-control" required></div><div class="mb-3"><label class="form-label">确认新密码</label><input type="password" name="confirm_password" class="form-control" required></div><button type="submit" class="btn btn-success">修改密码</button></form></div></div></div><div class="col-md-6"><div class="card"><div class="card-header"><h5 class="mb-0">系统信息</h5></div><div class="card-body"><p>PHP版本: ' . PHP_VERSION . '</p><p>Swoole版本: ' . SWOOLE_VERSION . '</p><p>服务器时间: ' . date('Y-m-d H:i:s') . '</p></div></div></div></div></main></div></div>';
        
        $response->end($this->html($content, '系统设置 - Sheng_Bot'));
    }
    
    private function saveSettings($request, $response)
    {
        $post = $request->post ?? [];
        $this->db->setConfig('site_name', trim($post['site_name'] ?? ''));
        $this->db->setConfig('domain', trim($post['domain'] ?? ''));
        $this->db->setConfig('http_port', intval($post['http_port'] ?? 9501));
        $this->db->setConfig('https_port', intval($post['https_port'] ?? 9502));
        $this->redirect($response, '/admin/settings');
    }
    
    private function savePassword($request, $response)
    {
        $post = $request->post ?? [];
        $oldPassword = $post['old_password'] ?? '';
        $newPassword = $post['new_password'] ?? '';
        $confirmPassword = $post['confirm_password'] ?? '';
        
        $session = $this->getSession($request);
        $adminId = $session['admin_id'] ?? 0;
        
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();
        
        if (!$admin || !password_verify($oldPassword, $admin['password_hash'])) {
            return $this->showError($response, '原密码错误', '/admin/settings');
        }
        
        if (strlen($newPassword) < 6 || $newPassword !== $confirmPassword) {
            return $this->showError($response, '新密码长度至少6位，且两次输入一致', '/admin/settings');
        }
        
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
        $updateStmt->execute([$newHash, $adminId]);
        
        $this->redirect($response, '/admin/settings');
    }
}
