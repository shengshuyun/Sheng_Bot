<?php
session_start();
require_once __DIR__ . '/数据库.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../函数库/数据库连接池.php';
require_once __DIR__ . '/../函数库/日志系统.php';

checkInstalled();
requireLogin();

$db = new SQLiteDatabase();
$pdo = $db->getConnection();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'general') {
        $db->setConfig('site_name', trim($_POST['site_name'] ?? ''));
        $db->setConfig('domain', trim($_POST['domain'] ?? ''));
        $db->setConfig('http_port', (int)($_POST['http_port'] ?? 9501));
        $db->setConfig('https_port', (int)($_POST['https_port'] ?? 9502));
        $message = '基本设置已保存';
        $messageType = 'success';
    } elseif ($action === 'password') {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($oldPassword, $admin['password_hash'])) {
            if (strlen($newPassword) >= 6 && $newPassword === $confirmPassword) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
                $updateStmt->execute([$newHash, $_SESSION['admin_id']]);
                $message = '密码修改成功';
                $messageType = 'success';
            } else {
                $message = '新密码长度至少6位，且两次输入一致';
                $messageType = 'danger';
            }
        } else {
            $message = '原密码错误';
            $messageType = 'danger';
        }
    } elseif ($action === 'database') {
        $db->setConfig('db_pool_max_size', (int)($_POST['db_pool_max_size'] ?? 10));
        $db->setConfig('db_pool_min_size', (int)($_POST['db_pool_min_size'] ?? 2));
        $db->setConfig('db_pool_timeout', (int)($_POST['db_pool_timeout'] ?? 5));
        $db->setConfig('query_cache_enabled', isset($_POST['query_cache_enabled']) ? true : false);
        $db->setConfig('query_cache_ttl', (int)($_POST['query_cache_ttl'] ?? 300));
        $db->setConfig('query_cache_max_size', (int)($_POST['query_cache_max_size'] ?? 1000));
        $message = '数据库设置已保存';
        $messageType = 'success';
    } elseif ($action === 'logging') {
        $db->setConfig('log_level', $_POST['log_level'] ?? 'info');
        $db->setConfig('log_max_file_size', (int)($_POST['log_max_file_size'] ?? 10) * 1024 * 1024);
        $db->setConfig('log_max_files', (int)($_POST['log_max_files'] ?? 10));
        $db->setConfig('log_to_database', isset($_POST['log_to_database']) ? true : false);
        $db->setConfig('log_to_file', isset($_POST['log_to_file']) ? true : false);
        $message = '日志设置已保存';
        $messageType = 'success';
    }
}

$siteName = $db->getConfig('site_name', 'Sheng_Bot');
$domain = $db->getConfig('domain', '0.0.0.0');
$httpPort = $db->getConfig('http_port', 9501);
$httpsPort = $db->getConfig('https_port', 9502);

$dbPoolMaxSize = $db->getConfig('db_pool_max_size', 10);
$dbPoolMinSize = $db->getConfig('db_pool_min_size', 2);
$dbPoolTimeout = $db->getConfig('db_pool_timeout', 5);
$queryCacheEnabled = $db->getConfig('query_cache_enabled', true);
$queryCacheTtl = $db->getConfig('query_cache_ttl', 300);
$queryCacheMaxSize = $db->getConfig('query_cache_max_size', 1000);

$logLevel = $db->getConfig('log_level', 'info');
$logMaxFileSize = $db->getConfig('log_max_file_size', 10 * 1024 * 1024) / (1024 * 1024);
$logMaxFiles = $db->getConfig('log_max_files', 10);
$logToDatabase = $db->getConfig('log_to_database', true);
$logToFile = $db->getConfig('log_to_file', true);

