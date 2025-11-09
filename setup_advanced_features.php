<?php
/**
 * Automated Setup Script for Advanced Features
 * Run this once to setup all advanced features
 * 
 * Usage: php setup_advanced_features.php
 */

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║  ADVANCED FEATURES SETUP WIZARD                          ║\n";
echo "║  Version 2.0                                             ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

require_once __DIR__ . '/config/database.php';

$errors = [];
$warnings = [];
$success = [];

// Step 1: Check PHP version
echo "[1/10] Checking PHP version... ";
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "✓ PHP " . PHP_VERSION . "\n";
    $success[] = "PHP version compatible";
} else {
    echo "✗ PHP " . PHP_VERSION . " (requires 7.4+)\n";
    $errors[] = "PHP version too old";
}

// Step 2: Check required extensions
echo "[2/10] Checking PHP extensions... ";
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl'];
$missing = [];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}
if (empty($missing)) {
    echo "✓ All required\n";
    $success[] = "All PHP extensions present";
} else {
    echo "✗ Missing: " . implode(', ', $missing) . "\n";
    $errors[] = "Missing extensions: " . implode(', ', $missing);
}

// Step 3: Check database connection
echo "[3/10] Testing database connection... ";
try {
    $pdo->query("SELECT 1");
    echo "✓ Connected\n";
    $success[] = "Database connection successful";
} catch (Exception $e) {
    echo "✗ Failed\n";
    $errors[] = "Database connection failed: " . $e->getMessage();
}

// Step 4: Check if migrations needed
echo "[4/10] Checking database schema... ";
try {
    $tables = ['blocked_ips', 'email_notifications_log', 'fraud_alerts', 'video_performance_metrics'];
    $existing = [];
    $missing = [];
    
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            $existing[] = $table;
        } else {
            $missing[] = $table;
        }
    }
    
    if (empty($missing)) {
        echo "✓ All tables exist\n";
        $success[] = "Database schema up-to-date";
    } else {
        echo "⚠ Missing tables: " . implode(', ', $missing) . "\n";
        $warnings[] = "Need to run migration: " . implode(', ', $missing);
    }
} catch (Exception $e) {
    echo "✗ Check failed\n";
    $errors[] = "Schema check failed: " . $e->getMessage();
}

// Step 5: Check new columns in users table
echo "[5/10] Checking users table columns... ";
try {
    $columns = ['email_notifications_enabled', 'daily_summary_enabled', 'weekly_summary_enabled'];
    $result = $pdo->query("DESCRIBE users");
    $existingColumns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    $missingCols = array_diff($columns, $existingColumns);
    
    if (empty($missingCols)) {
        echo "✓ All columns exist\n";
        $success[] = "Users table updated";
    } else {
        echo "⚠ Missing: " . implode(', ', $missingCols) . "\n";
        $warnings[] = "Users table needs columns: " . implode(', ', $missingCols);
    }
} catch (Exception $e) {
    echo "✗ Check failed\n";
    $errors[] = "Column check failed: " . $e->getMessage();
}

// Step 6: Check file permissions
echo "[6/10] Checking file permissions... ";
$writableDirs = ['logs', 'cache'];
$permIssues = [];
foreach ($writableDirs as $dir) {
    if (is_dir(__DIR__ . '/' . $dir)) {
        if (!is_writable(__DIR__ . '/' . $dir)) {
            $permIssues[] = $dir;
        }
    }
}
if (empty($permIssues)) {
    echo "✓ All writable\n";
    $success[] = "File permissions correct";
} else {
    echo "⚠ Not writable: " . implode(', ', $permIssues) . "\n";
    $warnings[] = "Fix permissions: chmod 755 " . implode(' ', $permIssues);
}

