<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/fraud_detection.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: ../login.php');
    exit;
}

// Get Today's Stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as today_views,
        COALESCE(SUM(earnings), 0) as today_earnings,
        COUNT(DISTINCT ip_address) as today_unique_visitors
    FROM views_log 
    WHERE user_id = ? AND DATE(viewed_at) = CURDATE() AND is_counted = 1
");
$stmt->execute([$user['id']]);
$todayStats = $stmt->fetch();

// Get Yesterday's Stats for Comparison
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as yesterday_views,
        COALESCE(SUM(earnings), 0) as yesterday_earnings,
        COUNT(DISTINCT ip_address) as yesterday_unique_visitors
    FROM views_log 
    WHERE user_id = ? AND DATE(viewed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND is_counted = 1
");
$stmt->execute([$user['id']]);
$yesterdayStats = $stmt->fetch();

// Calculate percentage changes
$viewsChange = $yesterdayStats['yesterday_views'] > 0 
    ? (($todayStats['today_views'] - $yesterdayStats['yesterday_views']) / $yesterdayStats['yesterday_views']) * 100 
    : 0;
$earningsChange = $yesterdayStats['yesterday_earnings'] > 0 
    ? (($todayStats['today_earnings'] - $yesterdayStats['yesterday_earnings']) / $yesterdayStats['yesterday_earnings']) * 100 
    : 0;

// Get Weekly Trends (Last 7 days)
$stmt = $pdo->prepare("
    SELECT 
        DATE(viewed_at) as date,
        COUNT(*) as views,
        COALESCE(SUM(earnings), 0) as earnings,
        COUNT(DISTINCT ip_address) as unique_visitors
    FROM views_log
    WHERE user_id = ? AND viewed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND is_counted = 1
    GROUP BY DATE(viewed_at)
    ORDER BY date ASC
");
$stmt->execute([$user['id']]);
$weeklyTrends = $stmt->fetchAll();

// Get Monthly Trends (Last 30 days)
$stmt = $pdo->prepare("
    SELECT 
        DATE(viewed_at) as date,
        COUNT(*) as views,
        COALESCE(SUM(earnings), 0) as earnings
    FROM views_log
    WHERE user_id = ? AND viewed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND is_counted = 1
    GROUP BY DATE(viewed_at)
    ORDER BY date ASC
");
$stmt->execute([$user['id']]);
$monthlyTrends = $stmt->fetchAll();

// Top Performing Links (Last 7 days)
$stmt = $pdo->prepare("
    SELECT 
        l.id, l.title, l.short_code, l.views as total_views,
        COUNT(v.id) as recent_views,
        COALESCE(SUM(v.earnings), 0) as recent_earnings
    FROM links l
    LEFT JOIN views_log v ON l.id = v.link_id 
        AND v.viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND v.is_counted = 1
    WHERE l.user_id = ?
    GROUP BY l.id
    ORDER BY recent_views DESC, recent_earnings DESC
    LIMIT 10
");
$stmt->execute([$user['id']]);
$topLinks = $stmt->fetchAll();

// Geographic Distribution
$stmt = $pdo->prepare("
    SELECT 
        country_code, 
        country_name,
        COUNT(*) as views,
        COALESCE(SUM(earnings), 0) as earnings
    FROM views_log
    WHERE user_id = ? AND is_counted = 1 AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY country_code, country_name
    ORDER BY views DESC
    LIMIT 20
");
$stmt->execute([$user['id']]);
$geoData = $stmt->fetchAll();

// Traffic Sources Breakdown
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN referrer = '' OR referrer IS NULL THEN 'Direct'
            WHEN referrer LIKE '%google%' THEN 'Google'
            WHEN referrer LIKE '%facebook%' THEN 'Facebook'
            WHEN referrer LIKE '%twitter%' THEN 'Twitter'
            WHEN referrer LIKE '%instagram%' THEN 'Instagram'
            WHEN referrer LIKE '%youtube%' THEN 'YouTube'
            WHEN referrer LIKE '%telegram%' THEN 'Telegram'
            ELSE 'Other'
        END as source,
        COUNT(*) as views,
        COALESCE(SUM(earnings), 0) as earnings
    FROM views_log
    WHERE user_id = ? AND is_counted = 1 AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY source
    ORDER BY views DESC
");
$stmt->execute([$user['id']]);
$trafficSources = $stmt->fetchAll();

// Device Type Distribution
$stmt = $pdo->prepare("
    SELECT 
        device_type,
        COUNT(*) as views,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM views_log WHERE user_id = ? AND is_counted = 1 AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 2) as percentage
    FROM views_log
    WHERE user_id = ? AND is_counted = 1 AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY device_type
    ORDER BY views DESC
");
$stmt->execute([$user['id'], $user['id']]);
$deviceStats = $stmt->fetchAll();

// Browser Usage Stats
$stmt = $pdo->prepare("
    SELECT 
        browser,
        COUNT(*) as views,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM views_log WHERE user_id = ? AND is_counted = 1 AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 2) as percentage
    FROM views_log
    WHERE user_id = ? AND is_counted = 1 AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY browser
    ORDER BY views DESC
    LIMIT 10
");
$stmt->execute([$user['id'], $user['id']]);
$browserStats = $stmt->fetchAll();

// Peak Activity Hours
$stmt = $pdo->prepare("
    SELECT 
        HOUR(viewed_at) as hour,
        COUNT(*) as views,
        COALESCE(SUM(earnings), 0) as earnings
    FROM views_log
    WHERE user_id = ? AND is_counted = 1 AND viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY HOUR(viewed_at)
    ORDER BY hour ASC
");
$stmt->execute([$user['id']]);
$hourlyStats = $stmt->fetchAll();

// Fraud Detection Alerts
$fraudAlerts = detectFraudulentActivity($user['id']);

include 'header.php';
?>

<style>
.stat-widget {
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.trend-up { color: #28a745; }
.trend-down { color: #dc3545; }
.trend-neutral { color: #6c757d; }
.real-time-counter {
    font-size: 2.5rem;
    font-weight: bold;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}
.mini-chart {
    height: 50px;
}
.progress-ring {
    transform: rotate(-90deg);
}
.heatmap-cell {
    display: inline-block;
    width: 30px;
    height: 30px;
    margin: 2px;
    border-radius: 3px;
}
.fraud-alert {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 10px;
    margin-bottom: 10px;
}
</style>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-tachometer-alt"></i> Advanced Analytics Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-sm btn-primary" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Fraud Alerts -->
            <?php if (!empty($fraudAlerts)): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <h5><i class="fas fa-exclamation-triangle"></i> Security Alerts</h5>
                <?php foreach ($fraudAlerts as $alert): ?>
                <div class="fraud-alert">
                    <strong><?= $alert['type'] ?>:</strong> <?= $alert['message'] ?>
                    <small class="text-muted">(<?= $alert['detected_at'] ?>)</small>
                </div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Real-Time Earnings Counter -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card stat-widget bg-gradient-primary text-white">
                        <div class="card-body text-center">
                            <h3><i class="fas fa-chart-line"></i> Today's Real-Time Earnings</h3>
                            <div class="real-time-counter" id="realtimeEarnings">
                                $<?= number_format($todayStats['today_earnings'], 2) ?>
                            </div>
                            <p class="mb-0">Updates every 30 seconds</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today vs Yesterday Comparison -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stat-widget">
                        <div class="card-body">
                            <h6 class="text-muted">Views Today</h6>
                            <h2><?= number_format($todayStats['today_views']) ?></h2>
                            <span class="<?= $viewsChange >= 0 ? 'trend-up' : 'trend-down' ?>">
                                <i class="fas fa-<?= $viewsChange >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= abs(number_format($viewsChange, 1)) ?>% vs yesterday
                            </span>
                            <div class="mt-2">
                                <small class="text-muted">Yesterday: <?= number_format($yesterdayStats['yesterday_views']) ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card stat-widget">
                        <div class="card-body">
                            <h6 class="text-muted">Earnings Today</h6>
                            <h2>$<?= number_format($todayStats['today_earnings'], 2) ?></h2>
                            <span class="<?= $earningsChange >= 0 ? 'trend-up' : 'trend-down' ?>">
                                <i class="fas fa-<?= $earningsChange >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= abs(number_format($earningsChange, 1)) ?>% vs yesterday
                            </span>
                            <div class="mt-2">
                                <small class="text-muted">Yesterday: $<?= number_format($yesterdayStats['yesterday_earnings'], 2) ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card stat-widget">
                        <div class="card-body">
                            <h6 class="text-muted">Unique Visitors Today</h6>
                            <h2><?= number_format($todayStats['today_unique_visitors']) ?></h2>
                            <span class="trend-neutral">
                                <i class="fas fa-users"></i>
                                Yesterday: <?= number_format($yesterdayStats['yesterday_unique_visitors']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weekly & Monthly Trends -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-calendar-week"></i> Weekly Trends (Last 7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="weeklyChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Monthly Trends (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performing Links -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-trophy"></i> Top Performing Links (Last 7 Days)</h5>
                            <span class="badge bg-success">Live</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Title</th>
                                            <th>Recent Views</th>
                                            <th>Total Views</th>
                                            <th>Recent Earnings</th>
                                            <th>Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topLinks as $index => $link): ?>
                                        <tr>
                                            <td>
                                                <?php if ($index < 3): ?>
                                                    <span class="badge bg-<?= ['warning', 'secondary', 'bronze'][$index] ?>">
                                                        #<?= $index + 1 ?>
                                                    </span>
                                                <?php else: ?>
                                                    #<?= $index + 1 ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="link_stats.php?id=<?= $link['id'] ?>">
                                                    <?= htmlspecialchars(substr($link['title'], 0, 40)) ?>
                                                </a>
                                            </td>
                                            <td><?= number_format($link['recent_views']) ?></td>
                                            <td><?= number_format($link['total_views']) ?></td>
                                            <td>$<?= number_format($link['recent_earnings'], 2) ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <?php 
                                                    $maxViews = $topLinks[0]['recent_views'];
                                                    $percentage = $maxViews > 0 ? ($link['recent_views'] / $maxViews) * 100 : 0;
                                                    ?>
                                                    <div class="progress-bar bg-success" style="width: <?= $percentage ?>%">
                                                        <?= number_format($percentage, 1) ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Geographic Heatmap & Traffic Sources -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-globe"></i> Geographic Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div id="worldMap" style="height: 400px;"></div>
                            <div class="mt-3">
                                <h6>Top Countries:</h6>
                                <?php foreach (array_slice($geoData, 0, 5) as $geo): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?= $geo['country_name'] ?: 'Unknown' ?></span>
                                    <span class="badge bg-primary"><?= number_format($geo['views']) ?> views</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-share-alt"></i> Traffic Sources</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="trafficSourcesChart" height="300"></canvas>
                            <div class="mt-3">
                                <?php foreach ($trafficSources as $source): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><i class="fas fa-circle" style="color: <?= ['#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6f42c1'][array_rand([0,1,2,3,4,5])] ?>;"></i> <?= $source['source'] ?></span>
                                    <div>
                                        <span class="badge bg-secondary"><?= number_format($source['views']) ?> views</span>
                                        <span class="badge bg-success">$<?= number_format($source['earnings'], 2) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Device & Browser Stats -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-mobile-alt"></i> Device Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="deviceChart"></canvas>
                            <div class="mt-3">
                                <?php foreach ($deviceStats as $device): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><?= ucfirst($device['device_type']) ?></span>
                                        <span><?= $device['percentage'] ?>%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: <?= $device['percentage'] ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-browser"></i> Browser Usage</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="browserChart"></canvas>
                            <div class="mt-3">
                                <?php foreach ($browserStats as $browser): ?>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span><?= $browser['browser'] ?></span>
                                        <span class="badge bg-info"><?= $browser['percentage'] ?>%</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Peak Activity Hours -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock"></i> Peak Activity Hours (Last 7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="hourlyChart" height="80"></canvas>
                            <div class="mt-3 text-center">
                                <?php
                                $maxHour = array_reduce($hourlyStats, function($carry, $item) {
                                    return ($item['views'] > ($carry['views'] ?? 0)) ? $item : $carry;
                                }, []);
                                ?>
                                <p class="mb-0">
                                    <strong>Peak Hour:</strong> 
                                    <?= sprintf('%02d:00 - %02d:00', $maxHour['hour'] ?? 0, ($maxHour['hour'] ?? 0) + 1) ?>
                                    with <?= number_format($maxHour['views'] ?? 0) ?> views
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>
// Real-time earnings update
function updateRealtimeEarnings() {
    fetch('../api/realtime_stats.php?user_id=<?= $user['id'] ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('realtimeEarnings').textContent = '$' + parseFloat(data.today_earnings).toFixed(2);
            }
        })
        .catch(error => console.error('Error:', error));
}

// Update every 30 seconds
setInterval(updateRealtimeEarnings, 30000);

// Weekly Trends Chart
new Chart(document.getElementById('weeklyChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($weeklyTrends, 'date')) ?>,
        datasets: [{
            label: 'Views',
            data: <?= json_encode(array_column($weeklyTrends, 'views')) ?>,
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            yAxisID: 'y'
        }, {
            label: 'Earnings ($)',
            data: <?= json_encode(array_column($weeklyTrends, 'earnings')) ?>,
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
            y: { type: 'linear', display: true, position: 'left' },
            y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false } }
        }
    }
});

// Monthly Trends Chart
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($monthlyTrends, 'date')) ?>,
        datasets: [{
            label: 'Views',
            data: <?= json_encode(array_column($monthlyTrends, 'views')) ?>,
            backgroundColor: 'rgba(0, 123, 255, 0.7)'
        }]
    },
    options: { responsive: true }
});

// Traffic Sources Pie Chart
new Chart(document.getElementById('trafficSourcesChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($trafficSources, 'source')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($trafficSources, 'views')) ?>,
            backgroundColor: ['#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6f42c1', '#fd7e14']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// Device Distribution Chart
new Chart(document.getElementById('deviceChart'), {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_column($deviceStats, 'device_type')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($deviceStats, 'views')) ?>,
            backgroundColor: ['#007bff', '#28a745', '#ffc107']
        }]
    },
    options: { responsive: true }
});

// Browser Usage Chart
new Chart(document.getElementById('browserChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($browserStats, 'browser')) ?>,
        datasets: [{
            label: 'Views',
            data: <?= json_encode(array_column($browserStats, 'views')) ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.7)'
        }]
    },
    options: { responsive: true, indexAxis: 'y' }
});

// Hourly Activity Chart
new Chart(document.getElementById('hourlyChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(function($h) { return sprintf('%02d:00', $h['hour']); }, $hourlyStats)) ?>,
        datasets: [{
            label: 'Views',
            data: <?= json_encode(array_column($hourlyStats, 'views')) ?>,
            borderColor: '#ff6384',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            fill: true,
            tension: 0.4
        }]
    },
    options: { responsive: true }
});

function refreshDashboard() {
    location.reload();
}
</script>

<?php include 'footer.php'; ?>
