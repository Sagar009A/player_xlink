<?php
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Get dashboard stats
$stats = [];

// Total Users
$stmt = $pdo->query("SELECT COUNT(*) as total, 
                     SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                     SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                     SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked
                     FROM users");
$stats['users'] = $stmt->fetch();

// Total Views
$stmt = $pdo->query("SELECT SUM(total_views) as total_views FROM users");
$stats['total_views'] = $stmt->fetchColumn();

// Total Earnings
$stmt = $pdo->query("SELECT SUM(total_earnings) as total_earnings FROM users");
$stats['total_earnings'] = $stmt->fetchColumn();

// Pending Withdrawals
$stmt = $pdo->query("SELECT COUNT(*) as count, SUM(amount_usd) as amount 
                     FROM withdrawals WHERE status = 'processing'");
$stats['pending_withdrawals'] = $stmt->fetch();

// Today's Stats
$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as active_users,
                     COUNT(*) as views
                     FROM views_log 
                     WHERE DATE(viewed_at) = CURDATE() AND is_counted = 1");
$stats['today'] = $stmt->fetch();

// Recent Activity (Last 30 days)
$stmt = $pdo->query("
    SELECT DATE(viewed_at) as date, COUNT(*) as views
    FROM views_log
    WHERE viewed_at > DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_counted = 1
    GROUP BY DATE(viewed_at)
    ORDER BY date DESC
");
$stats['activity'] = $stmt->fetchAll();

// Top Earners
$stmt = $pdo->query("
    SELECT username, email, total_views, total_earnings, balance
    FROM users
    WHERE status = 'approved'
    ORDER BY total_earnings DESC
    LIMIT 10
");
$stats['top_earners'] = $stmt->fetchAll();

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-sm btn-outline-secondary" onclick="updateAllStats()">
                        <i class="fas fa-sync"></i> Update All Stats
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['users']['total']) ?></div>
                                    <small class="text-success">Approved: <?= $stats['users']['approved'] ?></small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Views</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_views']) ?></div>
                                    <small class="text-muted">Today: <?= number_format($stats['today']['views']) ?></small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-eye fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Earnings</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?= number_format($stats['total_earnings'], 2) ?></div>
                                    <small class="text-muted">Platform-wide</small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Withdrawals</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['pending_withdrawals']['count'] ?></div>
                                    <small class="text-danger">$<?= number_format($stats['pending_withdrawals']['amount'], 2) ?></small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-coins fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Views Chart -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Views Trend (Last 30 Days)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="viewsChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <a href="users.php?status=pending" class="btn btn-warning btn-block mb-2">
                                <i class="fas fa-user-clock"></i> Pending Users (<?= $stats['users']['pending'] ?>)
                            </a>
                            <a href="withdrawals.php?status=processing" class="btn btn-info btn-block mb-2">
                                <i class="fas fa-money-check"></i> Process Withdrawals
                            </a>
                            <a href="settings.php" class="btn btn-secondary btn-block mb-2">
                                <i class="fas fa-cog"></i> System Settings
                            </a>
                            <button onclick="updateCurrencyRates()" class="btn btn-success btn-block">
                                <i class="fas fa-sync"></i> Update Currency Rates
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Earners Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top 10 Earners</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Total Views</th>
                                    <th>Total Earnings</th>
                                    <th>Balance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['top_earners'] as $index => $user): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= number_format($user['total_views']) ?></td>
                                    <td>$<?= number_format($user['total_earnings'], 2) ?></td>
                                    <td>$<?= number_format($user['balance'], 2) ?></td>
                                    <td>
                                        <a href="user_detail.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Views Chart
const ctx = document.getElementById('viewsChart').getContext('2d');
const viewsData = <?= json_encode(array_reverse($stats['activity'])) ?>;

new Chart(ctx, {
    type: 'line',
    data: {
        labels: viewsData.map(d => d.date),
        datasets: [{
            label: 'Views',
            data: viewsData.map(d => d.views),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

function updateAllStats() {
    if (confirm('This will recalculate all user stats. Continue?')) {
        fetch('ajax/update_stats.php', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                location.reload();
            });
    }
}

function updateCurrencyRates() {
    fetch('ajax/update_currency.php', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
        });
}
</script>

<?php include 'footer.php'; ?>