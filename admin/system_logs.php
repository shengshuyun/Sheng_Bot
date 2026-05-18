<?php
session_start();
require_once __DIR__ . '/数据库.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../函数库/日志系统.php';

checkInstalled();
requireLogin();

$db = new SQLiteDatabase();
$logger = Logger::getInstance();
$siteName = $db->getConfig('site_name', 'Sheng_Bot');

$levelFilter = $_GET['level'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$pdo = $db->getConnection();

if ($levelFilter) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM system_logs WHERE level = ?");
    $countStmt->execute([$levelFilter]);
    $total = $countStmt->fetchColumn();
    
    $logsStmt = $pdo->prepare("SELECT * FROM system_logs WHERE level = ? ORDER BY id DESC LIMIT ? OFFSET ?");
    $logsStmt->execute([$levelFilter, $perPage, $offset]);
} else {
    $countStmt = $pdo->query("SELECT COUNT(*) FROM system_logs");
    $total = $countStmt->fetchColumn();
    
    $logsStmt = $pdo->prepare("SELECT * FROM system_logs ORDER BY id DESC LIMIT ? OFFSET ?");
    $logsStmt->execute([$perPage, $offset]);
}

$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
$totalPages = ceil($total / $perPage);

$levelColors = [
    'debug' => 'bg-secondary',
    'info' => 'bg-primary',
    'warning' => 'bg-warning text-dark',
    'error' => 'bg-danger',
    'critical' => 'bg-dark text-white'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统日志 - <?php echo htmlspecialchars($siteName); ?></title>
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
                            <a class="nav-link active" href="system_logs.php">🔍 系统日志</a>
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
                    <h1 class="h2">系统日志</h1>
                    <span class="text-muted">共 <?php echo $total; ?> 条日志</span>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">日志级别</label>
                                <select name="level" class="form-select">
                                    <option value="">全部级别</option>
                                    <option value="debug" <?php echo $levelFilter === 'debug' ? 'selected' : ''; ?>>DEBUG</option>
                                    <option value="info" <?php echo $levelFilter === 'info' ? 'selected' : ''; ?>>INFO</option>
                                    <option value="warning" <?php echo $levelFilter === 'warning' ? 'selected' : ''; ?>>WARNING</option>
                                    <option value="error" <?php echo $levelFilter === 'error' ? 'selected' : ''; ?>>ERROR</option>
                                    <option value="critical" <?php echo $levelFilter === 'critical' ? 'selected' : ''; ?>>CRITICAL</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">筛选</button>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <a href="system_logs.php" class="btn btn-secondary w-100">重置</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">级别</th>
                                        <th style="width: 180px;">时间</th>
                                        <th>消息</th>
                                        <th style="width: 100px;">详情</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <span class="badge <?php echo $levelColors[$log['level']] ?? 'bg-secondary'; ?>">
                                                    <?php echo strtoupper($log['level']); ?>
                                                </span>
                                            </td>
                                            <td class="text-nowrap"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                            <td style="max-width: 500px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?php echo htmlspecialchars($log['message']); ?>
                                            </td>
                                            <td>
                                                <?php if ($log['context']): ?>
                                                    <button class="btn btn-sm btn-info" onclick="toggleContext(<?php echo $log['id']; ?>)">查看</button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if ($log['context']): ?>
                                            <tr id="context-<?php echo $log['id']; ?>" style="display: none;">
                                                <td colspan="4" class="bg-light">
                                                    <pre class="mb-0 p-3" style="font-size: 12px;"><?php echo htmlspecialchars($log['context']); ?></pre>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">暂无日志</td>
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
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $levelFilter ? '&level=' . urlencode($levelFilter) : ''; ?>">上一页</a>
                            </li>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $levelFilter ? '&level=' . urlencode($levelFilter) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $levelFilter ? '&level=' . urlencode($levelFilter) : ''; ?>">下一页</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        function toggleContext(id) {
            const row = document.getElementById('context-' + id);
            if (row.style.display === 'none') {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        }
    </script>
</body>
</html>
