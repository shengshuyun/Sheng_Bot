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
        $bootstrapCss = 'https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.3.3/css/bootstrap.min.css';
        $bootstrapJs = 'https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.3.3/js/bootstrap.bundle.min.js';
        $fontAwesome = 'https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        $customCss = '/admin/styles.css?t=' . time();
        
        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sheng_Bot 管理后台 - 现代化机器人管理平台">
    <title>' . htmlspecialchars($title) . '</title>
    <link href="' . $bootstrapCss . '" rel="stylesheet">
    <link href="' . $fontAwesome . '" rel="stylesheet">
    <link href="' . $customCss . '" rel="stylesheet">
</head>
<body>
    ' . $content . '
    <script src="' . $bootstrapJs . '"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const sidebar = document.querySelector(".sidebar");
            const sidebarOverlay = document.querySelector(".sidebar-overlay");
            const toggleBtn = document.querySelector(".sidebar-toggle");
            
            if (toggleBtn) {
                toggleBtn.addEventListener("click", function() {
                    sidebar.classList.toggle("show");
                    if (sidebarOverlay) {
                        sidebarOverlay.classList.toggle("show");
                    }
                });
            }
            
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener("click", function() {
                    sidebar.classList.remove("show");
                    sidebarOverlay.classList.remove("show");
                });
            }
            
            const cards = document.querySelectorAll(".card");
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + "s";
            });
            
            const tableRows = document.querySelectorAll("tbody tr");
            tableRows.forEach((row, index) => {
                row.style.animationDelay = (index * 0.05) + "s";
                row.classList.add("animate-fadeIn");
            });
        });
        
        function showToast(message, type = "success") {
            const toastContainer = document.createElement("div");
            toastContainer.className = "toast-container";
            toastContainer.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header">
                        <i class="fas fa-${type === "success" ? "check-circle" : "exclamation-circle"} me-2"></i>
                        <strong class="me-auto">提示</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            `;
            document.body.appendChild(toastContainer);
            
            const toast = new bootstrap.Toast(toastContainer.querySelector(".toast"));
            toast.show();
            
            toastContainer.querySelector(".toast").addEventListener("hidden.bs.toast", function() {
                toastContainer.remove();
            });
        }
        
        function confirmDelete(event, message = "确定要删除吗？") {
            if (!confirm(message)) {
                event.preventDefault();
                return false;
            }
            showToast("删除成功", "success");
            return true;
        }
    </script>
</body>
</html>';
    }
    
    public function handle($request, $response)
    {
        $uri = $request->server['request_uri'] ?? '/';
        $uri = strtok($uri, '?');
        
        $isInstalled = $this->db->isInstalled();
        
        if ($isInstalled && substr($uri, 0, 14) === '/admin/install' && $uri !== '/admin/install/do') {
            $this->redirect($response, '/admin/');
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
        } elseif ($uri === '/admin/settings/save-advanced') {
            $this->saveAdvancedSettings($request, $response);
        } else {
            $response->status(404);
            $response->end($this->errorPage('页面不存在', '您访问的页面不存在或已被删除。'));
        }
    }
    
    private function redirect($response, $url)
    {
        $response->status(302);
        $response->header('Location', $url);
        $response->end('');
    }
    
    private function errorPage($title, $message)
    {
        $content = '<div class="container">
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-md-6 col-lg-4">
                    <div class="card text-center animate-fadeInUp">
                        <div class="card-header bg-danger">
                            <i class="fas fa-exclamation-triangle fa-3x text-white"></i>
                        </div>
                        <div class="card-body">
                            <h2 class="text-danger mb-3">' . htmlspecialchars($title) . '</h2>
                            <p class="text-muted mb-4">' . htmlspecialchars($message) . '</p>
                            <a href="/admin/" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>返回首页
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        return $content;
    }
    
    private function installPage($request, $response)
    {
        $content = '<div class="container">
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-md-8 col-lg-6">
                    <div class="card shadow-lg animate-fadeInUp">
                        <div class="card-header text-center py-4">
                            <h3 class="mb-0">
                                <i class="fas fa-rocket me-2"></i>Sheng_Bot 安装向导
                            </h3>
                            <p class="mb-0 mt-2 opacity-75">开始您的机器人管理之旅</p>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="/admin/install/do" class="needs-validation" novalidate>
                                <div class="alert alert-info mb-4">
                                    <i class="fas fa-user-shield me-2"></i>
                                    <strong>管理员账号</strong>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-user me-2"></i>用户名
                                        </label>
                                        <input type="text" name="username" class="form-control form-control-lg" 
                                               required placeholder="请输入管理员用户名" autofocus>
                                        <div class="invalid-feedback">请输入用户名</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-key me-2"></i>密码
                                        </label>
                                        <input type="password" name="password" class="form-control form-control-lg" 
                                               required placeholder="至少6位" minlength="6">
                                        <div class="invalid-feedback">密码长度至少6位</div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-lock me-2"></i>确认密码
                                    </label>
                                    <input type="password" name="confirm_password" class="form-control form-control-lg" 
                                           required placeholder="再次输入密码">
                                    <div class="invalid-feedback">请再次输入密码</div>
                                </div>
                                
                                <hr class="my-4">
                                
                                <div class="alert alert-success mb-4">
                                    <i class="fas fa-cog me-2"></i>
                                    <strong>基本设置</strong>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-globe me-2"></i>站点名称
                                    </label>
                                    <input type="text" name="site_name" class="form-control form-control-lg" 
                                           value="Sheng_Bot">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-network-wired me-2"></i>监听地址
                                        </label>
                                        <input type="text" name="domain" class="form-control" value="0.0.0.0">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-door-open me-2"></i>HTTP端口
                                        </label>
                                        <input type="number" name="http_port" class="form-control" value="9501">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-lock me-2"></i>HTTPS端口
                                        </label>
                                        <input type="number" name="https_port" class="form-control" value="9502">
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-download me-2"></i>开始安装
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        $response->end($this->html($content, '安装 - Sheng_Bot'));
    }
    
    private function doInstall($request, $response)
    {
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
        
        $content = '<div class="container">
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-md-6 col-lg-5">
                    <div class="card shadow-lg text-center animate-fadeInUp">
                        <div class="card-header bg-success py-4">
                            <i class="fas fa-check-circle fa-4x text-white animate-pulse"></i>
                        </div>
                        <div class="card-body p-5">
                            <h2 class="text-success mb-3">安装成功</h2>
                            <p class="text-muted mb-4">恭喜，Sheng_Bot管理后台已成功安装</p>
                            <div class="d-grid gap-2">
                                <a href="/admin/" class="btn btn-primary btn-lg">
                                    <i class="fas fa-rocket me-2"></i>进入管理后台
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        $response->end($this->html($content, '安装成功 - Sheng_Bot'));
    }
    
    private function loginPage($request, $response)
    {
        $content = '<div class="container">
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-md-5 col-lg-4">
                    <div class="card shadow-lg animate-fadeInUp">
                        <div class="card-header text-center py-4">
                            <h3 class="mb-0">
                                <i class="fas fa-user-circle me-2"></i>Sheng_Bot
                            </h3>
                            <p class="mb-0 mt-2 opacity-75">管理后台登录</p>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="/admin/login/do" class="needs-validation" novalidate>
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-user me-2"></i>用户名
                                    </label>
                                    <input type="text" name="username" class="form-control form-control-lg" 
                                           required autofocus placeholder="请输入用户名">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-key me-2"></i>密码
                                    </label>
                                    <input type="password" name="password" class="form-control form-control-lg" 
                                           required placeholder="请输入密码">
                                </div>
                                <div class="d-grid gap-2 mt-5">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>登录
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
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
        $content = '<div class="container">
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-md-5 col-lg-4">
                    <div class="card shadow-lg text-center animate-fadeInUp">
                        <div class="card-header bg-danger py-4">
                            <i class="fas fa-exclamation-circle fa-3x text-white"></i>
                        </div>
                        <div class="card-body p-4">
                            <h3 class="text-danger mb-3">出错了</h3>
                            <p class="text-muted mb-4">' . htmlspecialchars($message) . '</p>
                            <a href="' . htmlspecialchars($backUrl) . '" class="btn btn-primary btn-lg">
                                <i class="fas fa-arrow-left me-2"></i>返回
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        $response->end($this->html($content, '错误 - Sheng_Bot'));
    }
    
    private function sidebar($active = '')
    {
        $siteName = htmlspecialchars($this->db->getConfig('site_name', 'Sheng_Bot'));
        
        $menu = [
            'index' => ['title' => '控制台', 'icon' => 'fa-home'],
            'qq' => ['title' => '官方QQ机器人', 'icon' => 'fa-robot'],
            'napcat' => ['title' => 'NapCat机器人', 'icon' => 'fa-terminal'],
            'logs' => ['title' => '消息日志', 'icon' => 'fa-envelope'],
            'system' => ['title' => '系统日志', 'icon' => 'fa-clipboard-list'],
            'settings' => ['title' => '系统设置', 'icon' => 'fa-cog'],
            'logout' => ['title' => '退出登录', 'icon' => 'fa-sign-out-alt']
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
            
            $menuHtml .= '<li class="nav-item">
                <a class="nav-link ' . $activeClass . '" href="' . $link . '">
                    <i class="fas ' . $item['icon'] . ' me-3"></i>' . $item['title'] . '
                </a>
            </li>';
        }
        
        return '<div class="sidebar-overlay"></div>
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse show">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <h5>
                        <i class="fas fa-robot me-2"></i>' . $siteName . '
                    </h5>
                </div>
                <ul class="nav flex-column">' . $menuHtml . '</ul>
            </div>
        </nav>';
    }
    
    private function mobileHeader()
    {
        return '<div class="mobile-header d-md-none">
            <button class="sidebar-toggle btn btn-primary">
                <i class="fas fa-bars"></i>
            </button>
            <span class="navbar-brand ms-3">' . htmlspecialchars($this->db->getConfig('site_name', 'Sheng_Bot')) . '</span>
        </div>';
    }
    
    private function indexPage($request, $response)
    {
        $pdo = $this->db->getConnection();
        $qqBotsCount = $pdo->query("SELECT COUNT(*) FROM qq_bots")->fetchColumn();
        $napcatBotsCount = $pdo->query("SELECT COUNT(*) FROM napcat_bots")->fetchColumn();
        $messageLogsCount = $pdo->query("SELECT COUNT(*) FROM message_logs")->fetchColumn();
        
        $session = $this->getSession($request);
        $username = htmlspecialchars($session['username'] ?? 'Admin');
        
        $content = '<div class="container-fluid">
            ' . $this->mobileHeader() . '
            <div class="row">
                ' . $this->sidebar('index') . '
                
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
                        <div>
                            <h1 class="h2 mb-1">
                                <i class="fas fa-tachometer-alt me-2 text-primary"></i>控制台
                            </h1>
                            <p class="text-muted mb-0">
                                <i class="fas fa-user me-2"></i>欢迎回来，' . $username . '
                            </p>
                        </div>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <span class="badge bg-info me-2">
                                <i class="fas fa-clock me-1"></i>' . date('Y-m-d H:i') . '
                            </span>
                        </div>
                    </div>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center py-4">
                                    <div class="display-4 text-primary mb-2">' . $qqBotsCount . '</div>
                                    <h5 class="card-title">
                                        <i class="fas fa-robot me-2"></i>官方QQ机器人
                                    </h5>
                                    <a href="/admin/bots/qq" class="btn btn-outline-primary btn-sm mt-3">
                                        <i class="fas fa-plus me-2"></i>管理
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center py-4">
                                    <div class="display-4 text-success mb-2">' . $napcatBotsCount . '</div>
                                    <h5 class="card-title">
                                        <i class="fas fa-terminal me-2"></i>NapCat机器人
                                    </h5>
                                    <a href="/admin/bots/napcat" class="btn btn-outline-success btn-sm mt-3">
                                        <i class="fas fa-plus me-2"></i>管理
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center py-4">
                                    <div class="display-4 text-info mb-2">' . $messageLogsCount . '</div>
                                    <h5 class="card-title">
                                        <i class="fas fa-envelope me-2"></i>消息日志
                                    </h5>
                                    <a href="/admin/logs" class="btn btn-outline-info btn-sm mt-3">
                                        <i class="fas fa-eye me-2"></i>查看
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-bolt me-2"></i>快速操作
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="/admin/bots/qq" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>添加官方QQ机器人
                                        </a>
                                        <a href="/admin/bots/napcat" class="btn btn-success">
                                            <i class="fas fa-plus me-2"></i>添加NapCat机器人
                                        </a>
                                        <a href="/admin/settings" class="btn btn-secondary">
                                            <i class="fas fa-cog me-2"></i>系统设置
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-server me-2"></i>系统信息
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fab fa-php me-2 text-primary"></i>PHP版本</span>
                                            <span class="badge bg-primary">' . PHP_VERSION . '</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fas fa-sync me-2 text-success"></i>Swoole版本</span>
                                            <span class="badge bg-success">' . SWOOLE_VERSION . '</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fas fa-clock me-2 text-info"></i>服务器时间</span>
                                            <span class="badge bg-info">' . date('Y-m-d H:i:s') . '</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fas fa-database me-2 text-warning"></i>数据库状态</span>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>正常</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>';
        
        $response->end($this->html($content, '控制台 - Sheng_Bot'));
    }
    
    private function qqBotsPage($request, $response)
    {
        $pdo = $this->db->getConnection();
        $bots = $pdo->query("SELECT * FROM qq_bots ORDER BY id DESC")->fetchAll();
        
        $botsHtml = '';
        if (!empty($bots)) {
            foreach ($bots as $bot) {
                $sandbox = $bot['sandbox'] ? 
                    '<span class="badge bg-success"><i class="fas fa-check me-1"></i>沙箱</span>' : 
                    '<span class="badge bg-secondary"><i class="fas fa-times me-1"></i>正式</span>';
                $botsHtml .= '<tr class="animate-fadeIn">
                    <td><strong>#' . $bot['id'] . '</strong></td>
                    <td><code>' . htmlspecialchars($bot['appid']) . '</code></td>
                    <td><code>' . htmlspecialchars(substr($bot['secret'], 0, 10)) . '...</code></td>
                    <td>' . $sandbox . '</td>
                    <td><small class="text-muted">' . $bot['created_at'] . '</small></td>
                    <td>
                        <form method="POST" action="/admin/bots/qq/delete" class="d-inline" onsubmit="return confirmDelete(event, \'确定要删除这个机器人吗？\');">
                            <input type="hidden" name="id" value="' . $bot['id'] . '">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>';
            }
        } else {
            $botsHtml = '<tr><td colspan="6" class="text-center text-muted py-5">
                <i class="fas fa-robot fa-3x mb-3 opacity-25"></i>
                <p class="mb-0">暂无机器人</p>
                <small>点击上方按钮添加第一个机器人</small>
            </td></tr>';
        }
        
        $content = '<div class="container-fluid">
            ' . $this->mobileHeader() . '
            <div class="row">
                ' . $this->sidebar('qq') . '
                
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
                        <div>
                            <h1 class="h2 mb-1">
                                <i class="fas fa-robot me-2 text-primary"></i>官方QQ机器人
                            </h1>
                            <p class="text-muted mb-0">管理您的官方QQ机器人账号</p>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-plus me-2"></i>添加机器人
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/admin/bots/qq/add" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="fas fa-id-card me-2"></i>AppID
                                    </label>
                                    <input type="text" name="appid" class="form-control" required 
                                           placeholder="请输入AppID">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">
                                        <i class="fas fa-key me-2"></i>Secret
                                    </label>
                                    <input type="text" name="secret" class="form-control" required 
                                           placeholder="请输入Secret密钥">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">
                                        <i class="fas fa-shield-alt me-2"></i>沙箱模式
                                    </label>
                                    <div class="form-check form-switch mt-2">
                                        <input type="checkbox" name="sandbox" class="form-check-input" 
                                               id="sandbox" checked>
                                        <label class="form-check-label" for="sandbox">启用</label>
                                    </div>
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>机器人列表
                                <span class="badge bg-light text-dark ms-2">' . count($bots) . '</span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-hashtag me-2"></i>ID</th>
                                            <th><i class="fas fa-id-card me-2"></i>AppID</th>
                                            <th><i class="fas fa-key me-2"></i>Secret</th>
                                            <th><i class="fas fa-cog me-2"></i>模式</th>
                                            <th><i class="fas fa-clock me-2"></i>添加时间</th>
                                            <th><i class="fas fa-tools me-2"></i>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>' . $botsHtml . '</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>';
        
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
        if (!empty($bots)) {
            foreach ($bots as $bot) {
                $token = $bot['token'] ? 
                    '<code>' . htmlspecialchars(substr($bot['token'], 0, 10)) . '...</code>' : 
                    '<span class="badge bg-secondary">无Token</span>';
                $botsHtml .= '<tr class="animate-fadeIn">
                    <td><strong>#' . $bot['id'] . '</strong></td>
                    <td><code>' . htmlspecialchars($bot['qq']) . '</code></td>
                    <td><small>' . htmlspecialchars($bot['http_url']) . '</small></td>
                    <td>' . $token . '</td>
                    <td><small class="text-muted">' . $bot['created_at'] . '</small></td>
                    <td>
                        <form method="POST" action="/admin/bots/napcat/delete" class="d-inline" onsubmit="return confirmDelete(event, \'确定要删除这个机器人吗？\');">
                            <input type="hidden" name="id" value="' . $bot['id'] . '">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>';
            }
        } else {
            $botsHtml = '<tr><td colspan="6" class="text-center text-muted py-5">
                <i class="fas fa-terminal fa-3x mb-3 opacity-25"></i>
                <p class="mb-0">暂无机器人</p>
                <small>点击上方按钮添加第一个机器人</small>
            </td></tr>';
        }
        
        $content = '<div class="container-fluid">
            ' . $this->mobileHeader() . '
            <div class="row">
                ' . $this->sidebar('napcat') . '
                
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
                        <div>
                            <h1 class="h2 mb-1">
                                <i class="fas fa-terminal me-2 text-success"></i>NapCat机器人
                            </h1>
                            <p class="text-muted mb-0">管理您的NapCat机器人连接</p>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-success">
                            <h5 class="mb-0">
                                <i class="fas fa-plus me-2"></i>添加机器人
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/admin/bots/napcat/add" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">
                                        <i class="fas fa-user me-2"></i>QQ号
                                    </label>
                                    <input type="text" name="qq" class="form-control" required 
                                           placeholder="请输入QQ号">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">
                                        <i class="fas fa-link me-2"></i>HTTP地址
                                    </label>
                                    <input type="text" name="http_url" class="form-control" 
                                           placeholder="http://127.0.0.1:3000" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">
                                        <i class="fas fa-key me-2"></i>Token
                                    </label>
                                    <input type="text" name="token" class="form-control" 
                                           placeholder="可选">
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>机器人列表
                                <span class="badge bg-light text-dark ms-2">' . count($bots) . '</span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-hashtag me-2"></i>ID</th>
                                            <th><i class="fas fa-user me-2"></i>QQ号</th>
                                            <th><i class="fas fa-link me-2"></i>HTTP地址</th>
                                            <th><i class="fas fa-key me-2"></i>Token</th>
                                            <th><i class="fas fa-clock me-2"></i>添加时间</th>
                                            <th><i class="fas fa-tools me-2"></i>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>' . $botsHtml . '</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>';
        
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
        $get = $request->get ?? [];
        $botType = $get['bot_type'] ?? '';
        $logs = $this->db->getMessageLogs(200, $botType);
        
        $logsHtml = '';
        if (!empty($logs)) {
            foreach ($logs as $log) {
                $botTypeLabel = $log['bot_type'] === 'qq' ? 
                    '<span class="badge bg-primary"><i class="fas fa-robot me-1"></i>QQ</span>' : 
                    '<span class="badge bg-success"><i class="fas fa-terminal me-1"></i>NapCat</span>';
                $content = htmlspecialchars($log['content'] ?? '-');
                $contentShort = strlen($content) > 50 ? substr($content, 0, 50) . '...' : $content;
                
                $logsHtml .= '<tr class="animate-fadeIn">
                    <td>' . $botTypeLabel . '</td>
                    <td><code>' . htmlspecialchars($log['bot_id']) . '</code></td>
                    <td><small>' . htmlspecialchars($log['user_id'] ?? '-') . '</small></td>
                    <td><small>' . htmlspecialchars($log['group_id'] ?? '-') . '</small></td>
                    <td><span class="badge bg-secondary">' . htmlspecialchars($log['message_type'] ?? '-') . '</span></td>
                    <td><small data-bs-toggle="tooltip" title="' . $content . '">' . $contentShort . '</small></td>
                    <td><small class="text-muted">' . $log['created_at'] . '</small></td>
                </tr>';
            }
        } else {
            $logsHtml = '<tr><td colspan="7" class="text-center text-muted py-5">
                <i class="fas fa-envelope-open fa-3x mb-3 opacity-25"></i>
                <p class="mb-0">暂无消息日志</p>
            </td></tr>';
        }
        
        $qqSelected = $botType === 'qq' ? 'selected' : '';
        $napcatSelected = $botType === 'napcat' ? 'selected' : '';
        
        $content = '<div class="container-fluid">
            ' . $this->mobileHeader() . '
            <div class="row">
                ' . $this->sidebar('logs') . '
                
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
                        <div>
                            <h1 class="h2 mb-1">
                                <i class="fas fa-envelope me-2 text-info"></i>消息日志
                            </h1>
                            <p class="text-muted mb-0">查看所有机器人的消息记录</p>
                        </div>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <form method="GET" action="/admin/logs" class="d-flex gap-2">
                                <select name="bot_type" class="form-select">
                                    <option value="">全部机器人</option>
                                    <option value="qq" ' . $qqSelected . '>QQ官方机器人</option>
                                    <option value="napcat" ' . $napcatSelected . '>NapCat机器人</option>
                                </select>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>筛选
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>消息记录
                                <span class="badge bg-light text-dark ms-2">' . count($logs) . ' 条</span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th><i class="fas fa-robot me-2"></i>类型</th>
                                            <th><i class="fas fa-id-badge me-2"></i>机器人ID</th>
                                            <th><i class="fas fa-user me-2"></i>用户ID</th>
                                            <th><i class="fas fa-users me-2"></i>群组ID</th>
                                            <th><i class="fas fa-tag me-2"></i>消息类型</th>
                                            <th><i class="fas fa-comment me-2"></i>内容</th>
                                            <th><i class="fas fa-clock me-2"></i>时间</th>
                                        </tr>
                                    </thead>
                                    <tbody>' . $logsHtml . '</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>';
        
        $response->end($this->html($content, '消息日志 - Sheng_Bot'));
    }
    
    private function systemLogsPage($request, $response)
    {
        $get = $request->get ?? [];
        $level = $get['level'] ?? '';
        $logs = $this->db->getSystemLogs(200, $level);
        
        $levelColors = [
            'debug' => 'secondary',
            'info' => 'info',
            'warning' => 'warning',
            'error' => 'danger',
            'critical' => 'dark'
        ];
        
        $levelIcons = [
            'debug' => 'fa-bug',
            'info' => 'fa-info-circle',
            'warning' => 'fa-exclamation-triangle',
            'error' => 'fa-times-circle',
            'critical' => 'fa-skull'
        ];
        
        $logsHtml = '';
        if (!empty($logs)) {
            foreach ($logs as $log) {
                $levelClass = $levelColors[$log['level']] ?? 'secondary';
                $levelIcon = $levelIcons[$log['level']] ?? 'fa-circle';
                $levelLabel = '<span class="badge bg-' . $levelClass . '">
                    <i class="fas ' . $levelIcon . ' me-1"></i>' . strtoupper(htmlspecialchars($log['level'])) . '
                </span>';
                $message = htmlspecialchars($log['message']);
                $context = $log['context'] ? htmlspecialchars($log['context']) : '';
                $contextHtml = $context ? '<code class="d-block">' . $context . '</code>' : '-';
                
                $logsHtml .= '<tr class="animate-fadeIn">
                    <td>' . $levelLabel . '</td>
                    <td><small>' . $message . '</small></td>
                    <td style="max-width: 300px;"><small>' . $contextHtml . '</small></td>
                    <td><small class="text-muted">' . $log['created_at'] . '</small></td>
                </tr>';
            }
        } else {
            $logsHtml = '<tr><td colspan="4" class="text-center text-muted py-5">
                <i class="fas fa-clipboard-list fa-3x mb-3 opacity-25"></i>
                <p class="mb-0">暂无系统日志</p>
            </td></tr>';
        }
        
        $levels = ['debug', 'info', 'warning', 'error', 'critical'];
        $levelOptions = '<option value="">全部级别</option>';
        foreach ($levels as $lvl) {
            $selected = $level === $lvl ? 'selected' : '';
            $levelOptions .= '<option value="' . $lvl . '" ' . $selected . '>' . strtoupper($lvl) . '</option>';
        }
        
        $content = '<div class="container-fluid">
            ' . $this->mobileHeader() . '
            <div class="row">
                ' . $this->sidebar('system') . '
                
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
                        <div>
                            <h1 class="h2 mb-1">
                                <i class="fas fa-clipboard-list me-2 text-warning"></i>系统日志
                            </h1>
                            <p class="text-muted mb-0">监控系统运行状态和错误信息</p>
                        </div>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <form method="GET" action="/admin/system/logs" class="d-flex gap-2">
                                <select name="level" class="form-select">
                                    ' . $levelOptions . '
                                </select>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>筛选
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>系统日志
                                <span class="badge bg-light text-dark ms-2">' . count($logs) . ' 条</span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th style="width: 120px;"><i class="fas fa-flag me-2"></i>级别</th>
                                            <th><i class="fas fa-comment me-2"></i>消息</th>
                                            <th style="width: 300px;"><i class="fas fa-code me-2"></i>上下文</th>
                                            <th style="width: 180px;"><i class="fas fa-clock me-2"></i>时间</th>
                                        </tr>
                                    </thead>
                                    <tbody>' . $logsHtml . '</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>';
        
        $response->end($this->html($content, '系统日志 - Sheng_Bot'));
    }
    
    private function settingsPage($request, $response)
    {
        $siteName = htmlspecialchars($this->db->getConfig('site_name', 'Sheng_Bot'));
        $domain = htmlspecialchars($this->db->getConfig('domain', '0.0.0.0'));
        $httpPort = $this->db->getConfig('http_port', 9501);
        $httpsPort = $this->db->getConfig('https_port', 9502);
        
        $dbPoolMaxSize = $this->db->getConfig('db_pool_max_size', 10);
        $dbPoolMinSize = $this->db->getConfig('db_pool_min_size', 2);
        $dbPoolTimeout = $this->db->getConfig('db_pool_timeout', 5);
        $queryCacheEnabled = $this->db->getConfig('query_cache_enabled', true) ? 'checked' : '';
        $queryCacheTtl = $this->db->getConfig('query_cache_ttl', 300);
        $queryCacheMaxSize = $this->db->getConfig('query_cache_max_size', 1000);
        $logLevel = htmlspecialchars($this->db->getConfig('log_level', 'info'));
        $logMaxFileSize = $this->db->getConfig('log_max_file_size', 10 * 1024 * 1024) / (1024 * 1024);
        $logMaxFiles = $this->db->getConfig('log_max_files', 10);
        $logToDatabase = $this->db->getConfig('log_to_database', true) ? 'checked' : '';
        $logToFile = $this->db->getConfig('log_to_file', true) ? 'checked' : '';
        
        $debugSel = $logLevel === 'debug' ? 'selected' : '';
        $infoSel = $logLevel === 'info' ? 'selected' : '';
        $warningSel = $logLevel === 'warning' ? 'selected' : '';
        $errorSel = $logLevel === 'error' ? 'selected' : '';
        $criticalSel = $logLevel === 'critical' ? 'selected' : '';
        
        $content = '<div class="container-fluid">
            ' . $this->mobileHeader() . '
            <div class="row">
                ' . $this->sidebar('settings') . '
                
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
                        <div>
                            <h1 class="h2 mb-1">
                                <i class="fas fa-cog me-2 text-secondary"></i>系统设置
                            </h1>
                            <p class="text-muted mb-0">配置系统各项参数</p>
                        </div>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-globe me-2"></i>基本设置
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="/admin/settings/save">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-tag me-2"></i>站点名称
                                            </label>
                                            <input type="text" name="site_name" class="form-control" 
                                                   value="' . $siteName . '">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-server me-2"></i>监听地址
                                            </label>
                                            <input type="text" name="domain" class="form-control" 
                                                   value="' . $domain . '">
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-door-open me-2"></i>HTTP端口
                                                </label>
                                                <input type="number" name="http_port" class="form-control" 
                                                       value="' . $httpPort . '">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-lock me-2"></i>HTTPS端口
                                                </label>
                                                <input type="number" name="https_port" class="form-control" 
                                                       value="' . $httpsPort . '">
                                            </div>
                                        </div>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>保存设置
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-warning">
                                    <h5 class="mb-0">
                                        <i class="fas fa-key me-2"></i>修改密码
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="/admin/settings/password">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-lock me-2"></i>原密码
                                            </label>
                                            <input type="password" name="old_password" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-key me-2"></i>新密码
                                            </label>
                                            <input type="password" name="new_password" class="form-control" required>
                                        </div>
                                        <div class="mb-4">
                                            <label class="form-label">
                                                <i class="fas fa-check-double me-2"></i>确认新密码
                                            </label>
                                            <input type="password" name="confirm_password" class="form-control" required>
                                        </div>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-warning">
                                                <i class="fas fa-key me-2"></i>修改密码
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-database me-2"></i>数据库连接池
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="/admin/settings/save-advanced">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-users me-2"></i>最大连接数
                                                </label>
                                                <input type="number" name="db_pool_max_size" class="form-control" 
                                                       value="' . $dbPoolMaxSize . '">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-user me-2"></i>最小连接数
                                                </label>
                                                <input type="number" name="db_pool_min_size" class="form-control" 
                                                       value="' . $dbPoolMinSize . '">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-clock me-2"></i>超时(秒)
                                                </label>
                                                <input type="number" name="db_pool_timeout" class="form-control" 
                                                       value="' . $dbPoolTimeout . '">
                                            </div>
                                        </div>
                                        <hr>
                                        
                                        <h6 class="mb-3">
                                            <i class="fas fa-bolt me-2 text-warning"></i>查询缓存
                                        </h6>
                                        <div class="form-check form-switch mb-3">
                                            <input type="checkbox" name="query_cache_enabled" class="form-check-input" 
                                                   id="queryCacheEnabled" ' . $queryCacheEnabled . '>
                                            <label class="form-check-label" for="queryCacheEnabled">
                                                启用查询缓存
                                            </label>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-hourglass-half me-2"></i>缓存TTL(秒)
                                                </label>
                                                <input type="number" name="query_cache_ttl" class="form-control" 
                                                       value="' . $queryCacheTtl . '">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-boxes me-2"></i>最大缓存数
                                                </label>
                                                <input type="number" name="query_cache_max_size" class="form-control" 
                                                       value="' . $queryCacheMaxSize . '">
                                            </div>
                                        </div>
                                        <hr>
                                        
                                        <h6 class="mb-3">
                                            <i class="fas fa-clipboard-list me-2 text-info"></i>日志系统
                                        </h6>
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-flag me-2"></i>日志级别
                                            </label>
                                            <select name="log_level" class="form-select">
                                                <option value="debug" ' . $debugSel . '>DEBUG</option>
                                                <option value="info" ' . $infoSel . '>INFO</option>
                                                <option value="warning" ' . $warningSel . '>WARNING</option>
                                                <option value="error" ' . $errorSel . '>ERROR</option>
                                                <option value="critical" ' . $criticalSel . '>CRITICAL</option>
                                            </select>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-file me-2"></i>单个文件大小(MB)
                                                </label>
                                                <input type="number" name="log_max_file_size_mb" class="form-control" 
                                                       value="' . $logMaxFileSize . '">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-folder me-2"></i>最大文件数
                                                </label>
                                                <input type="number" name="log_max_files" class="form-control" 
                                                       value="' . $logMaxFiles . '">
                                            </div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input type="checkbox" name="log_to_database" class="form-check-input" 
                                                   id="logToDatabase" ' . $logToDatabase . '>
                                            <label class="form-check-label" for="logToDatabase">
                                                <i class="fas fa-database me-2"></i>记录到数据库
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-4">
                                            <input type="checkbox" name="log_to_file" class="form-check-input" 
                                                   id="logToFile" ' . $logToFile . '>
                                            <label class="form-check-label" for="logToFile">
                                                <i class="fas fa-file-alt me-2"></i>记录到文件
                                            </label>
                                        </div>
                                        
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-save me-2"></i>保存高级设置
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>';
        
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
    
    private function saveAdvancedSettings($request, $response)
    {
        $post = $request->post ?? [];
        
        $this->db->setConfig('db_pool_max_size', intval($post['db_pool_max_size'] ?? 10));
        $this->db->setConfig('db_pool_min_size', intval($post['db_pool_min_size'] ?? 2));
        $this->db->setConfig('db_pool_timeout', intval($post['db_pool_timeout'] ?? 5));
        $this->db->setConfig('query_cache_enabled', isset($post['query_cache_enabled']) ? true : false);
        $this->db->setConfig('query_cache_ttl', intval($post['query_cache_ttl'] ?? 300));
        $this->db->setConfig('query_cache_max_size', intval($post['query_cache_max_size'] ?? 1000));
        $this->db->setConfig('log_level', $post['log_level'] ?? 'info');
        $this->db->setConfig('log_max_file_size', intval($post['log_max_file_size_mb'] ?? 10) * 1024 * 1024);
        $this->db->setConfig('log_max_files', intval($post['log_max_files'] ?? 10));
        $this->db->setConfig('log_to_database', isset($post['log_to_database']) ? true : false);
        $this->db->setConfig('log_to_file', isset($post['log_to_file']) ? true : false);
        
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
