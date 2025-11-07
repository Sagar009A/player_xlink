<?php
/**
 * Comprehensive Feature Testing Script
 * Tests all major features of the URL shortener system
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/extractors.php';

// Check if running from CLI or browser
$isCli = php_sapi_name() === 'cli';

// Test results tracking
$testResults = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
    'tests' => []
];

// Helper function to output
function output($text, $type = 'info') {
    global $isCli;
    
    if ($isCli) {
        $colors = [
            'success' => "\033[32m",
            'error' => "\033[31m",
            'warning' => "\033[33m",
            'info' => "\033[36m",
            'reset' => "\033[0m"
        ];
        $color = $colors[$type] ?? $colors['info'];
        echo $color . $text . $colors['reset'] . "\n";
    } else {
        $classes = [
            'success' => 'success',
            'error' => 'error',
            'warning' => 'warning',
            'info' => 'info'
        ];
        $class = $classes[$type] ?? 'info';
        echo "<span class='$class'>" . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "</span><br>";
    }
}

// Helper function to run test
function runTest($name, $callback) {
    global $testResults;
    
    $testResults['total']++;
    output("", 'info');
    output("Testing: $name", 'info');
    output(str_repeat("-", 60), 'info');
    
    try {
        $result = $callback();
        
        if ($result['success']) {
            $testResults['passed']++;
            output("? PASSED: " . $result['message'], 'success');
        } else {
            if (isset($result['warning']) && $result['warning']) {
                $testResults['warnings']++;
                output("? WARNING: " . $result['message'], 'warning');
            } else {
                $testResults['failed']++;
                output("? FAILED: " . $result['message'], 'error');
            }
        }
        
        if (isset($result['details'])) {
            foreach ($result['details'] as $detail) {
                output("  ? " . $detail, 'info');
            }
        }
        
        $testResults['tests'][] = [
            'name' => $name,
            'success' => $result['success'],
            'message' => $result['message']
        ];
        
        return $result;
        
    } catch (Exception $e) {
        $testResults['failed']++;
        output("? EXCEPTION: " . $e->getMessage(), 'error');
        
        $testResults['tests'][] = [
            'name' => $name,
            'success' => false,
            'message' => $e->getMessage()
        ];
        
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// HTML Header (for browser)
if (!$isCli) {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprehensive Feature Test</title>
    <style>
        body { font-family: "Courier New", monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; max-width: 1200px; margin: 0 auto; }
        .success { color: #4ec9b0; font-weight: bold; }
        .error { color: #f48771; font-weight: bold; }
        .warning { color: #dcdcaa; font-weight: bold; }
        .info { color: #569cd6; }
        .header { color: #dcdcaa; font-weight: bold; font-size: 24px; text-align: center; margin: 20px 0; }
        .section { margin: 20px 0; padding: 20px; background: #252526; border-left: 5px solid #007acc; border-radius: 5px; }
        .summary { background: #2d2d30; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .summary-item { display: inline-block; margin: 10px 20px; }
        pre { background: #1e1e1e; padding: 10px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #3e3e42; }
        th { background: #2d2d30; color: #dcdcaa; }
    </style>
</head>
<body>';
    echo '<div class="header">?? COMPREHENSIVE FEATURE TEST SUITE</div>';
}

output("==========================================", 'info');
output("  COMPREHENSIVE FEATURE TEST SUITE", 'info');
output("==========================================", 'info');
output("Started at: " . date('Y-m-d H:i:s'), 'info');
output("", 'info');

// ============================================================
// TEST 1: Database Connection
// ============================================================
if (!$isCli) echo '<div class="section">';
runTest("Database Connection", function() use ($pdo) {
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection is null'];
    }
    
    // Try a simple query
    $stmt = $pdo->query("SELECT 1");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Cannot execute test query'];
    }
    
    return [
        'success' => true,
        'message' => 'Database connected successfully',
        'details' => ['Connection is active and working']
    ];
});
if (!$isCli) echo '</div>';

// ============================================================
// TEST 2: Required Tables
// ============================================================
if (!$isCli) echo '<div class="section">';
runTest("Database Tables Check", function() use ($pdo) {
    $requiredTables = ['users', 'links', 'settings', 'withdrawals', 'folders'];
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            $missingTables[] = $table;
        }
    }
    
    if (!empty($missingTables)) {
        return [
            'success' => false,
            'message' => 'Missing tables: ' . implode(', ', $missingTables),
            'details' => ['Run database.sql to create missing tables']
        ];
    }
    
    return [
        'success' => true,
        'message' => 'All required tables exist',
        'details' => ['Tables: ' . implode(', ', $requiredTables)]
    ];
});
if (!$isCli) echo '</div>';

// ============================================================
// TEST 3: Configuration Files
// ============================================================
if (!$isCli) echo '<div class="section">';
runTest("Configuration Files", function() {
    $configFiles = [
        'config/database.php',
        'config/config.php',
        'config/extractors.php',
        'config/currencies.php'
    ];
    
    $missing = [];
    foreach ($configFiles as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $missing[] = $file;
        }
    }
    
    if (!empty($missing)) {
        return [
            'success' => false,
            'message' => 'Missing config files',
            'details' => $missing
        ];
    }
    
    // Check if constants are defined
    $requiredConstants = ['SITE_URL', 'SITE_NAME', 'DB_HOST'];
    $missingConstants = [];
    
    foreach ($requiredConstants as $const) {
        if (!defined($const)) {
            $missingConstants[] = $const;
        }
    }
    
    if (!empty($missingConstants)) {
        return [
            'success' => false,
            'warning' => true,
            'message' => 'Missing constants: ' . implode(', ', $missingConstants),
            'details' => ['Check config.php for proper configuration']
        ];
    }
    
    return [
        'success' => true,
        'message' => 'All configuration files present',
        'details' => [
            'Site URL: ' . SITE_URL,
            'Site Name: ' . SITE_NAME
        ]
    ];
});
if (!$isCli) echo '</div>';

// ============================================================
// TEST 4: Extractors System
// ============================================================
if (!$isCli) echo '<div class="section">';
runTest("Video Extractors System", function() {
    $extractorFiles = [
        'extractors/AbstractExtractor.php',
        'extractors/TeraboxExtractor.php',
        'extractors/DiskwalaExtractor.php',
        'extractors/StreamTapeExtractor.php',
        'extractors/StreamNetExtractor.php',
        'extractors/NowPlayTocExtractor.php',
        'extractors/VividCastExtractor.php',
        'services/ExtractorManager.php'
    ];
    
    $missing = [];
    foreach ($extractorFiles as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $missing[] = $file;
        }
    }
    
    if (!empty($missing)) {
        return [
            'success' => false,
            'message' => 'Missing extractor files',
            'details' => $missing
        ];
    }
    
    // Try to load ExtractorManager
    require_once __DIR__ . '/extractors/AbstractExtractor.php';
    require_once __DIR__ . '/services/ExtractorManager.php';
    
    $manager = new ExtractorManager();
    $platforms = $manager->getSupportedPlatforms();
    
    $enabledCount = count(array_filter($platforms, function($p) { return $p['enabled']; }));
    
    return [
        'success' => true,
        'message' => "ExtractorManager loaded successfully",
        'details' => [
            "Total platforms: " . count($platforms),
            "Enabled platforms: $enabledCount",
            "Platforms: " . implode(', ', array_column($platforms, 'name'))
        ]
    ];
});
if (!$isCli) echo '</div>';

// ============================================================
// TEST 5: API Endpoints
// ============================================================
if (!$isCli) echo '<div class="section">';
runTest("API Endpoints", function() {
    $apiFiles = [
        'api/extract.php',
        'api/instant_extract.php',
        'api/links.php',
        'api/stats.php',
        'api/auth.php'
    ];
    
    $missing = [];
    foreach ($apiFiles as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $missing[] = $file;
        }
    }
    
    if (!empty($missing)) {
        return [
            'success' => false,
            'warning' => true,
            'message' => 'Some API files missing',
            'details' => $missing
        ];
    }
    
    return [
        'success' => true,
        'message' => 'All API endpoints present',
        'details' => array_map(function($f) { return basename($f); }, $apiFiles)
    ];
});
if (!$isCli) echo '</div>';

// ============================================================
// TEST 6: User Pages
// ============================================================
if (!$isCli) echo '<div class="section">';
runTest("User Dashboard Pages", function() {
    $userPages = [
        'user/dashboard.php',
        'user/links.php',
        'user/earnings.php',
        'user/withdraw.php',
        'user/profile.php',
        'user/login.php',
        'user/register.php'
    ];
    
    $missing = [];
    foreach ($userPages as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $missing[] = $file;
        }
    }
    
    if (!empty($missing)) {
        return [
            'success' => false,
            'message' => 'Missing user pages',
            'details' => $missing
        ];
    }
    
    return [
        'success' => true,
        'message' => 'All user pages present',
        'details' => ['Total pages: ' . count($userPages)]
    ];
});
if (!$isCli) echo '</div>';

// ============================================================
// TEST 7: Admin Pages
// ============================================================
if (!$isCli) echo '<div class="section">';
runTest("Admin Dashboard Pages", function() {
    $adminPages = [
        'admin/index.php',
        'admin/users.php',
        'admin/settings.php',
        'admin/withdrawals.php',
        'admin/analytics.php',
        'admin/login.php'
    ];
    
    $missing = [];
    foreach ($adminPages as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $missing[] = $file;
        }
    }
    
    if (!empty($missing)) {
        return [
            'success' => false,
            'message' => 'Missing admin pages',
            'details' => $missing
        ];
    }
    
    return [
        'success' => true,
        'message' => 'All admin pages present',
        'details' => ['Total pages: ' . count($adminPages)]
    ];
});
if (!$isCli) echo '</div>';

// ============================================================
// TEST 8: File Permissions
// ============================================================
if (!$isCli) echo '<div class="section">';
runTest("File Permissions", function() {
    $writeableDirs = [
        'uploads',
        'cache',
        'logs'
    ];
    
    $issues = [];
    foreach ($writeableDirs as $dir) {
        $path = __DIR__ . '/' . $dir;
        
        if (!file_exists($path)) {
            // Try to create it
            if (!@mkdir($path, 0755, true)) {
                $issues[] = "$dir - does not exist and cannot be created";
            }
        } elseif (!is_writable($path)) {
            $issues[] = "$dir - not writable";
        }
    }
    
    if (!empty($issues)) {
        return [
            'success' => false,
            'warning' => true,
            'message' => 'Some directories have permission issues',
            'details' => $issues
        ];
    }
    
    return [
        'success' => true,
        'message' => 'All required directories are writable',
        'details' => array_map(function($d) { return "$d - OK"; }, $writeableDirs)
    ];
});
if (!$isCli) echo '</div>';

// ============================================================
// TEST 9: Settings Check
// ============================================================
if (!$isCli) echo '<div class="section">';
runTest("System Settings", function() use ($pdo) {
    $requiredSettings = [
        'site_name',
        'cpm_rate',
        'min_withdrawal',
        'currency_code',
        'admin_email'
    ];
    
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('" . implode("','", $requiredSettings) . "')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $missing = array_diff($requiredSettings, array_keys($settings));
    
    if (!empty($missing)) {
        return [
            'success' => false,
            'warning' => true,
            'message' => 'Some settings are missing',
            'details' => array_merge(
                ['Missing: ' . implode(', ', $missing)],
                ['Found: ' . count($settings) . ' settings']
            )
        ];
    }
    
    $details = [];
    foreach ($settings as $key => $value) {
        if (strlen($value) > 50) {
            $value = substr($value, 0, 50) . '...';
        }
        $details[] = "$key = $value";
    }
    
    return [
        'success' => true,
        'message' => 'All core settings configured',
        'details' => $details
    ];
});
if (!$isCli) echo '</div>';

// ============================================================
// TEST 10: URL Shortening Test (Functional Test)
// ============================================================
if (!$isCli) echo '<div class="section">';
runTest("URL Shortening Function", function() use ($pdo) {
    require_once __DIR__ . '/includes/functions.php';
    
    // Test generateShortCode function
    if (!function_exists('generateShortCode')) {
        return [
            'success' => false,
            'message' => 'generateShortCode function not found',
            'details' => ['Check includes/functions.php']
        ];
    }
    
    $shortCode = generateShortCode();
    
    if (empty($shortCode) || strlen($shortCode) < 6) {
        return [
            'success' => false,
            'message' => 'Invalid short code generated: ' . $shortCode
        ];
    }
    
    // Check if short code is unique
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE short_code = ?");
    $stmt->execute([$shortCode]);
    $exists = $stmt->fetchColumn();
    
    return [
        'success' => true,
        'message' => 'URL shortening function works correctly',
        'details' => [
            "Generated code: $shortCode",
            "Code length: " . strlen($shortCode),
            "Unique: " . ($exists ? 'No (collision)' : 'Yes')
        ]
    ];
});
if (!$isCli) echo '</div>';

// ============================================================
// TEST SUMMARY
// ============================================================
if (!$isCli) echo '<div class="summary">';
output("", 'info');
output("==========================================", 'info');
output("           TEST SUMMARY", 'info');
output("==========================================", 'info');
output("", 'info');

$passRate = $testResults['total'] > 0 ? round(($testResults['passed'] / $testResults['total']) * 100, 2) : 0;

output("Total Tests: " . $testResults['total'], 'info');
output("Passed: " . $testResults['passed'], 'success');
output("Failed: " . $testResults['failed'], 'error');
output("Warnings: " . $testResults['warnings'], 'warning');
output("Pass Rate: " . $passRate . "%", $passRate >= 80 ? 'success' : 'warning');
output("", 'info');

if ($testResults['failed'] > 0) {
    output("? Some tests failed. Please review the errors above.", 'warning');
} elseif ($testResults['warnings'] > 0) {
    output("? All tests passed but with warnings. Review warnings above.", 'warning');
} else {
    output("? All tests passed successfully! System is healthy.", 'success');
}

output("", 'info');
output("Completed at: " . date('Y-m-d H:i:s'), 'info');
output("==========================================", 'info');

if (!$isCli) {
    echo '</div>';
    
    // Generate detailed table
    echo '<div class="section">';
    echo '<h3 style="color: #dcdcaa;">Detailed Test Results</h3>';
    echo '<table>';
    echo '<thead><tr><th>#</th><th>Test Name</th><th>Status</th><th>Message</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($testResults['tests'] as $index => $test) {
        $statusClass = $test['success'] ? 'success' : 'error';
        $statusIcon = $test['success'] ? '?' : '?';
        $num = $index + 1;
        
        echo "<tr>";
        echo "<td>$num</td>";
        echo "<td>{$test['name']}</td>";
        echo "<td class='$statusClass'>$statusIcon " . ($test['success'] ? 'PASS' : 'FAIL') . "</td>";
        echo "<td>" . htmlspecialchars($test['message']) . "</td>";
        echo "</tr>";
    }
    
    echo '</tbody></table>';
    echo '</div>';
    
    echo '</body></html>';
}
?>