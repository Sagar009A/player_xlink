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

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $telegram = sanitizeInput($_POST['telegram_id'] ?? '');
        $trafficSource = sanitizeInput($_POST['traffic_source'] ?? '');
        $trafficCategory = sanitizeInput($_POST['traffic_category'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE users SET telegram_id = ?, traffic_source = ?, traffic_category = ? WHERE id = ?");
        if ($stmt->execute([$telegram, $trafficSource, $trafficCategory, $userId])) {
            $success = 'Profile updated successfully!';
        } else {
            $error = 'Failed to update profile';
        }
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (verifyPassword($currentPassword, $user['password'])) {
            if ($newPassword === $confirmPassword) {
                if (strlen($newPassword) >= 6) {
                    $hashedPassword = hashPassword($newPassword);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt->execute([$hashedPassword, $userId])) {
                        $success = 'Password changed successfully!';
                    } else {
                        $error = 'Failed to change password';
                    }
                } else {
                    $error = 'Password must be at least 6 characters';
                }
            } else {
                $error = 'New passwords do not match';
            }
        } else {
            $error = 'Current password is incorrect';
        }
    }
    
    if ($action === 'update_currency') {
        $currency = sanitizeInput($_POST['preferred_currency'] ?? 'USD');
        $stmt = $pdo->prepare("UPDATE users SET preferred_currency = ? WHERE id = ?");
        if ($stmt->execute([$currency, $userId])) {
            $success = 'Currency preference updated!';
        }
    }
    
    // Refresh user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
}

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-user-cog"></i> Profile & Settings</h1>
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

            <div class="row">
                <!-- Account Info -->
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user"></i> Account Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                    <small class="text-muted">Username cannot be changed</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                    <small class="text-muted">Email cannot be changed</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Telegram ID</label>
                                    <input type="text" name="telegram_id" class="form-control" value="<?= htmlspecialchars($user['telegram_id']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Traffic Source</label>
                                    <select name="traffic_source" class="form-select" required>
                                        <option value="YouTube" <?= $user['traffic_source'] === 'YouTube' ? 'selected' : '' ?>>YouTube</option>
                                        <option value="Telegram" <?= $user['traffic_source'] === 'Telegram' ? 'selected' : '' ?>>Telegram</option>
                                        <option value="Instagram" <?= $user['traffic_source'] === 'Instagram' ? 'selected' : '' ?>>Instagram</option>
                                        <option value="Facebook" <?= $user['traffic_source'] === 'Facebook' ? 'selected' : '' ?>>Facebook</option>
                                        <option value="Twitter" <?= $user['traffic_source'] === 'Twitter' ? 'selected' : '' ?>>Twitter</option>
                                        <option value="Website" <?= $user['traffic_source'] === 'Website' ? 'selected' : '' ?>>Website</option>
                                        <option value="Other" <?= $user['traffic_source'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Traffic Category</label>
                                    <select name="traffic_category" class="form-select" required>
                                        <option value="0-500" <?= $user['traffic_category'] === '0-500' ? 'selected' : '' ?>>0 - 500 views/day</option>
                                        <option value="1K-10K" <?= $user['traffic_category'] === '1K-10K' ? 'selected' : '' ?>>1K - 10K views/day</option>
                                        <option value="10K-100K" <?= $user['traffic_category'] === '10K-100K' ? 'selected' : '' ?>>10K - 100K views/day</option>
                                        <option value="100K-1M" <?= $user['traffic_category'] === '100K-1M' ? 'selected' : '' ?>>100K - 1M views/day</option>
                                        <option value="1M+" <?= $user['traffic_category'] === '1M+' ? 'selected' : '' ?>>1M+ views/day</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- API Key -->
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-key"></i> API Key</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Your API Key</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="apiKey" value="<?= htmlspecialchars($user['api_key']) ?>" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyApiKey()">
                                        <i class="fas fa-copy"></i> Copy
                                    </button>
                                </div>
                                <small class="text-muted">Use this key for API access. Keep it secret!</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Referral Code</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="referralCode" value="<?= htmlspecialchars($user['referral_code']) ?>" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyReferralCode()">
                                        <i class="fas fa-copy"></i> Copy
                                    </button>
                                </div>
                                <small class="text-muted">Share this code to earn referral commission</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security & Preferences -->
                <div class="col-md-6">
                    <!-- Change Password -->
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-lock"></i> Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" minlength="6" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                                </div>

                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Currency Preference -->
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-dollar-sign"></i> Currency Preference</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_currency">
                                
                                <div class="mb-3">
                                    <label class="form-label">Preferred Currency</label>
                                    <select name="preferred_currency" class="form-select">
                                        <?php
                                        require_once __DIR__ . '/../config/currencies.php';
                                        foreach (SUPPORTED_CURRENCIES as $code => $name):
                                        ?>
                                        <option value="<?= $code ?>" <?= $user['preferred_currency'] === $code ? 'selected' : '' ?>>
                                            <?= $name ?> (<?= $code ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Currency
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Account Stats -->
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Account Stats</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tr>
                                    <th>Member Since:</th>
                                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Account Status:</th>
                                    <td>
                                        <span class="badge bg-<?= $user['status'] === 'approved' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($user['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Total Links:</th>
                                    <td><?= $pdo->prepare("SELECT COUNT(*) FROM links WHERE user_id = ?")->execute([$userId]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0 ?></td>
                                </tr>
                                <tr>
                                    <th>Total Views:</th>
                                    <td><?= number_format($user['total_views']) ?></td>
                                </tr>
                                <tr>
                                    <th>Total Earnings:</th>
                                    <td>$<?= number_format($user['total_earnings'], 2) ?></td>
                                </tr>
                                <tr>
                                    <th>Current Balance:</th>
                                    <td><strong class="text-success">$<?= number_format($user['balance'], 2) ?></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function copyApiKey() {
    const input = document.getElementById('apiKey');
    const text = input.value;
    
    // Modern clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('API Key copied to clipboard!', 'success');
        }).catch(err => {
            console.error('Clipboard error:', err);
            fallbackCopy(text, 'API Key');
        });
    } else {
        fallbackCopy(text, 'API Key');
    }
}

function copyReferralCode() {
    const input = document.getElementById('referralCode');
    const text = input.value;
    
    // Modern clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Referral code copied to clipboard!', 'success');
        }).catch(err => {
            console.error('Clipboard error:', err);
            fallbackCopy(text, 'Referral code');
        });
    } else {
        fallbackCopy(text, 'Referral code');
    }
}

function fallbackCopy(text, itemName) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.top = '0';
    textarea.style.left = '0';
    textarea.style.width = '2em';
    textarea.style.height = '2em';
    textarea.style.padding = '0';
    textarea.style.border = 'none';
    textarea.style.outline = 'none';
    textarea.style.boxShadow = 'none';
    textarea.style.background = 'transparent';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showToast(itemName + ' copied to clipboard!', 'success');
        } else {
            alert(itemName + ' copied! (Please try again if not working)');
        }
    } catch (err) {
        console.error('Fallback copy error:', err);
        alert('Please manually copy the ' + itemName);
    }
    
    document.body.removeChild(textarea);
}

// Toast notification function
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed top-0 start-50 translate-middle-x mt-3`;
    toast.style.zIndex = '9999';
    toast.style.minWidth = '300px';
    toast.innerHTML = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('fade');
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}
</script>

<?php include 'footer.php'; ?>