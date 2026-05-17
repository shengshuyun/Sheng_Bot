<?php
session_start();
require_once __DIR__ . '/数据库.php';
require_once __DIR__ . '/auth.php';

checkInstalled();
requireLogin();

$db = new SQLiteDatabase();
$pdo = $db->getConnection();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $appid = trim($_POST['appid'] ?? '');
        $secret = trim($_POST['secret'] ?? '');
        $sandbox = isset($_POST['sandbox']) ? 1 : 0;
        
        if ($appid && $secret) {
            $stmt = $pdo->prepare("INSERT INTO qq_bots (appid, secret, sandbox) VALUES (?, ?, ?)");
            if ($stmt->execute([$appid, $secret, $sandbox])) {
                $message = '机器人添加成功！';
                $messageType = 'success';
            } else {
                $message = '添加失败';
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM qq_bots WHERE id = ?");
            $stmt->execute([$id]);
            $message = '机器人已删除';
            $messageType = 'success';
        }
    }
}

$bots = $pdo->query("SELECT * FROM qq_bots ORDER BY id DESC")->fetchAll();
$siteName = $db->getConfig('site_name', 'Sheng_Bot');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>官方QQ机器人 - <?php echo htmlspecialchars($siteName); ?></title>
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
                            <a class="nav-link active" href="bots_qq.php">🤖 官方QQ机器人</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bots_napcat.php">😺 NapCat机器人</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logs.php">📝 消息日志</a>
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
                    <h1 class="h2">官方QQ机器人</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">添加机器人</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="add">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">AppID</label>
                                    <input type="text" name="appid" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Secret</label>
                                    <input type="text" name="secret" class="form-control" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="form-check">
                                        <input type="checkbox" name="sandbox" class="form-check-input" id="sandbox" checked>
                                        <label class="form-check-label" for="sandbox">沙箱模式</label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">添加</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">机器人列表</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>AppID</th>
                                        <th>Secret</th>
                                        <th>沙箱模式</th>
                                        <th>添加时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bots as $bot): ?>
                                        <tr>
                                            <td><?php echo $bot['id']; ?></td>
                                            <td><?php echo htmlspecialchars($bot['appid']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($bot['secret'], 0, 10)); ?>...</td>
                                            <td><?php echo $bot['sandbox'] ? '<span class="badge bg-success">是</span>' : '<span class="badge bg-secondary">否</span>'; ?></td>
                                            <td><?php echo $bot['created_at']; ?></td>
                                            <td>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $bot['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('确定删除吗？')">删除</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($bots)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">暂无机器人</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
