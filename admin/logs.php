<?php
session_start();
require_once __DIR__ . '/数据库.php';
require_once __DIR__ . '/auth.php';

checkInstalled();
requireLogin();

$db = new SQLiteDatabase();
$pdo = $db->getConnection();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if (isset($_GET['bot_type']) && $_GET['bot_type']) {
    $where[] = "bot_type = ?";
    $params[] = $_GET['bot_type'];
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM message_logs $whereSql");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $pdo->prepare("SELECT * FROM message_logs $whereSql ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$logs = $stmt->fetchAll();

$siteName = $db->getConfig('site_name', 'Sheng_Bot');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>消息日志 - <?php echo htmlspecialchars($siteName); ?></title>
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
                            <a class="nav-link active" href="logs.php">📝 消息日志</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">⚙️ 系统设置</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">🚪 退出登录</a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">消息日志</h1>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">机器人类型</label>
                                <select name="bot_type" class="form-select">
                                    <option value="">全部</option>
                                    <option value="qq" <?php echo (isset($_GET['bot_type']) && $_GET['bot_type'] === 'qq') ? 'selected' : ''; ?>>官方QQ</option>
                                    <option value="napcat" <?php echo (isset($_GET['bot_type']) && $_GET['bot_type'] === 'napcat') ? 'selected' : ''; ?>>NapCat</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">筛选</button>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <a href="logs.php" class="btn btn-secondary w-100">重置</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">日志列表 (共 <?php echo $total; ?> 条)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>类型</th>
                                        <th>Bot ID</th>
                                        <th>用户ID</th>
                                        <th>群ID</th>
                                        <th>消息内容</th>
                                        <th>时间</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo $log['id']; ?></td>
                                            <td>
                                                <?php echo $log['bot_type'] === 'qq' 
                                                    ? '<span class="badge bg-primary">官方QQ</span>' 
                                                    : '<span class="badge bg-success">NapCat</span>'; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['bot_id']); ?></td>
                                            <td><?php echo $log['user_id'] ? htmlspecialchars($log['user_id']) : '-'; ?></td>
                                            <td><?php echo $log['group_id'] ? htmlspecialchars($log['group_id']) : '-'; ?></td>
                                            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($log['content'] ?? ''); ?></td>
                                            <td><?php echo $log['created_at']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">暂无日志</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">上一页</a>
                            </li>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">下一页</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
