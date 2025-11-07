<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/currencies.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: ../login.php');
    exit;
}

// Auto-update currency rates
autoUpdateCurrencyRates();

// Get user stats
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM links WHERE user_id = ?) as total_links,
        (SELECT COUNT(*) FROM links WHERE user_id = ? AND is_active = 1) as active_links,
        (SELECT SUM(today_views) FROM links WHERE user_id = ?) as today_views,
        (SELECT SUM(amount) FROM referral_earnings WHERE referrer_id = ?) as referral_earnings
");
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$stats = $stmt->fetch();

// Get recent links
$stmt = $pdo->prepare("
    SELECT * FROM links 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user['id']]);
$recentLinks = $stmt->fetchAll();

// Get traffic analytics (last 7 days)
$analytics = getTrafficAnalytics($user['id'], null, 7);

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleCurrency()">
                        <i class="fas fa-exchange-alt"></i> Currency: <span id="currentCurrency"><?= $user['preferred_currency'] ?></span>
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card bg-gradient-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Balance</div>
                                    <div class="h4 mb-0 font-weight-bold" id="balanceDisplay">
                                        <?= formatCurrency($user['balance'], $user['preferred_currency']) ?>
                                    </div>
                                </div>
                                <div class="icon-circle">
                                    <i class="fas fa-wallet fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card bg-gradient-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Total Views</div>
                                    <div class="h4 mb-0 font-weight-bold"><?= number_format($user['total_views']) ?></div>
                                    <small>Today: <?= number_format($stats['today_views'] ?? 0) ?></small>
                                </div>
                                <div class="icon-circle">
                                    <i class="fas fa-eye fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card bg-gradient-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Total Earnings</div>
                                    <div class="h4 mb-0 font-weight-bold" id="earningsDisplay">
                                        <?= formatCurrency($user['total_earnings'], $user['preferred_currency']) ?>
                                    </div>
                                </div>
                                <div class="icon-circle">
                                    <i class="fas fa-dollar-sign fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card bg-gradient-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Active Links</div>
                                    <div class="h4 mb-0 font-weight-bold"><?= number_format($stats['active_links']) ?></div>
                                    <small>Total: <?= number_format($stats['total_links']) ?></small>
                                </div>
                                <div class="icon-circle">
                                    <i class="fas fa-link fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <a href="links.php?action=create" class="btn btn-primary btn-block">
                                        <i class="fas fa-plus"></i> Create Link
                                    </a>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <a href="links.php?action=bulk" class="btn btn-info btn-block">
                                        <i class="fas fa-upload"></i> Bulk Convert
                                    </a>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <a href="withdraw.php" class="btn btn-success btn-block">
                                        <i class="fas fa-money-check"></i> Withdraw
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Referral Program</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Your Referral Code:</strong></p>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" value="<?= $user['referral_code'] ?>" id="refCode" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyToClipboard('refCode')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <p class="mb-1"><small>Referral Link:</small></p>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?= SITE_URL ?>/register?ref=<?= $user['referral_code'] ?>" id="refLink" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyToClipboard('refLink')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <p class="mt-3 mb-0"><strong>Earnings:</strong> <?= formatCurrency($stats['referral_earnings'] ?? 0, $user['preferred_currency']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Views Chart -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Views Trend (Last 7 Days)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="viewsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Links -->
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold">Recent Links</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Short URL</th>
                                    <th>Views</th>
                                    <th>Today</th>
                                    <th>Earnings</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLinks as $link): ?>
                                <tr>
                                    <td><?= htmlspecialchars($link['title']) ?></td>
                                    <td>
                                        <code><?= SITE_URL . '/' . ($link['custom_alias'] ?: $link['short_code']) ?></code>
                                        <button class="btn btn-sm btn-link" onclick="copyToClipboard('link_<?= $link['id'] ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <input type="hidden" id="link_<?= $link['id'] ?>" value="<?= SITE_URL . '/' . ($link['custom_alias'] ?: $link['short_code']) ?>">
                                    </td>
                                    <td><?= number_format($link['views']) ?></td>
                                    <td><?= number_format($link['today_views']) ?></td>
                                    <td><?= formatCurrency($link['earnings'], $user['preferred_currency']) ?></td>
                                    <td>
                                        <a href="link_stats.php?id=<?= $link['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-chart-line"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="links.php" class="btn btn-primary">View All Links</a>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const analyticsData = <?= json_encode(array_reverse($analytics)) ?>;

const ctx = document.getElementById('viewsChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: analyticsData.map(d => d.date),
        datasets: [{
            label: 'Views',
            data: analyticsData.map(d => d.views),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

function copyToClipboard(elementId) {
    const el = document.getElementById(elementId);
    el.select();
    document.execCommand('copy');
    alert('Copied to clipboard!');
}

// Currency data from PHP
const balanceUSD = <?= $user['balance'] ?>;
const earningsUSD = <?= $user['total_earnings'] ?>;
const currencyRates = <?= json_encode([
    'USD' => 1,
    'EUR' => 0.85,
    'GBP' => 0.73,
    'INR' => 83.12,
    // Add more as needed
]) ?>;

let currentCurrency = '<?= $user['preferred_currency'] ?>';

function toggleCurrency() {
    const currencies = Object.keys(currencyRates);
    const currentIndex = currencies.indexOf(currentCurrency);
    const nextIndex = (currentIndex + 1) % currencies.length;
    currentCurrency = currencies[nextIndex];
    
    updateCurrencyDisplay();
    
    // Save preference
    fetch('../api/user_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ preferred_currency: currentCurrency })
    });
}

function updateCurrencyDisplay() {
    const rate = currencyRates[currentCurrency];
    document.getElementById('currentCurrency').textContent = currentCurrency;
    document.getElementById('balanceDisplay').textContent = formatMoney(balanceUSD * rate, currentCurrency);
    document.getElementById('earningsDisplay').textContent = formatMoney(earningsUSD * rate, currentCurrency);
}

function formatMoney(amount, currency) {
    const symbols = { USD: '$', EUR: '€', GBP: '£', INR: '₹' };
    return (symbols[currency] || '$') + amount.toFixed(2);
}
</script>

<?php include 'footer.php'; ?>