// Step 7: Check cron jobs
echo "[7/10] Checking cron setup... ";
exec('crontab -l 2>&1', $cronOutput, $cronReturn);
$cronLines = implode("\n", $cronOutput);
$cronFiles = ['send_daily_summaries.php', 'send_weekly_summaries.php', 'check_milestones.php', 'run_fraud_detection.php'];
$missingCron = [];
foreach ($cronFiles as $cronFile) {
    if (strpos($cronLines, $cronFile) === false) {
        $missingCron[] = $cronFile;
    }
}
if (empty($missingCron)) {
    echo "✓ Configured\n";
    $success[] = "Cron jobs configured";
} else {
    echo "⚠ Missing: " . count($missingCron) . " cron jobs\n";
    $warnings[] = "Setup cron jobs: " . implode(', ', $missingCron);
}

// Step 8: Check email configuration
echo "[8/10] Checking email configuration... ";
if (defined('EMAIL_FROM') && !empty(EMAIL_FROM)) {
    echo "✓ Configured\n";
    $success[] = "Email configured";
} else {
    echo "⚠ Not configured\n";
    $warnings[] = "Configure EMAIL_FROM in config.php";
}

// Step 9: Test email sending (optional)
echo "[9/10] Testing email functionality... ";
if (function_exists('mail')) {
    echo "✓ Mail function available\n";
    $success[] = "Mail function available";
} else {
    echo "⚠ Mail function not available\n";
    $warnings[] = "PHP mail() not available";
}

// Step 10: Verify new files exist
echo "[10/10] Verifying new files... ";
$requiredFiles = [
    'user/advanced_dashboard.php',
    'user/video_performance.php',
    'user/api_documentation.php',
    'api/realtime_stats.php',
    'includes/fraud_detection.php',
    'includes/email_notifications.php'
];
$missingFiles = [];
foreach ($requiredFiles as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $missingFiles[] = $file;
    }
}
if (empty($missingFiles)) {
    echo "✓ All files present\n";
    $success[] = "All new files exist";
} else {
    echo "✗ Missing: " . implode(', ', $missingFiles) . "\n";
    $errors[] = "Missing files: " . implode(', ', $missingFiles);
}

// Summary
echo "\n╔═══════════════════════════════════════════════════════════╗\n";
echo "║  SETUP SUMMARY                                           ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

echo "✅ Success: " . count($success) . "\n";
foreach ($success as $msg) {
    echo "   • $msg\n";
}

if (!empty($warnings)) {
    echo "\n⚠️  Warnings: " . count($warnings) . "\n";
    foreach ($warnings as $msg) {
        echo "   • $msg\n";
    }
}

if (!empty($errors)) {
    echo "\n❌ Errors: " . count($errors) . "\n";
    foreach ($errors as $msg) {
        echo "   • $msg\n";
    }
}

// Action items
echo "\n╔═══════════════════════════════════════════════════════════╗\n";
echo "║  NEXT STEPS                                              ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

if (!empty($warnings) || !empty($errors)) {
    echo "1. Run database migration:\n";
    echo "   mysql -u username -p database < database_migrations/advanced_features.sql\n\n";
    
    echo "2. Setup cron jobs:\n";
    echo "   crontab -e\n";
    echo "   (Add jobs from cron/CRON_SETUP.md)\n\n";
    
    echo "3. Configure email in config/config.php:\n";
    echo "   define('EMAIL_FROM', 'noreply@yourdomain.com');\n\n";
    
    echo "4. Set permissions:\n";
    echo "   chmod 755 logs cache\n\n";
    
    echo "5. Test features:\n";
    echo "   Visit: /user/advanced_dashboard.php\n";
    echo "   Visit: /user/api_documentation.php\n\n";
} else {
    echo "✅ All checks passed! System ready.\n\n";
    echo "Access new features:\n";
    echo "• Advanced Dashboard: /user/advanced_dashboard.php\n";
    echo "• Video Analytics: /user/video_performance.php?id=LINK_ID\n";
    echo "• API Docs: /user/api_documentation.php\n\n";
}

echo "For detailed documentation, see:\n";
echo "• ADVANCED_FEATURES_README.md\n";
echo "• IMPLEMENTATION_COMPLETE_SUMMARY.md\n";
echo "• cron/CRON_SETUP.md\n\n";

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║  Setup wizard completed at " . date('Y-m-d H:i:s') . "      ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n";

// Exit with appropriate code
exit(empty($errors) ? 0 : 1);
