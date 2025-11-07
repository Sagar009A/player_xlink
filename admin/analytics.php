<?php
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Get date range
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;

// Platform stats
$platformStats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'approved'")->fetchColumn(),
    'total_views' => $pdo->query("SELECT SUM(total_views) FROM users")->fetchColumn(),
    'total_earnings' => $pdo->query("SELECT SUM(total_earnings) FROM users")->fetchColumn(),
    'total_links' => $pdo->query("SELECT COUNT(*) FROM links")->fetchColumn(),
];

// Daily stats
$stmt = $pdo->prepare("
    SELECT DATE(viewed_at) as date, COUNT(*) as views, COUNT(DISTINCT user_id) as active_users
    FROM views_log
    WHERE viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY) AND is_counted = 1
    GROUP BY DATE(viewed_at)
    ORDER BY date DESC
");
$stmt->execute([$days]);
$dailyStats = $stmt->fetchAll();

// Top countries
$stmt = $pdo->query("
    SELECT country_code, country_name, COUNT(*) as views
    FROM views_log
    WHERE is_counted = 1
    GROUP BY country_code
    ORDER BY views DESC
    LIMIT 10
");
$topCountries = $stmt->fetchAll();

// Device breakdown
$stmt = $pdo->query("
    SELECT device_type, COUNT(*) as count
    FROM views_log
    WHERE is_counted = 1
    GROUP BY device_type
");
$deviceStats = $stmt->fetchAll();

// Top earners
$stmt = $pdo->query("
    SELECT username, email, total_earnings, total_views
    FROM users
    WHERE status = 'approved'
    ORDER BY total_earnings DESC
    LIMIT 20
");
$topEarners = $stmt->fetchAll();

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Platform Analytics</h1>
                <div class="btn-group">
                    <a href="?days=7" class="btn btn-sm btn-<?= $days === 7 ? 'primary' : 'outline-primary' ?>">7 Days</a>
                    <a href="?days=30" class="btn btn-sm btn-<?= $days === 30 ? 'primary' : 'outline-primary' ?>">30 Days</a>
                    <a href="?days=90" class="btn btn-sm btn-<?= $days === 90 ? 'primary' : 'outline-primary' ?>">90 Days</a>
                </div>
            </div>

            <!-- Platform Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6>Total Users</h6>
                            <h3><?= number_format($platformStats['total_users']) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6>Total Views</h6>
                            <h3><?= number_format($platformStats['total_views']) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6>Total Earnings</h6>
                            <h3>$<?= number_format($platformStats['total_earnings'], 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6>Total Links</h6>
                            <h3><?= number_format($platformStats['total_links']) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">Daily Views & Active Users (Last <?= $days ?> Days)</div>
                        <div class="card-body">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">Device Breakdown</div>
                        <div class="card-body">
                            <canvas id="deviceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">Top 10 Countries</div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Country</th>
                                        <th>Views</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topCountries as $country): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($country['country_name']) ?></td>
                                        <td><?= number_format($country['views']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">Top 20 Earners</div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Views</th>
                                        <th>Earnings</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topEarners as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td><?= number_format($user['total_views']) ?></td>
                                        <td>$<?= number_format($user['total_earnings'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily Views Chart
const dailyData = <?= json_encode(array_reverse($dailyStats)) ?>;
new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: dailyData.map(d => d.date),
        datasets: [{
            label: 'Views',
            data: dailyData.map(d => d.views),
            borderColor: '#2ecc71',
            backgroundColor: 'rgba(46, 204, 113, 0.1)',
            tension: 0.3
        }, {
            label: 'Active Users',
            data: dailyData.map(d => d.active_users),
            borderColor: '#3498db',
            backgroundColor: 'rgba(52, 152, 219, 0.1)',
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: true }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Device Chart
const deviceData = <?= json_encode($deviceStats) ?>;
new Chart(document.getElementById('deviceChart'), {
    type: 'doughnut',
    data: {
        labels: deviceData.map(d => d.device_type),
        datasets: [{
            data: deviceData.map(d => d.count),
            backgroundColor: ['#3498db', '#e74c3c', '#2ecc71']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

<?php include 'footer.php'; ?>