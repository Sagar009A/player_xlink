<?php
/**
 * ONE-CLICK CRON JOB SETUP SCRIPT
 * 
 * This script helps you set up all required cron jobs in one click.
 * 
 * USAGE:
 * 1. Visit this page in your browser: https://yourdomain.com/setup_cron_jobs.php
 * 2. Copy the generated cron commands
 * 3. Add them to your cPanel or use the "Add All to cPanel" feature
 * 
 * SECURITY: Delete this file after setup for security!
 */

// Security check - change this password
define('SETUP_PASSWORD', 'terabox2024');

session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cron Job Setup - LinkStreamX</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .cron-item {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
        }
        
        .cron-item h3 {
            color: #333;
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .cron-item p {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .cron-command {
            background: #2d3748;
            color: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin-top: 10px;
            white-space: pre;
            position: relative;
        }
        
        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
            transition: all 0.3s;
        }
        
        .copy-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .copy-btn:active {
            transform: translateY(0);
        }
        
        .success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        
        .warning {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        
        .danger {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        
        .info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin: 10px 5px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .password-form {
            text-align: center;
            padding: 40px;
        }
        
        .password-input {
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            width: 300px;
            margin: 20px 0;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-box h4 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .stat-box p {
            font-size: 24px;
            font-weight: bold;
        }
        
        .step {
            display: flex;
            align-items: start;
            margin: 20px 0;
        }
        
        .step-number {
            background: #667eea;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
            margin-right: 15px;
        }
        
        .step-content {
            flex: 1;
        }
        
        .step-content h4 {
            margin-bottom: 5px;
            color: #333;
        }
        
        .step-content p {
            color: #666;
            line-height: 1.6;
        }
        
        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            color: #d63384;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>

<div class="container">
    <?php
    // Password check
    $authenticated = isset($_SESSION['cron_setup_authenticated']) && $_SESSION['cron_setup_authenticated'] === true;
    
    if (isset($_POST['password'])) {
        if ($_POST['password'] === SETUP_PASSWORD) {
            $_SESSION['cron_setup_authenticated'] = true;
            $authenticated = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = 'Invalid password!';
        }
    }
    
    if (!$authenticated): ?>
        <div class="card password-form">
            <h1>?? Cron Job Setup</h1>
            <p class="subtitle">Enter password to continue</p>
            
            <?php if (isset($error)): ?>
                <div class="info danger">? <?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div>
                    <input type="password" name="password" class="password-input" 
                           placeholder="Enter setup password" required autofocus>
                </div>
                <button type="submit" class="btn-primary">Unlock Setup</button>
            </form>
            
            <div class="info" style="margin-top: 30px; text-align: left;">
                <strong>Default Password:</strong> <code>terabox2024</code><br>
                <small>Change this in the script for security!</small>
            </div>
        </div>
    <?php else: 
        // Get server information
        $serverPath = dirname(__FILE__);
        $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}";
        
        // Cron jobs configuration
        $cronJobs = [
            [
                'name' => 'Update Currency Rates',
                'description' => 'Updates currency exchange rates from API. Keeps your earnings calculations accurate for international users.',
                'schedule' => 'Once daily at 2:00 AM',
                'cron_time' => '0 2 * * *',
                'file' => 'cron/update_currency.php',
                'priority' => 'Medium',
                'badge' => 'warning'
            ],
            [
                'name' => 'Update Statistics',
                'description' => 'Updates global statistics, analytics data, and generates reports. Essential for dashboard accuracy.',
                'schedule' => 'Every 5 minutes',
                'cron_time' => '*/5 * * * *',
                'file' => 'cron/update_stats.php',
                'priority' => 'High',
                'badge' => 'danger'
            ],
            [
                'name' => 'Reset Daily Views',
                'description' => 'Resets daily view counters for rate limiting. Runs at midnight to start fresh tracking each day.',
                'schedule' => 'Daily at 12:00 AM',
                'cron_time' => '0 0 * * *',
                'file' => 'cron/reset_daily_views.php',
                'priority' => 'High',
                'badge' => 'danger'
            ],
            [
                'name' => 'Fetch TeraBox Token',
                'description' => 'Refreshes TeraBox authentication tokens. CRITICAL for video extraction to work properly!',
                'schedule' => 'Every 30 minutes',
                'cron_time' => '*/30 * * * *',
                'file' => 'cron/fetch_terabox_token.php',
                'priority' => 'Critical',
                'badge' => 'danger'
            ],
            [
                'name' => 'Refresh Expired Videos',
                'description' => 'Updates expired video links automatically. Keeps your short links working even after video URLs expire.',
                'schedule' => 'Every 2 hours',
                'cron_time' => '0 */2 * * *',
                'file' => 'cron/refresh_expired_videos.php',
                'priority' => 'High',
                'badge' => 'danger'
            ]
        ];
    ?>
    
    <div class="card">
        <h1>?? Cron Job Setup</h1>
        <p class="subtitle">Set up all required cron jobs in one go!</p>
        
        <div class="info success">
            <strong>? Ready to Configure!</strong><br>
            Follow the steps below to set up automated tasks for your system.
        </div>
        
        <div class="stats">
            <div class="stat-box">
                <h4>Total Cron Jobs</h4>
                <p><?= count($cronJobs) ?></p>
            </div>
            <div class="stat-box">
                <h4>Critical Tasks</h4>
                <p><?= count(array_filter($cronJobs, fn($j) => $j['priority'] === 'Critical')) ?></p>
            </div>
            <div class="stat-box">
                <h4>Setup Time</h4>
                <p>5 mins</p>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h2>?? Cron Jobs to Setup</h2>
        <p class="subtitle">Copy and add these commands to your cPanel Cron Jobs</p>
        
        <?php foreach ($cronJobs as $index => $job): ?>
            <div class="cron-item">
                <h3>
                    <?= ($index + 1) ?>. <?= $job['name'] ?>
                    <span class="badge badge-<?= $job['badge'] ?>"><?= $job['priority'] ?></span>
                </h3>
                <p><strong>Schedule:</strong> <?= $job['schedule'] ?></p>
                <p><?= $job['description'] ?></p>
                
                <div class="cron-command"><?= $job['cron_time'] ?> /usr/local/bin/php <?= $serverPath ?>/<?= $job['file'] ?> > /dev/null 2>&1</div>
                
                <button class="copy-btn" onclick="copyCommand(this, '<?= $job['cron_time'] ?> /usr/local/bin/php <?= $serverPath ?>/<?= $job['file'] ?> > /dev/null 2>&1')">
                    ?? Copy Command
                </button>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="card">
        <h2>?? How to Add to cPanel</h2>
        
        <div class="step">
            <div class="step-number">1</div>
            <div class="step-content">
                <h4>Login to cPanel</h4>
                <p>Access your hosting cPanel dashboard</p>
            </div>
        </div>
        
        <div class="step">
            <div class="step-number">2</div>
            <div class="step-content">
                <h4>Find Cron Jobs</h4>
                <p>Search for "Cron Jobs" in the search bar or find it under "Advanced" section</p>
            </div>
        </div>
        
        <div class="step">
            <div class="step-number">3</div>
            <div class="step-content">
                <h4>Add Each Cron Job</h4>
                <p>For each cron job above:</p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li>Click the "Copy Command" button</li>
                    <li>In cPanel, scroll to "Add New Cron Job"</li>
                    <li>Select "Common Settings" or enter time manually</li>
                    <li>Paste the full command in the "Command" field</li>
                    <li>Click "Add New Cron Job"</li>
                </ul>
            </div>
        </div>
        
        <div class="step">
            <div class="step-number">4</div>
            <div class="step-content">
                <h4>Verify Setup</h4>
                <p>After adding all jobs, you should see 5 cron jobs listed in your cPanel. Check the execution logs after a few hours to ensure they're running properly.</p>
            </div>
        </div>
        
        <div class="step">
            <div class="step-number">5</div>
            <div class="step-content">
                <h4>Delete This File</h4>
                <p><strong style="color: #dc3545;">IMPORTANT:</strong> Delete <code>setup_cron_jobs.php</code> from your server after setup for security!</p>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h2>?? Alternative: Manual Time Entry</h2>
        <p class="subtitle">If your cPanel doesn't have Common Settings</p>
        
        <div class="info">
            <strong>Cron Time Format:</strong> <code>minute hour day month weekday</code>
            
            <table style="width: 100%; margin-top: 15px; border-collapse: collapse;">
                <tr style="background: #f8f9fa;">
                    <th style="padding: 10px; text-align: left;">Field</th>
                    <th style="padding: 10px; text-align: left;">Values</th>
                    <th style="padding: 10px; text-align: left;">Special</th>
                </tr>
                <tr>
                    <td style="padding: 10px;">Minute</td>
                    <td style="padding: 10px;">0-59</td>
                    <td style="padding: 10px;"><code>*/5</code> = every 5 minutes</td>
                </tr>
                <tr style="background: #f8f9fa;">
                    <td style="padding: 10px;">Hour</td>
                    <td style="padding: 10px;">0-23</td>
                    <td style="padding: 10px;"><code>*/2</code> = every 2 hours</td>
                </tr>
                <tr>
                    <td style="padding: 10px;">Day</td>
                    <td style="padding: 10px;">1-31</td>
                    <td style="padding: 10px;"><code>*</code> = every day</td>
                </tr>
                <tr style="background: #f8f9fa;">
                    <td style="padding: 10px;">Month</td>
                    <td style="padding: 10px;">1-12</td>
                    <td style="padding: 10px;"><code>*</code> = every month</td>
                </tr>
                <tr>
                    <td style="padding: 10px;">Weekday</td>
                    <td style="padding: 10px;">0-6 (0=Sunday)</td>
                    <td style="padding: 10px;"><code>*</code> = every day</td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="card">
        <h2>? Troubleshooting</h2>
        
        <div class="cron-item warning">
            <h3>Cron Job Not Running?</h3>
            <p><strong>Solution 1:</strong> Check PHP path</p>
            <p>Your PHP path might be different. Try these alternatives:</p>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li><code>/usr/bin/php</code></li>
                <li><code>/usr/local/bin/php</code></li>
                <li><code>/opt/cpanel/ea-php80/root/usr/bin/php</code> (replace 80 with your PHP version)</li>
            </ul>
            <p style="margin-top: 10px;"><strong>To find your PHP path:</strong> Create a PHP file with <code>&lt;?php echo PHP_BINARY; ?&gt;</code> and visit it in browser.</p>
        </div>
        
        <div class="cron-item warning">
            <h3>Permission Denied Error?</h3>
            <p>Make sure all cron files have execute permissions:</p>
            <div class="cron-command">chmod +x <?= $serverPath ?>/cron/*.php</div>
        </div>
        
        <div class="cron-item warning">
            <h3>Cron Execution Failed?</h3>
            <p>Check cron logs in cPanel or contact your hosting support for assistance.</p>
        </div>
    </div>
    
    <div class="card">
        <h2>?? All Done?</h2>
        <div class="info success">
            <strong>Next Steps:</strong>
            <ol style="margin-left: 20px; margin-top: 10px; line-height: 2;">
                <li>Wait 5-30 minutes for cron jobs to run</li>
                <li>Check your admin dashboard for updated statistics</li>
                <li>Test TeraBox link extraction - it should work smoothly!</li>
                <li><strong style="color: #dc3545;">DELETE this file (setup_cron_jobs.php) for security</strong></li>
            </ol>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="/" class="btn-primary">Go to Homepage</a>
            <a href="/admin" class="btn-primary">Go to Admin Panel</a>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<script>
function copyCommand(button, text) {
    // Create temporary textarea
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    
    // Select and copy
    textarea.select();
    document.execCommand('copy');
    
    // Remove textarea
    document.body.removeChild(textarea);
    
    // Update button
    const originalText = button.innerHTML;
    button.innerHTML = '? Copied!';
    button.style.background = '#28a745';
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.style.background = '#667eea';
    }, 2000);
}
</script>

</body>
</html>