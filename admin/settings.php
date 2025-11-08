<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Check admin login
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        // General Settings
        if (isset($_POST['site_name'])) {
            updateSetting('site_name', sanitizeInput($_POST['site_name']));
        }
        if (isset($_POST['site_tagline'])) {
            updateSetting('site_tagline', sanitizeInput($_POST['site_tagline']));
        }
        
        // Monetization Settings
        if (isset($_POST['default_cpm_rate'])) {
            updateSetting('default_cpm_rate', floatval($_POST['default_cpm_rate']));
        }
        if (isset($_POST['referral_commission'])) {
            updateSetting('referral_commission', floatval($_POST['referral_commission']));
        }
        if (isset($_POST['min_withdrawal'])) {
            updateSetting('min_withdrawal', floatval($_POST['min_withdrawal']));
        }
        if (isset($_POST['daily_view_limit_per_ip'])) {
            updateSetting('daily_view_limit_per_ip', intval($_POST['daily_view_limit_per_ip']));
        }
        
        // Feature Toggles (Checkboxes)
        updateSetting('app_login_enabled', isset($_POST['app_login_enabled']) ? '1' : '0');
        updateSetting('video_organization_enabled', isset($_POST['video_organization_enabled']) ? '1' : '0');
        updateSetting('auto_fetch_thumbnail', isset($_POST['auto_fetch_thumbnail']) ? '1' : '0');
        updateSetting('dark_mode_enabled', isset($_POST['dark_mode_enabled']) ? '1' : '0');
        
        // Update Intervals
        if (isset($_POST['stats_update_interval'])) {
            updateSetting('stats_update_interval', intval($_POST['stats_update_interval']));
        }
        if (isset($_POST['currency_update_interval'])) {
            updateSetting('currency_update_interval', intval($_POST['currency_update_interval']));
        }
        
        // API Settings
        if (isset($_POST['api_rate_limit'])) {
            updateSetting('api_rate_limit', intval($_POST['api_rate_limit']));
        }
        
        // Terabox API Settings
        if (isset($_POST['terabox_api_domain'])) {
            updateSetting('terabox_api_domain', sanitizeInput($_POST['terabox_api_domain']));
        }
        if (isset($_POST['terabox_js_token'])) {
            $token = trim($_POST['terabox_js_token']);
            if (!empty($token)) {
                updateSetting('terabox_js_token', $token);
            }
        }
        updateSetting('terabox_use_dynamic_domain', isset($_POST['terabox_use_dynamic_domain']) ? '1' : '0');
        
        $success = 'Settings updated successfully!';
        
    } catch (Exception $e) {
        $error = 'Failed to update settings: ' . $e->getMessage();
    }
}

// Get all current settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settingsArray = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $error = 'Failed to load settings: ' . $e->getMessage();
    $settingsArray = [];
}

