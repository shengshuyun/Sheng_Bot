<?php
require_once __DIR__ . '/数据库.php';

$db = new SQLiteDatabase();
$error = '';
$success = false;

if ($db->isInstalled()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $siteName = trim($_POST['site_name'] ?? 'Sheng_Bot');
    $domain = trim($_POST['domain'] ?? '0.0.0.0');
    $httpPort = (int)($_POST['http_port'] ?? 9501);
    $httpsPort = (int)($_POST['https_port'] ?? 9502);

    if (empty($username)) {
        $error = '请输入管理员用户名';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少6位';
    } elseif ($password !== $confirmPassword) {
        $error = '两次输入的密码不一致';
    } else {
        try {
            $pdo = $db->getConnection();
            
            // 创建管理员
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$username, $passwordHash]);

            // 保存基本配置
            $db->setConfig('site_name', $siteName);
            $db->setConfig('domain', $domain);
            $db->setConfig('http_port', $httpPort);
            $db->setConfig('https_port', $httpsPort);
            $db->setConfig('installed', true);

            $success = true;
        } catch (Exception $e) {
            $error = '安装失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sheng_Bot 安装向导</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .install-card {
            margin-top: 100px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card install-card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">🎉 Sheng_Bot 安装向导</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <h5>✅ 安装成功！</h5>
                                <p>恭喜！Sheng_Bot 管理后台已成功安装。</p>
                                <a href="index.php" class="btn btn-primary">进入管理后台</a>
                            </div>
                        <?php else: ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            
                            <form method="post">
                                <h5 class="mb-3">管理员账号</h5>
                                <div class="mb-3">
                                    <label class="form-label">用户名</label>
                                    <input type="text" name="username" class="form-control" required placeholder="请输入管理员用户名">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">密码</label>
                                    <input type="password" name="password" class="form-control" required placeholder="至少6位">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">确认密码</label>
                                    <input type="password" name="confirm_password" class="form-control" required placeholder="再次输入密码">
                                </div>

                                <h5 class="mb-3">基本设置</h5>
                                <div class="mb-3">
                                    <label class="form-label">站点名称</label>
                                    <input type="text" name="site_name" class="form-control" value="Sheng_Bot">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">监听地址</label>
                                        <input type="text" name="domain" class="form-control" value="0.0.0.0">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">HTTP端口</label>
                                        <input type="number" name="http_port" class="form-control" value="9501">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">HTTPS端口</label>
                                        <input type="number" name="https_port" class="form-control" value="9502">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">开始安装</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
