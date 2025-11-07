<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/currencies.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$error = '';
$success = '';

// Get settings
$minWithdrawal = getSetting('min_withdrawal', 5.00);

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $currency = sanitizeInput($_POST['currency'] ?? 'USD');
    $method = sanitizeInput($_POST['payment_method'] ?? '');
    $details = $_POST['payment_details'] ?? [];
    
    // Validation
    if ($amount < $minWithdrawal) {
        $error = "Minimum withdrawal amount is $" . number_format($minWithdrawal, 2);
    } elseif ($amount > $user['balance']) {
        $error = "Insufficient balance. Available: $" . number_format($user['balance'], 2);
    } elseif (empty($method)) {
        $error = "Please select a payment method";
    } elseif (empty($details)) {
        $error = "Please provide payment details";
    } else {
        // Convert to selected currency
        $convertedAmount = convertCurrency($amount, 'USD', $currency);
        
        // Deduct from balance
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);
        
        // Create withdrawal request
        $stmt = $pdo->prepare("
            INSERT INTO withdrawals (user_id, amount_usd, amount, currency, payment_method, payment_details, status)
            VALUES (?, ?, ?, ?, ?, ?, 'processing')
        ");
        $stmt->execute([
            $userId,
            $amount,
            $convertedAmount,
            $currency,
            $method,
            json_encode($details)
        ]);
        
        $success = "Withdrawal request submitted successfully! You'll receive payment within 24-48 hours.";
    }
}

// Get withdrawal history
$stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY requested_at DESC LIMIT 10");
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-money-check"></i> Withdraw Earnings</h1>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row mb-4">
                <!-- Balance Card -->
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6><i class="fas fa-wallet"></i> Available Balance</h6>
                            <h2>$<?= number_format($user['balance'], 2) ?></h2>
                            <small>Minimum: $<?= number_format($minWithdrawal, 2) ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6><i class="fas fa-chart-line"></i> Total Earnings</h6>
                            <h2>$<?= number_format($user['total_earnings'], 2) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6><i class="fas fa-eye"></i> Total Views</h6>
                            <h2><?= number_format($user['total_views']) ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Withdrawal Form -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0">Request Withdrawal</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="withdrawForm">
                                <div class="mb-3">
                                    <label class="form-label">Amount (USD)</label>
                                    <input type="number" step="0.01" name="amount" class="form-control" 
                                           min="<?= $minWithdrawal ?>" max="<?= $user['balance'] ?>" required>
                                    <small class="text-muted">Min: $<?= number_format($minWithdrawal, 2) ?> | Max: $<?= number_format($user['balance'], 2) ?></small>
                                </div>

<div class="mb-3">
    <label class="form-label">Currency</label>
    <select name="currency" class="form-select" id="currencySelect" required>
        <option value="">Select currency...</option>
        <?php 
        require_once __DIR__ . '/../config/currencies.php';
        foreach (SUPPORTED_CURRENCIES as $code => $name): 
        ?>
        <option value="<?= $code ?>"><?= $name ?> (<?= $code ?>)</option>
        <?php endforeach; ?>
    </select>