$logger = Logger::getInstance();
$logStats = $logger->getStats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - <?php echo htmlspecialchars($siteName); ?></title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <h5 class="text-white px-3 mb-3">🎯 <?php echo htmlspecialchars($siteName); ?></h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">📊 控制台</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bots_qq.php">🤖 官方QQ机器人</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bots_napcat.php">😺 NapCat机器人</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logs.php">📝 消息日志</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="system_logs.php">🔍 系统日志</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="settings.php">⚙️ 系统设置</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">🚪 退出登录</a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">系统设置</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">基本设置</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="action" value="general">
                                    <div class="mb-3">
                                        <label class="form-label">站点名称</label>
                                        <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($siteName); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">监听地址</label>
                                        <input type="text" name="domain" class="form-control" value="<?php echo htmlspecialchars($domain); ?>">
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">HTTP端口</label>
                                            <input type="number" name="http_port" class="form-control" value="<?php echo $httpPort; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">HTTPS端口</label>
                                            <input type="number" name="https_port" class="form-control" value="<?php echo $httpsPort; ?>">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">保存设置</button>
                                </form>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">修改密码</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="action" value="password">
                                    <div class="mb-3">
                                        <label class="form-label">原密码</label>
                                        <input type="password" name="old_password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">新密码</label>
                                        <input type="password" name="new_password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">确认新密码</label>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-success">修改密码</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">数据库设置</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="action" value="database">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">连接池最大大小</label>
                                            <input type="number" name="db_pool_max_size" class="form-control" value="<?php echo $dbPoolMaxSize; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">连接池最小大小</label>
                                            <input type="number" name="db_pool_min_size" class="form-control" value="<?php echo $dbPoolMinSize; ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">连接池超时（秒）</label>
                                        <input type="number" name="db_pool_timeout" class="form-control" value="<?php echo $dbPoolTimeout; ?>">
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" name="query_cache_enabled" class="form-check-input" id="query_cache_enabled" <?php echo $queryCacheEnabled ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="query_cache_enabled">启用查询缓存</label>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">缓存TTL（秒）</label>
                                            <input type="number" name="query_cache_ttl" class="form-control" value="<?php echo $queryCacheTtl; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">最大缓存条目</label>
                                            <input type="number" name="query_cache_max_size" class="form-control" value="<?php echo $queryCacheMaxSize; ?>">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">保存设置</button>
                                </form>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">日志设置</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="action" value="logging">
                                    <div class="mb-3">
                                        <label class="form-label">日志级别</label>
                                        <select name="log_level" class="form-select">
                                            <option value="debug" <?php echo $logLevel === 'debug' ? 'selected' : ''; ?>>DEBUG</option>
                                            <option value="info" <?php echo $logLevel === 'info' ? 'selected' : ''; ?>>INFO</option>
                                            <option value="warning" <?php echo $logLevel === 'warning' ? 'selected' : ''; ?>>WARNING</option>
                                            <option value="error" <?php echo $logLevel === 'error' ? 'selected' : ''; ?>>ERROR</option>
                                            <option value="critical" <?php echo $logLevel === 'critical' ? 'selected' : ''; ?>>CRITICAL</option>
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">单个日志文件最大（MB）</label>
                                            <input type="number" name="log_max_file_size" class="form-control" value="<?php echo $logMaxFileSize; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">保留日志文件数</label>
                                            <input type="number" name="log_max_files" class="form-control" value="<?php echo $logMaxFiles; ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" name="log_to_database" class="form-check-input" id="log_to_database" <?php echo $logToDatabase ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="log_to_database">记录到数据库</label>
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" name="log_to_file" class="form-check-input" id="log_to_file" <?php echo $logToFile ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="log_to_file">记录到文件</label>
                                    </div>
                                    <button type="submit" class="btn btn-primary">保存设置</button>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">系统信息</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>PHP版本：</strong><?php echo PHP_VERSION; ?></p>
                                <p><strong>Swoole版本：</strong><?php echo SWOOLE_VERSION; ?></p>
                                <p><strong>SQLite版本：</strong><?php echo $pdo->query('SELECT sqlite_version()')->fetchColumn(); ?></p>
                                <p><strong>服务器时间：</strong><?php echo date('Y-m-d H:i:s'); ?></p>
                                <p><strong>日志目录：</strong><?php echo htmlspecialchars($logStats['log_dir']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
