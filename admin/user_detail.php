<?php
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = intval($_GET['id'] ?? 0);

if (!$userId) {
    header('Location: users.php');
    exit;
}

// Handle manual adjustments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'adjust_balance':
            $amount = floatval($_POST['amount'] ?? 0);
            $type = $_POST['type'] ?? 'add';
            
            if ($type === 'add') {
                $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            }
            $stmt->execute([$amount, $userId]);
            $_SESSION['success'] = "Balance adjusted by $" . number_format($amount, 2);
            break;
            
        case 'adjust_views':
            $views = intval($_POST['views'] ?? 0);
            $stmt = $pdo->prepare("UPDATE users SET total_views = ? WHERE id = ?");
            $stmt->execute([$views, $userId]);
            $_SESSION['success'] = "Total views updated to " . number_format($views);
            break;
            
        case 'update_limit':
            $limit = intval($_POST['daily_view_limit'] ?? 50);
            $stmt = $pdo->prepare("UPDATE users SET daily_view_limit = ? WHERE id = ?");
            $stmt->execute([$limit, $userId]);
            $_SESSION['success'] = "Daily view limit updated";
            break;
    }
    
    header("Location: user_detail.php?id=$userId");
    exit;
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

// Get user links
$stmt = $pdo->prepare("SELECT * FROM links WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$links = $stmt->fetchAll();

// Get recent views
$stmt = $pdo->prepare("
    SELECT v.*, l.title, l.short_code 
    FROM views_log v 
    JOIN links l ON v.link_id = l.id 
    WHERE v.user_id = ? 
    ORDER BY v.viewed_at DESC 
    LIMIT 20
");
$stmt->execute([$userId]);
$recentViews = $stmt->fetchAll();

// Get withdrawal history
$stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY requested_at DESC LIMIT 10");
$stmt->execute([$userId]);
$withdrawals = $stmt->fetchAll();

// Get analytics
$analytics = getTrafficAnalytics($userId, null, 30);
$countryStats = getCountryStats($userId);

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <a href="users.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    User Details: <?= htmlspecialchars($user['username']) ?>
                </h1>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- User Info Card -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">User Information</div>
                        <div class="card-body">
                            <table class="table">
                                <tr>
                                    <th>User ID:</th>
                                    <td><?= $user['id'] ?></td>
                                </tr>
                                <tr>
                                    <th>Username:</th>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                </tr>
                                <tr>
                                    <th>Telegram ID:</th>
                                    <td><?= htmlspecialchars($user['telegram_id']) ?></td>
                                </tr>
                                <tr>
                                    <th>Traffic Source:</th>
                                    <td><?= htmlspecialchars($user['traffic_source']) ?></td>
                                </tr>
                                <tr>
                                    <th>Traffic Category:</th>
                                    <td><?= htmlspecialchars($user['traffic_category']) ?></td>
                                </tr>
                                <tr>
                                    <th>Referral Code:</th>
                                    <td><code><?= $user['referral_code'] ?></code></td>
                                </tr>
                                <tr>
                                    <th>API Key:</th>
                                    <td><code><?= substr($user['api_key'], 0, 20) ?>...</code></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge bg-<?= $user['status'] === 'approved' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                            <?= ucfirst($user['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Registered:</th>
                                    <td><?= date('M d, Y H:i', strtotime($user['created_at'])) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card mb-3 bg-primary text-white">
                        <div class="card-body">
                            <h6>Balance</h6>
                            <h3>$<?= number_format($user['balance'], 2) ?></h3>
                        </div>
                    </div>
                    <div class="card mb-3 bg-success text-white">
                        <div class="card-body">
                            <h6>Total Views</h6>
                            <h3><?= number_format($user['total_views']) ?></h3>
                        </div>
                    </div>
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6>Total Earnings</h6>
                            <h3>$<?= number_format($user['total_earnings'], 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manual Adjustments -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">Adjust Balance</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="adjust_balance">
                                <div class="mb-3">
                                    <label class="form-label">Amount ($)</label>
                                    <input type="number" step="0.01" name="amount" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <select name="type" class="form-select">
                                        <option value="add">Add (+)</option>
                                        <option value="subtract">Subtract (-)</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Adjust</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">Update Total Views</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="adjust_views">
                                <div class="mb-3">
                                    <label class="form-label">Total Views</label>
                                    <input type="number" name="views" class="form-control" value="<?= $user['total_views'] ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Update</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">Daily View Limit</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_limit">
                                <div class="mb-3">
                                    <label class="form-label">Limit per IP</label>
                                    <input type="number" name="daily_view_limit" class="form-control" value="<?= $user['daily_view_limit'] ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Update</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Links -->
            <div class="card mb-4">
                <div class="card-header">Recent Links (<?= count($links) ?>)</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Short Code</th>
                                    <th>Views</th>
                                    <th>Earnings</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($links as $link): ?>
                                <tr>
                                    <td><?= htmlspecialchars($link['title']) ?></td>
                                    <td><code><?= $link['short_code'] ?></code></td>
                                    <td><?= number_format($link['views']) ?></td>
                                    <td>$<?= number_format($link['earnings'], 2) ?></td>
                                    <td><?= date('M d, Y', strtotime($link['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Country Stats -->
            <div class="card mb-4">
                <div class="card-header">Top Countries</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Country</th>
                                    <th>Views</th>
                                    <th>Unique Visitors</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($countryStats, 0, 10) as $stat): ?>
                                <tr>
                                    <td><?= htmlspecialchars($stat['country_name']) ?> (<?= $stat['country_code'] ?>)</td>
                                    <td><?= number_format($stat['views']) ?></td>
                                    <td><?= number_format($stat['unique_visitors']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Withdrawal History -->
            <div class="card">
                <div class="card-header">Withdrawal History</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Requested</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdrawals as $w): ?>
                                <tr>
                                    <td>$<?= number_format($w['amount_usd'], 2) ?></td>
                                    <td><?= htmlspecialchars($w['payment_method']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $w['status'] === 'paid' ? 'success' : ($w['status'] === 'processing' ? 'warning' : 'info') ?>">
                                            <?= ucfirst($w['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($w['requested_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>