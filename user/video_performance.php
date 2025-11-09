<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: ../login.php');
    exit;
}

$linkId = intval($_GET['id'] ?? 0);

// Get link details
$stmt = $pdo->prepare("SELECT * FROM links WHERE id = ? AND user_id = ?");
$stmt->execute([$linkId, $user['id']]);
$link = $stmt->fetch();

if (!$link) {
    header('Location: links.php');
    exit;
}

// Watch Time Analytics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_views,
        AVG(watch_duration) as avg_watch_time,
        MAX(watch_duration) as max_watch_time,
        MIN(watch_duration) as min_watch_time,
        COUNT(CASE WHEN watch_duration >= 30 THEN 1 END) as views_30s_plus,
        COUNT(CASE WHEN watch_duration >= 60 THEN 1 END) as views_1min_plus,
        COUNT(CASE WHEN watch_duration >= 120 THEN 1 END) as views_2min_plus
    FROM views_log
    WHERE link_id = ? AND is_counted = 1
");
$stmt->execute([$linkId]);
$watchStats = $stmt->fetch();

// Calculate completion rate (assuming average video is 5 minutes)
$assumedVideoDuration = 300; // 5 minutes in seconds
$completionRate = $watchStats['avg_watch_time'] > 0 
    ? min(($watchStats['avg_watch_time'] / $assumedVideoDuration) * 100, 100) 
    : 0;

// Drop-off points (group by time ranges)
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN watch_duration < 10 THEN '0-10s'
            WHEN watch_duration < 30 THEN '10-30s'
            WHEN watch_duration < 60 THEN '30-60s'
            WHEN watch_duration < 120 THEN '1-2min'
            WHEN watch_duration < 300 THEN '2-5min'
            ELSE '5min+'
        END as time_range,
        COUNT(*) as viewer_count
    FROM views_log
    WHERE link_id = ? AND is_counted = 1
    GROUP BY time_range
    ORDER BY FIELD(time_range, '0-10s', '10-30s', '30-60s', '1-2min', '2-5min', '5min+')
");
$stmt->execute([$linkId]);
$dropOffPoints = $stmt->fetchAll();

// Engagement Score Calculation
$engagementScore = calculateEngagementScore($linkId);