</div>

                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <select name="payment_method" class="form-select" id="paymentMethod" required>
                                        <option value="">Select method...</option>
                                        <option value="PayPal">PayPal</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="UPI">UPI (India)</option>
                                        <option value="Crypto">Cryptocurrency</option>
                                        <option value="Paytm">Paytm</option>
                                        <option value="PhonePe">PhonePe</option>
                                    </select>
                                </div>

                                <!-- Payment Details (Dynamic) -->
                                <div id="paymentDetails"></div>

                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-paper-plane"></i> Submit Withdrawal Request
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Important Notes -->
                    <div class="card mt-4">
                        <div class="card-header bg-warning">
                            <h6 class="mb-0"><i class="fas fa-info-circle"></i> Important Information</h6>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li>Minimum withdrawal: $<?= number_format($minWithdrawal, 2) ?></li>
                                <li>Processing time: 24-48 hours</li>
                                <li>Withdrawals are processed on business days</li>
                                <li>Make sure payment details are correct</li>
                                <li>You'll receive confirmation via email</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Withdrawal History -->
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Withdrawals</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($history)): ?>
                            <p class="text-muted text-center py-4">No withdrawal history yet</p>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($history as $w): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($w['requested_at'])) ?></td>
                                            <td>
                                                <strong>$<?= number_format($w['amount_usd'], 2) ?></strong><br>
                                                <small class="text-muted"><?= $w['amount'] ?> <?= $w['currency'] ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($w['payment_method']) ?></td>
                                            <td>
                                                <?php
                                                $badges = [
                                                    'processing' => 'warning',
                                                    'accepted' => 'info',
                                                    'paid' => 'success',
                                                    'rejected' => 'danger'
                                                ];
                                                $badge = $badges[$w['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $badge ?>"><?= ucfirst($w['status']) ?></span>
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

<script>
// Dynamic payment details based on method
document.getElementById('paymentMethod').addEventListener('change', function() {
    const method = this.value;
    const container = document.getElementById('paymentDetails');
    
    let html = '';
    
    switch(method) {
        case 'PayPal':
            html = `
                <div class="mb-3">
                    <label class="form-label">PayPal Email</label>
                    <input type="email" name="payment_details[email]" class="form-control" required>
                </div>
            `;
            break;
        case 'Bank Transfer':
            html = `
                <div class="mb-3">
                    <label class="form-label">Account Holder Name</label>
                    <input type="text" name="payment_details[account_name]" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bank Name</label>
                    <input type="text" name="payment_details[bank_name]" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Account Number</label>
                    <input type="text" name="payment_details[account_number]" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">IFSC Code (If India)</label>
                    <input type="text" name="payment_details[ifsc]" class="form-control">
                </div>
            `;
            break;
        case 'UPI':
            html = `
                <div class="mb-3">
                    <label class="form-label">UPI ID</label>
                    <input type="text" name="payment_details[upi_id]" class="form-control" placeholder="username@paytm" required>
                </div>
            `;
            break;
        case 'Crypto':
            html = `
                <div class="mb-3">
                    <label class="form-label">Cryptocurrency</label>
                    <select name="payment_details[crypto_type]" class="form-select" required>
                        <option value="BTC">Bitcoin (BTC)</option>
                        <option value="ETH">Ethereum (ETH)</option>
                        <option value="USDT">Tether (USDT)</option>
                        <option value="LTC">Litecoin (LTC)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Wallet Address</label>
                    <input type="text" name="payment_details[wallet_address]" class="form-control" required>
                </div>
            `;
            break;
        case 'Paytm':
        case 'PhonePe':
            html = `
                <div class="mb-3">
                    <label class="form-label">Mobile Number</label>
                    <input type="tel" name="payment_details[mobile]" class="form-control" pattern="[0-9]{10}" required>
                </div>
            `;
            break;
    }
    
    container.innerHTML = html;
});
</script>

<script>
// Fix: Payment method change event
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodSelect = document.getElementById('paymentMethod');
    const paymentDetailsContainer = document.getElementById('paymentDetails');
    
    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', function() {
            const method = this.value;
            let html = '';
            
            switch(method) {
                case 'PayPal':
                    html = `
                        <div class="mb-3">
                            <label class="form-label">PayPal Email</label>
                            <input type="email" name="payment_details[email]" class="form-control" placeholder="your@email.com" required>
                        </div>
                    `;
                    break;
                    
                case 'Bank Transfer':
                    html = `
                        <div class="mb-3">
                            <label class="form-label">Account Holder Name</label>
                            <input type="text" name="payment_details[account_name]" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="payment_details[bank_name]" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="payment_details[account_number]" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">IFSC Code (India) / SWIFT Code</label>
                            <input type="text" name="payment_details[ifsc]" class="form-control">
                        </div>
                    `;
                    break;
                    
                case 'UPI':
                    html = `
                        <div class="mb-3">
                            <label class="form-label">UPI ID</label>
                            <input type="text" name="payment_details[upi_id]" class="form-control" placeholder="username@paytm" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mobile Number</label>
                            <input type="tel" name="payment_details[mobile]" class="form-control" pattern="[0-9]{10}" placeholder="9876543210" required>
                        </div>
                    `;
                    break;
                    
                case 'Crypto':
                    html = `
                        <div class="mb-3">
                            <label class="form-label">Cryptocurrency</label>
                            <select name="payment_details[crypto_type]" class="form-select" required>
                                <option value="">Select...</option>
                                <option value="BTC">Bitcoin (BTC)</option>
                                <option value="ETH">Ethereum (ETH)</option>
                                <option value="USDT">Tether (USDT)</option>
                                <option value="LTC">Litecoin (LTC)</option>
                                <option value="TRX">TRON (TRX)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Wallet Address</label>
                            <input type="text" name="payment_details[wallet_address]" class="form-control" required>
                        </div>
                    `;
                    break;
                    
                case 'Paytm':
                case 'PhonePe':
                case 'GooglePay':
                    html = `
                        <div class="mb-3">
                            <label class="form-label">Mobile Number</label>
                            <input type="tel" name="payment_details[mobile]" class="form-control" pattern="[0-9]{10}" placeholder="9876543210" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="payment_details[name]" class="form-control" required>
                        </div>
                    `;
                    break;
                    
                default:
                    html = '<p class="text-muted">Please select a payment method</p>';
            }
            
            paymentDetailsContainer.innerHTML = html;
        });
    }
});
</script>

<?php include 'footer.php'; ?>