// Helper function to get setting value with default
function getSettingValue($key, $default = '') {
    global $settingsArray;
    return isset($settingsArray[$key]) ? $settingsArray[$key] : $default;
}

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">System Settings</h1>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="update_settings" value="1">

                <!-- General Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog"></i> General Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Site Name</label>
                                <input type="text" class="form-control" name="site_name" 
                                       value="<?= htmlspecialchars(getSettingValue('site_name', 'LinkStreamX')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Site Tagline</label>
                                <input type="text" class="form-control" name="site_tagline" 
                                       value="<?= htmlspecialchars(getSettingValue('site_tagline', 'Turn Every View Into Value')) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monetization Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-dollar-sign"></i> Monetization Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default CPM Rate ($ per 1000 views)</label>
                                <input type="number" step="0.0001" class="form-control" name="default_cpm_rate" 
                                       value="<?= htmlspecialchars(getSettingValue('default_cpm_rate', '1.0000')) ?>">
                                <small class="text-muted">Base rate when no specific CPM rule matches</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Referral Commission (%)</label>
                                <input type="number" step="0.01" class="form-control" name="referral_commission" 
                                       value="<?= htmlspecialchars(getSettingValue('referral_commission', '10')) ?>">
                                <small class="text-muted">Percentage of referred user's earnings</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Minimum Withdrawal (USD)</label>
                                <input type="number" step="0.01" class="form-control" name="min_withdrawal" 
                                       value="<?= htmlspecialchars(getSettingValue('min_withdrawal', '5.00')) ?>">
                                <small class="text-muted">Minimum amount users can withdraw</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Daily View Limit per IP</label>
                                <input type="number" class="form-control" name="daily_view_limit_per_ip" 
                                       value="<?= htmlspecialchars(getSettingValue('daily_view_limit_per_ip', '50')) ?>">
                                <small class="text-muted">Max views from same IP per user per day</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feature Toggles -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-toggle-on"></i> Feature Toggles</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="app_login_enabled" 
                                   id="app_login_enabled" value="1" 
                                   <?= getSettingValue('app_login_enabled', '1') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="app_login_enabled">
                                <strong>Enable App Login/Registration</strong><br>
                                <small class="text-muted">Allow users to login via mobile app</small>
                            </label>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="video_organization_enabled" 
                                   id="video_organization_enabled" value="1" 
                                   <?= getSettingValue('video_organization_enabled', '1') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="video_organization_enabled">
                                <strong>Enable Video Organization (Folders)</strong><br>
                                <small class="text-muted">Let users organize links in folders</small>
                            </label>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="auto_fetch_thumbnail" 
                                   id="auto_fetch_thumbnail" value="1" 
                                   <?= getSettingValue('auto_fetch_thumbnail', '1') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="auto_fetch_thumbnail">
                                <strong>Auto-Fetch Video Thumbnails</strong><br>
                                <small class="text-muted">Automatically download thumbnails from video URLs</small>
                            </label>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="dark_mode_enabled" 
                                   id="dark_mode_enabled" value="1" 
                                   <?= getSettingValue('dark_mode_enabled', '1') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="dark_mode_enabled">
                                <strong>Enable Dark Mode</strong><br>
                                <small class="text-muted">Allow users to switch to dark theme</small>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Update Intervals -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock"></i> Update Intervals</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stats Update Interval (hours)</label>
                                <select class="form-select" name="stats_update_interval">
                                    <option value="2" <?= getSettingValue('stats_update_interval', '4') == '2' ? 'selected' : '' ?>>2 hours</option>
                                    <option value="4" <?= getSettingValue('stats_update_interval', '4') == '4' ? 'selected' : '' ?>>4 hours</option>
                                    <option value="5" <?= getSettingValue('stats_update_interval', '4') == '5' ? 'selected' : '' ?>>5 hours</option>
                                    <option value="6" <?= getSettingValue('stats_update_interval', '4') == '6' ? 'selected' : '' ?>>6 hours</option>
                                    <option value="12" <?= getSettingValue('stats_update_interval', '4') == '12' ? 'selected' : '' ?>>12 hours</option>
                                    <option value="24" <?= getSettingValue('stats_update_interval', '4') == '24' ? 'selected' : '' ?>>24 hours</option>
                                </select>
                                <small class="text-muted">How often to recalculate user statistics</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Currency Update Interval (hours)</label>
                                <select class="form-select" name="currency_update_interval">
                                    <option value="6" <?= getSettingValue('currency_update_interval', '24') == '6' ? 'selected' : '' ?>>6 hours</option>
                                    <option value="12" <?= getSettingValue('currency_update_interval', '24') == '12' ? 'selected' : '' ?>>12 hours</option>
                                    <option value="24" <?= getSettingValue('currency_update_interval', '24') == '24' ? 'selected' : '' ?>>24 hours</option>
                                    <option value="48" <?= getSettingValue('currency_update_interval', '24') == '48' ? 'selected' : '' ?>>48 hours</option>
                                </select>
                                <small class="text-muted">How often to fetch latest exchange rates</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- API Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plug"></i> API Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">API Rate Limit (requests per minute)</label>
                            <input type="number" class="form-control" name="api_rate_limit" 
                                   value="<?= htmlspecialchars(getSettingValue('api_rate_limit', '100')) ?>">
                            <small class="text-muted">Maximum API calls per user per minute</small>
                        </div>
                    </div>
                </div>

                <!-- Terabox API Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-box"></i> Terabox API Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="terabox_use_dynamic_domain" 
                                   id="terabox_use_dynamic_domain" value="1" 
                                   <?= getSettingValue('terabox_use_dynamic_domain', '1') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="terabox_use_dynamic_domain">
                                <strong>Use Dynamic Domain Detection</strong><br>
                                <small class="text-muted">Automatically detect and use the correct API domain for each TeraBox URL (1024tera.com, terabox.app, etc.)</small>
                            </label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Default Terabox API Domain</label>
                            <select class="form-select" name="terabox_api_domain">
                                <option value="www.terabox.app" <?= getSettingValue('terabox_api_domain', 'www.terabox.app') == 'www.terabox.app' ? 'selected' : '' ?>>www.terabox.app</option>
                                <option value="www.terabox.com" <?= getSettingValue('terabox_api_domain', 'www.terabox.app') == 'www.terabox.com' ? 'selected' : '' ?>>www.terabox.com</option>
                                <option value="www.1024tera.com" <?= getSettingValue('terabox_api_domain', 'www.terabox.app') == 'www.1024tera.com' ? 'selected' : '' ?>>www.1024tera.com</option>
                                <option value="www.1024terabox.com" <?= getSettingValue('terabox_api_domain', 'www.terabox.app') == 'www.1024terabox.com' ? 'selected' : '' ?>>www.1024terabox.com</option>
                            </select>
                            <small class="text-muted">Fallback API domain when dynamic detection is disabled</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Terabox JS Token (Optional Override)</label>
                            <textarea class="form-control font-monospace" name="terabox_js_token" rows="3" 
                                      placeholder="Leave empty to use auto-fetched token"><?= htmlspecialchars(getSettingValue('terabox_js_token', '')) ?></textarea>
                            <small class="text-muted">
                                Current token: <?php 
                                    $token = getSettingValue('terabox_js_token', '');
                                    if (!empty($token)) {
                                        echo '<span class="text-success">✓ Set (' . substr($token, 0, 20) . '...)</span>';
                                    } else {
                                        echo '<span class="text-warning">Using auto-fetched token</span>';
                                    }
                                ?><br>
                                The system automatically fetches and caches tokens. Only override if needed.
                            </small>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <strong>About Terabox Domains:</strong><br>
                            • <strong>terabox.app</strong> - Primary official domain (recommended)<br>
                            • <strong>terabox.com</strong> - Alternative official domain<br>
                            • <strong>1024tera.com</strong> - Mirror/alternative domain<br>
                            • <strong>1024terabox.com</strong> - Mirror/alternative domain<br>
                            <br>
                            With dynamic domain detection enabled, the system will automatically use the correct API for each URL.
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="mb-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                    <a href="index.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>

            <!-- Current Settings Info -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> System Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>PHP Version:</strong> <?= phpversion() ?></p>
                            <p><strong>Database:</strong> MySQL <?= $pdo->query('SELECT VERSION()')->fetchColumn() ?></p>
                            <p><strong>Server Time:</strong> <?= date('Y-m-d H:i:s') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Total Settings:</strong> <?= count($settingsArray) ?></p>
                            <p><strong>Last Stats Update:</strong> <?= getSettingValue('last_stats_update', 'Never') ?></p>
                            <p><strong>Upload Directory:</strong> <?= is_writable(UPLOAD_PATH) ? '✓ Writable' : '✗ Not Writable' ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>