// Best Performing Time Slots
$stmt = $pdo->prepare("
    SELECT 
        HOUR(viewed_at) as hour,
        COUNT(*) as views,
        AVG(watch_duration) as avg_watch_time,
        SUM(earnings) as earnings
    FROM views_log
    WHERE link_id = ? AND is_counted = 1
    GROUP BY HOUR(viewed_at)
    ORDER BY views DESC
");
$stmt->execute([$linkId]);
$timeSlots = $stmt->fetchAll();

// Audience Retention Over Time
$stmt = $pdo->prepare("
    SELECT 
        DATE(viewed_at) as date,
        COUNT(*) as views,
        AVG(watch_duration) as avg_watch_time,
        COUNT(CASE WHEN watch_duration >= 60 THEN 1 END) as retained_viewers
    FROM views_log
    WHERE link_id = ? AND is_counted = 1 AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(viewed_at)
    ORDER BY date ASC
");
$stmt->execute([$linkId]);
$retentionData = $stmt->fetchAll();

// Device-wise Performance
$stmt = $pdo->prepare("
    SELECT 
        device_type,
        COUNT(*) as views,
        AVG(watch_duration) as avg_watch_time,
        SUM(earnings) as earnings
    FROM views_log
    WHERE link_id = ? AND is_counted = 1
    GROUP BY device_type
    ORDER BY views DESC
");
$stmt->execute([$linkId]);
$devicePerformance = $stmt->fetchAll();

include 'header.php';
?>

<style>
.metric-card {
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.engagement-gauge {
    width: 150px;
    height: 150px;
    margin: 0 auto;
}
.retention-bar {
    height: 30px;
    background: linear-gradient(to right, #dc3545, #ffc107, #28a745);
    border-radius: 15px;
    position: relative;
}
.retention-marker {
    position: absolute;
    top: -5px;
    width: 3px;
    height: 40px;
    background: #000;
}
</style>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <a href="links.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    Video Performance Analytics
                </h1>
            </div>

            <!-- Video Info -->
            <div class="card mb-4">
                <div class="card-body">
                    <h4><?= htmlspecialchars($link['title']) ?></h4>
                    <p class="text-muted mb-0">
                        <i class="fas fa-link"></i> <?= SITE_URL ?>/<?= $link['short_code'] ?>
                    </p>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card metric-card bg-primary text-white">
                        <h3><?= number_format($watchStats['total_views']) ?></h3>
                        <p class="mb-0">Total Views</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card metric-card bg-success text-white">
                        <h3><?= gmdate("i:s", $watchStats['avg_watch_time']) ?></h3>
                        <p class="mb-0">Avg Watch Time</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card metric-card bg-info text-white">
                        <h3><?= number_format($completionRate, 1) ?>%</h3>
                        <p class="mb-0">Completion Rate</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card metric-card bg-warning text-white">
                        <h3><?= $engagementScore ?>/100</h3>
                        <p class="mb-0">Engagement Score</p>
                    </div>
                </div>
            </div>

            <!-- Engagement Breakdown -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-stopwatch"></i> Watch Time Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>30+ seconds:</strong> <?= number_format($watchStats['views_30s_plus']) ?> views 
                                (<?= number_format(($watchStats['views_30s_plus'] / max($watchStats['total_views'], 1)) * 100, 1) ?>%)
                            </div>
                            <div class="mb-3">
                                <strong>1+ minute:</strong> <?= number_format($watchStats['views_1min_plus']) ?> views 
                                (<?= number_format(($watchStats['views_1min_plus'] / max($watchStats['total_views'], 1)) * 100, 1) ?>%)
                            </div>
                            <div class="mb-3">
                                <strong>2+ minutes:</strong> <?= number_format($watchStats['views_2min_plus']) ?> views 
                                (<?= number_format(($watchStats['views_2min_plus'] / max($watchStats['total_views'], 1)) * 100, 1) ?>%)
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Engagement Score Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="engagementChart"></canvas>
                            <div class="mt-3">
                                <p class="mb-1"><strong>Score Calculation:</strong></p>
                                <ul class="small">
                                    <li>Average Watch Time: <?= number_format((min($watchStats['avg_watch_time'], 300) / 300) * 40) ?>/40 pts</li>
                                    <li>Completion Rate: <?= number_format($completionRate * 0.3) ?>/30 pts</li>
                                    <li>Viewer Retention: <?= number_format(($watchStats['views_1min_plus'] / max($watchStats['total_views'], 1)) * 30) ?>/30 pts</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Drop-off Analysis -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-area"></i> Viewer Drop-off Points</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="dropoffChart" height="80"></canvas>
                            <div class="mt-3">
                                <h6>Analysis:</h6>
                                <?php
                                $maxDropoff = array_reduce($dropOffPoints, function($carry, $item) {
                                    return $item['viewer_count'] > ($carry['viewer_count'] ?? 0) ? $item : $carry;
                                }, []);
                                ?>
                                <p class="mb-0">
                                    Most viewers drop off in the <strong><?= $maxDropoff['time_range'] ?? 'N/A' ?></strong> range 
                                    with <?= number_format($maxDropoff['viewer_count'] ?? 0) ?> viewers leaving.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Best Performing Time Slots -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock"></i> Best Performing Time Slots</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="timeSlotsChart" height="80"></canvas>
                            <div class="mt-3">
                                <?php
                                $bestSlot = array_reduce($timeSlots, function($carry, $item) {
                                    return $item['views'] > ($carry['views'] ?? 0) ? $item : $carry;
                                }, []);
                                ?>
                                <p class="mb-0">
                                    <strong>Peak Performance:</strong> 
                                    <?= sprintf('%02d:00 - %02d:00', $bestSlot['hour'] ?? 0, ($bestSlot['hour'] ?? 0) + 1) ?> 
                                    with <?= number_format($bestSlot['views'] ?? 0) ?> views and 
                                    $<?= number_format($bestSlot['earnings'] ?? 0, 2) ?> earnings
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audience Retention Graph -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-users"></i> Audience Retention Over Time (30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="retentionChart" height="80"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Device Performance -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-mobile-alt"></i> Device-wise Performance</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Device Type</th>
                                            <th>Views</th>
                                            <th>Avg Watch Time</th>
                                            <th>Earnings</th>
                                            <th>Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($devicePerformance as $device): ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-<?= $device['device_type'] === 'mobile' ? 'mobile' : ($device['device_type'] === 'tablet' ? 'tablet' : 'desktop') ?>"></i>
                                                <?= ucfirst($device['device_type']) ?>
                                            </td>
                                            <td><?= number_format($device['views']) ?></td>
                                            <td><?= gmdate("i:s", $device['avg_watch_time']) ?></td>
                                            <td>$<?= number_format($device['earnings'], 2) ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <?php 
                                                    $maxViews = max(array_column($devicePerformance, 'views'));
                                                    $percentage = ($device['views'] / $maxViews) * 100;
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

            <!-- Recommendations -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-lightbulb"></i> AI-Powered Recommendations</h5>
                </div>
                <div class="card-body">
                    <?php
                    $recommendations = generateRecommendations($watchStats, $completionRate, $engagementScore);
                    ?>
                    <ul>
                        <?php foreach ($recommendations as $rec): ?>
                        <li><?= $rec ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Engagement Pie Chart
new Chart(document.getElementById('engagementChart'), {
    type: 'doughnut',
    data: {
        labels: ['Watch Time', 'Completion', 'Retention'],
        datasets: [{
            data: [
                <?= number_format((min($watchStats['avg_watch_time'], 300) / 300) * 40) ?>,
                <?= number_format($completionRate * 0.3) ?>,
                <?= number_format(($watchStats['views_1min_plus'] / max($watchStats['total_views'], 1)) * 30) ?>
            ],
            backgroundColor: ['#007bff', '#28a745', '#ffc107']
        }]
    },
    options: { responsive: true }
});

// Drop-off Chart
new Chart(document.getElementById('dropoffChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($dropOffPoints, 'time_range')) ?>,
        datasets: [{
            label: 'Viewers',
            data: <?= json_encode(array_column($dropOffPoints, 'viewer_count')) ?>,
            backgroundColor: 'rgba(220, 53, 69, 0.7)'
        }]
    },
    options: { responsive: true }
});

// Time Slots Chart
new Chart(document.getElementById('timeSlotsChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(function($t) { return sprintf('%02d:00', $t['hour']); }, $timeSlots)) ?>,
        datasets: [{
            label: 'Views',
            data: <?= json_encode(array_column($timeSlots, 'views')) ?>,
            borderColor: '#17a2b8',
            backgroundColor: 'rgba(23, 162, 184, 0.2)',
            fill: true
        }]
    },
    options: { responsive: true }
});

// Retention Chart
new Chart(document.getElementById('retentionChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($retentionData, 'date')) ?>,
        datasets: [{
            label: 'Total Views',
            data: <?= json_encode(array_column($retentionData, 'views')) ?>,
            borderColor: '#007bff',
            yAxisID: 'y'
        }, {
            label: 'Retained Viewers (1min+)',
            data: <?= json_encode(array_column($retentionData, 'retained_viewers')) ?>,
            borderColor: '#28a745',
            yAxisID: 'y'
        }]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
            y: { type: 'linear', display: true, position: 'left' }
        }
    }
});
</script>

<?php include 'footer.php'; ?>

<?php
function calculateEngagementScore($linkId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            AVG(watch_duration) as avg_watch,
            COUNT(CASE WHEN watch_duration >= 60 THEN 1 END) / COUNT(*) * 100 as retention_rate
        FROM views_log
        WHERE link_id = ? AND is_counted = 1
    ");
    $stmt->execute([$linkId]);
    $data = $stmt->fetch();
    
    // Score out of 100
    $watchScore = min(($data['avg_watch'] / 300) * 40, 40); // Max 40 points
    $retentionScore = ($data['retention_rate'] / 100) * 30; // Max 30 points
    $viewsScore = 30; // Placeholder, can be based on total views
    
    return round($watchScore + $retentionScore + $viewsScore);
}

function generateRecommendations($watchStats, $completionRate, $engagementScore) {
    $recommendations = [];
    
    if ($watchStats['avg_watch_time'] < 30) {
        $recommendations[] = "💡 Average watch time is low. Consider improving video thumbnail and opening to grab attention.";
    }
    
    if ($completionRate < 25) {
        $recommendations[] = "⚠️ Low completion rate. Video content may need to be more engaging or shorter.";
    }
    
    if ($engagementScore < 50) {
        $recommendations[] = "📈 Overall engagement is below average. Try A/B testing different thumbnails and titles.";
    }
    
    if ($watchStats['views_30s_plus'] / max($watchStats['total_views'], 1) < 0.5) {
        $recommendations[] = "🎯 Over 50% of viewers leave within 30 seconds. Improve your video's first impression.";
    }
    
    if (empty($recommendations)) {
        $recommendations[] = "✅ Great performance! Your video is engaging viewers well. Keep up the good work!";
        $recommendations[] = "🚀 Consider promoting this video more as it has high engagement.";
    }
    
    return $recommendations;
}
?>
