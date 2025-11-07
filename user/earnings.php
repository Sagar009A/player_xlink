<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production mein 0 rakho

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // Get date filter
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
    $days = max(1, min(365, $days)); // Limit between 1-365 days

    // Get earnings by link (with safety check)
    $stmt = $pdo->prepare("
        SELECT 
            l.id,
            l.short_code,
            l.title,
            l.views,
            l.earnings,
            COALESCE(COUNT(v.id), 0) as recent_views,
            COALESCE(SUM(v.earnings), 0) as recent_earnings
        FROM links l
        LEFT JOIN views_log v ON l.id = v.link_id 
            AND v.is_counted = 1 
            AND v.viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        WHERE l.user_id = ?
        GROUP BY l.id
        ORDER BY recent_earnings DESC, l.views DESC
        LIMIT 20
    ");
    $stmt->execute([$days, $userId]);
    $linkEarnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get daily earnings
    $stmt = $pdo->prepare("
        SELECT 
            DATE(viewed_at) as date, 
            COUNT(*) as views, 
            COALESCE(SUM(earnings), 0) as daily_earnings
        FROM views_log
        WHERE user_id = ? 
            AND is_counted = 1 
            AND viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(viewed_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$userId, $days]);
    $dailyEarnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get country earnings
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(country_name, 'Unknown') as country_name,
            COALESCE(country_code, 'XX') as country_code,
            COUNT(*) as views, 
            COALESCE(SUM(earnings), 0) as country_earnings
        FROM views_log
        WHERE user_id = ? 
            AND is_counted = 1 
            AND viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY country_code, country_name
        ORDER BY country_earnings DESC
        LIMIT 10
    ");
    $stmt->execute([$userId, $days]);
    $countryEarnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate average CPM
    $avgCPM = $user['total_views'] > 0 
        ? ($user['total_earnings'] / $user['total_views']) * 1000 
        : 0;

} catch (Exception $e) {
    // Log error
    error_log("Earnings page error: " . $e->getMessage());
    $error = "Unable to load earnings data. Please try again later.";
    
    // Set default values
    $linkEarnings = [];
    $dailyEarnings = [];
    $countryEarnings = [];
    $avgCPM = 0;
}

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-chart-line"></i> Earnings Analytics</h1>
                <div class="btn-group">
                    <a href="?days=7" class="btn btn-sm btn-<?= $days === 7 ? 'primary' : 'outline-primary' ?>">7 Days</a>
                    <a href="?days=30" class="btn btn-sm btn-<?= $days === 30 ? 'primary' : 'outline-primary' ?>">30 Days</a>
                    <a href="?days=90" class="btn btn-sm btn-<?= $days === 90 ? 'primary' : 'outline-primary' ?>">90 Days</a>
                </div>
            </div>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6>Current Balance</h6>
                            <h3>$<?= number_format($user['balance'], 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6>Total Earnings</h6>
                            <h3>$<?= number_format($user['total_earnings'], 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6>Total Views</h6>
                            <h3><?= number_format($user['total_views']) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6>Avg CPM</h6>
                            <h3>$<?= number_format($avgCPM, 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0">Daily Earnings (Last <?= $days ?> Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="earningsChart" height="80"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0">Top Countries</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="countryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Top Earning Links</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($linkEarnings)): ?>
                            <p class="text-muted text-center py-4">No data available for this period</p>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Link Title</th>
                                            <th>Short Code</th>
                                            <th>Total Views</th>
                                            <th>Period Views</th>
                                            <th>Total Earnings</th>
                                            <th>Period Earnings</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($linkEarnings as $link): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(substr($link['title'], 0, 40)) ?><?= strlen($link['title']) > 40 ? '...' : '' ?></td>
                                            <td><code><?= htmlspecialchars($link['short_code']) ?></code></td>
                                            <td><?= number_format($link['views']) ?></td>
                                            <td><?= number_format($link['recent_views']) ?></td>
                                            <td>$<?= number_format($link['earnings'], 2) ?></td>
                                            <td><strong class="text-success">$<?= number_format($link['recent_earnings'], 2) ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Country Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($countryEarnings)): ?>
                            <p class="text-muted text-center py-4">No country data available</p>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Country</th>
                                            <th>Views</th>
                                            <th>Earnings</th>
                                            <th>CPM</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($countryEarnings as $country): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($country['country_name']) ?></strong>
                                                <small class="text-muted">(<?= htmlspecialchars($country['country_code']) ?>)</small>
                                            </td>
                                            <td><?= number_format($country['views']) ?></td>
                                            <td><strong>$<?= number_format($country['country_earnings'], 2) ?></strong></td>
                                            <td>
                                                <?php
                                                $cpm = $country['views'] > 0 
                                                    ? ($country['country_earnings'] / $country['views']) * 1000 
                                                    : 0;
                                                ?>
                                                $<?= number_format($cpm, 2) ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Safely handle chart data
const dailyData = <?= json_encode($dailyEarnings) ?>;
const countryData = <?= json_encode($countryEarnings) ?>;

// Daily Earnings Chart
if (dailyData && dailyData.length > 0) {
    new Chart(document.getElementById('earningsChart'), {
        type: 'line',
        data: {
            labels: dailyData.map(d => d.date),
            datasets: [{
                label: 'Earnings ($)',
                data: dailyData.map(d => parseFloat(d.daily_earnings)),
                borderColor: '#2ecc71',
                backgroundColor: 'rgba(46, 204, 113, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toFixed(2);
                        }
                    }
                }
            }
        }
    });
} else {
    document.getElementById('earningsChart').parentElement.innerHTML = '<p class="text-center text-muted py-4">No earnings data for chart</p>';
}

// Country Chart
if (countryData && countryData.length > 0) {
    new Chart(document.getElementById('countryChart'), {
        type: 'doughnut',
        data: {
            labels: countryData.map(d => d.country_code),
            datasets: [{
                data: countryData.map(d => parseFloat(d.country_earnings)),
                backgroundColor: ['#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#34495e', '#e67e22', '#95a5a6', '#16a085']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        font: { size: 10 }
                    }
                }
            }
        }
    });
} else {
    document.getElementById('countryChart').parentElement.innerHTML = '<p class="text-center text-muted py-4">No country data for chart</p>';
}
</script>

<?php include 'footer.php'; ?>