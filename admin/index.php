<?php
session_start();
require_once __DIR__ . '/数据库.php';
require_once __DIR__ . '/auth.php';

checkInstalled();
requireLogin();

$db = new SQLiteDatabase();
$pdo = $db->getConnection();

// 获取统计数据
$qqBotsCount = $pdo->query("SELECT COUNT(*) FROM qq_bots")->fetchColumn();
$napcatBotsCount = $pdo->query("SELECT COUNT(*) FROM napcat_bots")->fetchColumn();
$messageLogsCount = $pdo->query("SELECT COUNT(*) FROM message_logs")->fetchColumn();
$siteName = $db->getConfig('site_name', 'Sheng_Bot');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - 管理后台</title>
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
        .stat-card {
            transition: transform .2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
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
                            <a class="nav-link active" href="index.php">
                                📊 控制台
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bots_qq.php">
                                🤖 官方QQ机器人
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bots_napcat.php">
                                😺 NapCat机器人
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logs.php">
                                📝 消息日志
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                ⚙️ 系统设置
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                🚪 退出登录
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">控制台</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="text-muted">欢迎，<?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">🤖 官方QQ机器人</h5>
                                <h2 class="display-4"><?php echo $qqBotsCount; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">😺 NapCat机器人</h5>
                                <h2 class="display-4"><?php echo $napcatBotsCount; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">📝 消息日志</h5>
                                <h2 class="display-4"><?php echo $messageLogsCount; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">快速操作</h5>
                            </div>
                            <div class="card-body">
                                <a href="bots_qq.php" class="btn btn-primary mb-2 w-100">添加官方QQ机器人</a>
                                <a href="bots_napcat.php" class="btn btn-success mb-2 w-100">添加NapCat机器人</a>
                                <a href="settings.php" class="btn btn-secondary w-100">系统设置</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">系统信息</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>PHP版本：</strong><?php echo PHP_VERSION; ?></p>
                                <p><strong>Swoole版本：</strong><?php echo SWOOLE_VERSION; ?></p>
                                <p><strong>服务器时间：</strong><?php echo date('Y-m-d H:i:s'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
