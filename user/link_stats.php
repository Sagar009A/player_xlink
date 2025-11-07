<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$linkId = intval($_GET['id'] ?? 0);

if (!$linkId) {
    header('Location: links.php');
    exit;
}

// Get link details
$stmt = $pdo->prepare("SELECT * FROM links WHERE id = ? AND user_id = ?");
$stmt->execute([$linkId, $userId]);
$link = $stmt->fetch();

if (!$link) {
    $_SESSION['error'] = 'Link not found or access denied';
    header('Location: links.php');
    exit;
}

// Get date range
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
$days = max(1, min(365, $days));

// Get basic stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_views,
        COUNT(DISTINCT ip_address) as unique_visitors,
        SUM(CASE WHEN is_counted = 1 THEN 1 ELSE 0 END) as counted_views,
        SUM(earnings) as total_earnings,
        AVG(watch_duration) as avg_duration
    FROM views_log
    WHERE link_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
");
$stmt->execute([$linkId, $days]);
$stats = $stmt->fetch();

// Get daily views
$stmt = $pdo->prepare("
    SELECT 
        DATE(viewed_at) as date,
        COUNT(*) as views,
        COUNT(DISTINCT ip_address) as unique_views,
        SUM(earnings) as daily_earnings
    FROM views_log
    WHERE link_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY DATE(viewed_at)
    ORDER BY date ASC
");
$stmt->execute([$linkId, $days]);
$dailyStats = $stmt->fetchAll();

// Get hourly distribution (last 24 hours)
$stmt = $pdo->prepare("
    SELECT 
        HOUR(viewed_at) as hour,
        COUNT(*) as views
    FROM views_log
    WHERE link_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY HOUR(viewed_at)
    ORDER BY hour
");
$stmt->execute([$linkId]);
$hourlyStats = $stmt->fetchAll();

// Get country stats
$stmt = $pdo->prepare("
    SELECT 
        country_name,
        country_code,
        COUNT(*) as views,
        COUNT(DISTINCT ip_address) as unique_visitors,
        SUM(earnings) as country_earnings
    FROM views_log
    WHERE link_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY country_code
    ORDER BY views DESC
    LIMIT 15
");
$stmt->execute([$linkId, $days]);
$countryStats = $stmt->fetchAll();

// Get device stats
$stmt = $pdo->prepare("
    SELECT 
        device_type,
        COUNT(*) as views,
        SUM(earnings) as device_earnings
    FROM views_log
    WHERE link_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY device_type
    ORDER BY views DESC
");
$stmt->execute([$linkId, $days]);
$deviceStats = $stmt->fetchAll();

// Get browser stats
$stmt = $pdo->prepare("
    SELECT 
        browser,
        COUNT(*) as views
    FROM views_log
    WHERE link_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY) AND browser IS NOT NULL
    GROUP BY browser
    ORDER BY views DESC
    LIMIT 10
");
$stmt->execute([$linkId, $days]);
$browserStats = $stmt->fetchAll();

// Get referrer stats
$stmt = $pdo->prepare("
    SELECT 
        referrer,
        COUNT(*) as views
    FROM views_log
    WHERE link_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY) AND referrer IS NOT NULL AND referrer != ''
    GROUP BY referrer
    ORDER BY views DESC
    LIMIT 10
");
$stmt->execute([$linkId, $days]);
$referrerStats = $stmt->fetchAll();

// Get recent views
$stmt = $pdo->prepare("
    SELECT *
    FROM views_log
    WHERE link_id = ?
    ORDER BY viewed_at DESC
    LIMIT 50
");
$stmt->execute([$linkId]);
$recentViews = $stmt->fetchAll();

// Calculate average CPM
$avgCPM = $stats['counted_views'] > 0 
    ? ($stats['total_earnings'] / $stats['counted_views']) * 1000 
    : 0;

// Generate short URL
$shortUrl = SITE_URL . '/' . $link['short_code'];

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <!-- Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <a href="links.php" class="btn btn-sm btn-secondary mb-2">
                        <i class="fas fa-arrow-left"></i> Back to Links
                    </a>
                    <h1 class="h2 mb-0">
                        <i class="fas fa-chart-line"></i> Link Statistics
                    </h1>
                </div>
                <div class="btn-group">
                    <a href="?id=<?= $linkId ?>&days=7" class="btn btn-sm btn-<?= $days === 7 ? 'primary' : 'outline-primary' ?>">7 Days</a>
                    <a href="?id=<?= $linkId ?>&days=30" class="btn btn-sm btn-<?= $days === 30 ? 'primary' : 'outline-primary' ?>">30 Days</a>
                    <a href="?id=<?= $linkId ?>&days=90" class="btn btn-sm btn-<?= $days === 90 ? 'primary' : 'outline-primary' ?>">90 Days</a>
                    <a href="?id=<?= $linkId ?>&days=365" class="btn btn-sm btn-<?= $days === 365 ? 'primary' : 'outline-primary' ?>">1 Year</a>
                </div>
            </div>

            <!-- Link Info Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <?php if ($link['thumbnail_path']): ?>
                            <img src="<?= htmlspecialchars($link['thumbnail_path']) ?>" 
                                 alt="Thumbnail" 
                                 class="img-fluid rounded">
                            <?php else: ?>
                            <div class="bg-secondary text-white d-flex align-items-center justify-content-center rounded" 
                                 style="height: 100px;">
                                <i class="fas fa-image fa-3x"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-7">
                            <h4><?= htmlspecialchars($link['title']) ?></h4>
                            <?php if ($link['description']): ?>
                            <p class="text-muted mb-2"><?= htmlspecialchars($link['description']) ?></p>
                            <?php endif; ?>
                            <p class="mb-1">
                                <strong>Short URL:</strong> 
                                <code><?= $shortUrl ?></code>
                                <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('<?= $shortUrl ?>')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </p>
                            <p class="mb-1">
                                <strong>Original URL:</strong> 
                                <a href="<?= htmlspecialchars($link['original_url']) ?>" target="_blank" class="text-truncate d-inline-block" style="max-width: 400px;">
                                    <?= htmlspecialchars($link['original_url']) ?>
                                </a>
                            </p>
                            <p class="mb-0">
                                <strong>Status:</strong> 
                                <span class="badge bg-<?= $link['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $link['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                                <strong class="ms-3">Created:</strong> <?= date('M d, Y H:i', strtotime($link['created_at'])) ?>
                            </p>
                        </div>
                        <div class="col-md-3 text-end">
                            <div class="btn-group-vertical w-100">
                                <a href="<?= $shortUrl ?>" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt"></i> Open Link
                                </a>
                                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#qrModal">
                                    <i class="fas fa-qrcode"></i> QR Code
                                </button>
                                <button class="btn btn-success" onclick="exportData()">
                                    <i class="fas fa-download"></i> Export Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Total Views</h6>
                                    <h2 class="mb-0"><?= number_format($stats['total_views']) ?></h2>
                                    <small>Unique: <?= number_format($stats['unique_visitors']) ?></small>
                                </div>
                                <i class="fas fa-eye fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Counted Views</h6>
                                    <h2 class="mb-0"><?= number_format($stats['counted_views']) ?></h2>
                                    <small>Earning views</small>
                                </div>
                                <i class="fas fa-check-circle fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Total Earnings</h6>
                                    <h2 class="mb-0">$<?= number_format($stats['total_earnings'], 2) ?></h2>
                                    <small>Last <?= $days ?> days</small>
                                </div>
                                <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Average CPM</h6>
                                    <h2 class="mb-0">$<?= number_format($avgCPM, 2) ?></h2>
                                    <small>Per 1000 views</small>
                                </div>
                                <i class="fas fa-chart-line fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <!-- Daily Views Chart -->
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-area"></i> Daily Views & Earnings</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="dailyChart" height="80"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Hourly Distribution -->
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock"></i> Hourly Views (24h)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="hourlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Device & Browser Stats -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-mobile-alt"></i> Device Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($deviceStats)): ?>
                            <p class="text-muted text-center py-4">No device data available</p>
                            <?php else: ?>
                            <canvas id="deviceChart" height="200"></canvas>
                            <div class="mt-3">
                                <table class="table table-sm">
                                    <tbody>
                                        <?php foreach ($deviceStats as $device): ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-<?= $device['device_type'] === 'mobile' ? 'mobile-alt' : ($device['device_type'] === 'tablet' ? 'tablet-alt' : 'desktop') ?>"></i>
                                                <?= ucfirst($device['device_type']) ?>
                                            </td>
                                            <td class="text-end"><?= number_format($device['views']) ?> views</td>
                                            <td class="text-end">$<?= number_format($device['device_earnings'], 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-browser"></i> Top Browsers</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($browserStats)): ?>
                            <p class="text-muted text-center py-4">No browser data available</p>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Browser</th>
                                            <th class="text-end">Views</th>
                                            <th class="text-end">Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $totalBrowserViews = array_sum(array_column($browserStats, 'views'));
                                        foreach ($browserStats as $browser):
                                            $percentage = ($browser['views'] / $totalBrowserViews) * 100;
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($browser['browser']) ?></td>
                                            <td class="text-end"><?= number_format($browser['views']) ?></td>
                                            <td class="text-end">
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?= $percentage ?>%">
                                                        <?= number_format($percentage, 1) ?>%
                                                    </div>
                                                </div>
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

            <!-- Country Stats -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-globe"></i> Top Countries</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($countryStats)): ?>
                            <p class="text-muted text-center py-4">No country data available</p>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Country</th>
                                            <th>Total Views</th>
                                            <th>Unique Visitors</th>
                                            <th>Earnings</th>
                                            <th>CPM</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $totalCountryViews = array_sum(array_column($countryStats, 'views'));
                                        foreach ($countryStats as $index => $country):
                                            $percentage = ($country['views'] / $totalCountryViews) * 100;
                                            $cpm = $country['views'] > 0 ? ($country['country_earnings'] / $country['views']) * 1000 : 0;
                                        ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($country['country_name']) ?></strong>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($country['country_code']) ?></span>
                                            </td>
                                            <td><?= number_format($country['views']) ?></td>
                                            <td><?= number_format($country['unique_visitors']) ?></td>
                                            <td><strong class="text-success">$<?= number_format($country['country_earnings'], 2) ?></strong></td>
                                            <td>$<?= number_format($cpm, 2) ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-info" role="progressbar" 
                                                         style="width: <?= $percentage ?>%">
                                                        <?= number_format($percentage, 1) ?>%
                                                    </div>
                                                </div>
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

            <!-- Referrer Stats -->
            <?php if (!empty($referrerStats)): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-external-link-alt"></i> Top Referrers</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Source</th>
                                            <th class="text-end">Views</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($referrerStats as $ref): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= htmlspecialchars($ref['referrer']) ?>" target="_blank" class="text-truncate d-inline-block" style="max-width: 500px;">
                                                    <?= htmlspecialchars($ref['referrer']) ?>
                                                </a>
                                            </td>
                                            <td class="text-end"><?= number_format($ref['views']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Views -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history"></i> Recent Views (Last 50)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentViews)): ?>
                            <p class="text-muted text-center py-4">No views yet</p>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Country</th>
                                            <th>Device</th>
                                            <th>Browser</th>
                                            <th>IP Address</th>
                                            <th>Duration</th>
                                            <th>Counted</th>
                                            <th>Earnings</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentViews as $view): ?>
                                        <tr>
                                            <td><?= date('M d, H:i', strtotime($view['viewed_at'])) ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($view['country_code']) ?></span>
                                                <?= htmlspecialchars($view['country_name']) ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-<?= $view['device_type'] === 'mobile' ? 'mobile-alt' : ($view['device_type'] === 'tablet' ? 'tablet-alt' : 'desktop') ?>"></i>
                                                <?= ucfirst($view['device_type']) ?>
                                            </td>
                                            <td><?= htmlspecialchars($view['browser']) ?></td>
                                            <td><code><?= htmlspecialchars($view['ip_address']) ?></code></td>
                                            <td><?= $view['watch_duration'] ? number_format($view['watch_duration']) . 's' : '-' ?></td>
                                            <td>
                                                <?php if ($view['is_counted']): ?>
                                                <span class="badge bg-success">Yes</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary" title="Not counted: <?= $view['reason'] ?>">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $view['is_counted'] ? '$' . number_format($view['earnings'], 4) : '-' ?></td>
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

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-qrcode"></i> QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrcode"></div>
                <p class="mt-3"><code><?= $shortUrl ?></code></p>
                <button class="btn btn-primary" onclick="downloadQR()">
                    <i class="fas fa-download"></i> Download QR Code
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Load QRCode.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
// Chart colors
const colors = {
    primary: '#3498db',
    success: '#2ecc71',
    warning: '#f39c12',
    danger: '#e74c3c',
    info: '#1abc9c'
};

// Daily Views Chart
const dailyData = <?= json_encode($dailyStats) ?>;
if (dailyData && dailyData.length > 0) {
    new Chart(document.getElementById('dailyChart'), {
        type: 'line',
        data: {
            labels: dailyData.map(d => d.date),
            datasets: [{
                label: 'Views',
                data: dailyData.map(d => parseInt(d.views)),
                borderColor: colors.primary,
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            }, {
                label: 'Unique Views',
                data: dailyData.map(d => parseInt(d.unique_views)),
                borderColor: colors.info,
                backgroundColor: 'rgba(26, 188, 156, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            }, {
                label: 'Earnings ($)',
                data: dailyData.map(d => parseFloat(d.daily_earnings)),
                borderColor: colors.success,
                backgroundColor: 'rgba(46, 204, 113, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: { display: true, position: 'top' }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: { display: true, text: 'Views' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: { display: true, text: 'Earnings ($)' },
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
}

// Hourly Chart
const hourlyData = <?= json_encode($hourlyStats) ?>;
const hourlyArray = new Array(24).fill(0);
hourlyData.forEach(h => {
    hourlyArray[parseInt(h.hour)] = parseInt(h.views);
});

new Chart(document.getElementById('hourlyChart'), {
    type: 'bar',
    data: {
        labels: Array.from({length: 24}, (_, i) => i + ':00'),
        datasets: [{
            label: 'Views',
            data: hourlyArray,
            backgroundColor: colors.primary
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Device Chart
const deviceData = <?= json_encode($deviceStats) ?>;
if (deviceData && deviceData.length > 0) {
    new Chart(document.getElementById('deviceChart'), {
        type: 'doughnut',
        data: {
            labels: deviceData.map(d => d.device_type.charAt(0).toUpperCase() + d.device_type.slice(1)),
            datasets: [{
                data: deviceData.map(d => parseInt(d.views)),
                backgroundColor: [colors.primary, colors.success, colors.warning]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

// Copy to clipboard
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Link copied to clipboard!', 'success');
        });
    } else {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('Link copied!', 'success');
    }
}

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed top-0 start-50 translate-middle-x mt-3`;
    toast.style.zIndex = '9999';
    toast.innerHTML = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('fade');
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}

// Generate QR Code
document.getElementById('qrModal').addEventListener('shown.bs.modal', function () {
    const container = document.getElementById('qrcode');
    container.innerHTML = ''; // Clear previous QR
    new QRCode(container, {
        text: '<?= $shortUrl ?>',
        width: 256,
        height: 256,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });
});

// Download QR Code
function downloadQR() {
    const canvas = document.querySelector('#qrcode canvas');
    if (canvas) {
        const link = document.createElement('a');
        link.download = 'qrcode-<?= $link['short_code'] ?>.png';
        link.href = canvas.toDataURL();
        link.click();
        showToast('QR Code downloaded!', 'success');
    }
}

// Export data to CSV
function exportData() {
    const csv = [
        ['Date', 'Views', 'Unique Views', 'Earnings'],
        ...dailyData.map(d => [d.date, d.views, d.unique_views, d.daily_earnings])
    ].map(row => row.join(',')).join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'link-stats-<?= $link['short_code'] ?>-<?= date('Y-m-d') ?>.csv';
    link.click();
    showToast('Data exported!', 'success');
}
</script>

<?php include 'footer.php'; ?>