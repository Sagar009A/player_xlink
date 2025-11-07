<?php
/**
 * Comprehensive TeraBox Setup Verification Script
 * Checks cronjob execution, database status, and tests extraction
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "==========================================\n";
echo "TERABOX SETUP VERIFICATION SCRIPT\n";
echo "==========================================\n\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

require_once __DIR__ . '/config/database.php';

// Color codes for terminal output
function color($text, $color = 'green') {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

// Check database connection
echo color("?", "green") . " 1. Database Connection\n";
echo "   Status: " . color("Connected", "green") . "\n\n";

// Check if settings table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo color("?", "green") . " 2. Settings Table\n";
        echo "   Status: " . color("Exists", "green") . "\n\n";
    } else {
        echo color("?", "red") . " 2. Settings Table\n";
        echo "   Status: " . color("Missing", "red") . "\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo color("?", "red") . " 2. Settings Table\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Check TeraBox token in database
echo "==========================================\n";
echo "3. TERABOX TOKEN STATUS\n";
echo "==========================================\n";

try {
    $stmt = $pdo->prepare("SELECT setting_value, updated_at FROM settings WHERE setting_key = 'terabox_js_token'");
    $stmt->execute();
    $tokenData = $stmt->fetch();
    
    if ($tokenData) {
        $token = $tokenData['setting_value'];
        $updatedAt = $tokenData['updated_at'];
        
        echo "Status: " . color("? Found", "green") . "\n";
        echo "Token Preview: " . substr($token, 0, 30) . "...\n";
        echo "Token Length: " . strlen($token) . " characters\n";
        echo "Last Updated: " . $updatedAt . "\n";
        
        // Check token age
        $lastUpdate = strtotime($updatedAt);
        $hoursSinceUpdate = (time() - $lastUpdate) / 3600;
        echo "Age: " . round($hoursSinceUpdate, 2) . " hours\n";
        
        if ($hoursSinceUpdate > 6) {
            echo "Warning: " . color("Token is older than 6 hours - may need refresh", "yellow") . "\n";
        } else {
            echo "Freshness: " . color("? Fresh (less than 6 hours)", "green") . "\n";
        }
        
        // Validate token format
        if (strlen($token) >= 64 && preg_match('/^[A-Fa-f0-9]+$/', $token)) {
            echo "Format: " . color("? Valid", "green") . "\n";
        } else {
            echo "Format: " . color("? Invalid", "red") . "\n";
        }
    } else {
        echo "Status: " . color("? Not Found", "red") . "\n";
        echo "Note: Run the fetch_terabox_token.php cronjob to fetch the token\n";
    }
} catch (Exception $e) {
    echo "Error: " . color($e->getMessage(), "red") . "\n";
}

echo "\n";

// Check last token update timestamp
echo "==========================================\n";
echo "4. TOKEN UPDATE HISTORY\n";
echo "==========================================\n";

try {
    $stmt = $pdo->prepare("SELECT setting_value, updated_at FROM settings WHERE setting_key = 'terabox_token_last_update'");
    $stmt->execute();
    $updateData = $stmt->fetch();
    
    if ($updateData) {
        $lastUpdateTimestamp = $updateData['setting_value'];
        $recordedAt = $updateData['updated_at'];
        
        echo "Last Update Timestamp: " . date('Y-m-d H:i:s', $lastUpdateTimestamp) . "\n";
        echo "Recorded At: " . $recordedAt . "\n";
        echo "Time Since Update: " . round((time() - $lastUpdateTimestamp) / 3600, 2) . " hours\n";
    } else {
        echo "Status: " . color("No update history found", "yellow") . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . color($e->getMessage(), "red") . "\n";
}

echo "\n";

// Check if logs directory exists
echo "==========================================\n";
echo "5. LOGS DIRECTORY\n";
echo "==========================================\n";

$logsDir = __DIR__ . '/logs';
if (is_dir($logsDir)) {
    echo "Status: " . color("? Exists", "green") . "\n";
    
    // Check for token log file
    $tokenLogFile = $logsDir . '/terabox_token.log';
    if (file_exists($tokenLogFile)) {
        echo "Token Log: " . color("? Found", "green") . "\n";
        echo "Log Size: " . round(filesize($tokenLogFile) / 1024, 2) . " KB\n";
        
        // Show last 5 lines of log
        $logContent = file_get_contents($tokenLogFile);
        $logLines = explode("\n", trim($logContent));
        $lastLines = array_slice($logLines, -5);
        
        echo "\nLast 5 Log Entries:\n";
        echo "-------------------\n";
        foreach ($lastLines as $line) {
            echo $line . "\n";
        }
    } else {
        echo "Token Log: " . color("Not found", "yellow") . "\n";
    }
} else {
    echo "Status: " . color("? Not Found", "yellow") . "\n";
    echo "Note: Logs directory will be created automatically when cronjobs run\n";
}

echo "\n";

// Test external token source
echo "==========================================\n";
echo "6. EXTERNAL TOKEN SOURCE TEST\n";
echo "==========================================\n";

$tokenUrl = 'https://ntmtemp.xyz/token.txt';
echo "Testing: $tokenUrl\n";

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible)');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response && $httpCode === 200) {
    $externalToken = trim($response);
    echo "Status: " . color("? Available", "green") . "\n";
    echo "Token Preview: " . substr($externalToken, 0, 30) . "...\n";
    echo "Token Length: " . strlen($externalToken) . " characters\n";
    
    if (strlen($externalToken) >= 64) {
        echo "Format: " . color("? Valid", "green") . "\n";
    } else {
        echo "Format: " . color("? Invalid", "red") . "\n";
    }
} else {
    echo "Status: " . color("? Failed", "red") . "\n";
    echo "HTTP Code: $httpCode\n";
    if ($curlError) {
        echo "Error: $curlError\n";
    }
}

echo "\n";

// Test TeraBox extraction with provided URL
echo "==========================================\n";
echo "7. TERABOX EXTRACTION TEST\n";
echo "==========================================\n";

$testUrl = 'https://teraboxurl.com/s/1TIZoUbQaiogVBF03otnqDQ';
echo "Test URL: $testUrl\n";

// Load the extractor
require_once __DIR__ . '/extractors/AbstractExtractor.php';
require_once __DIR__ . '/extractors/TeraboxExtractor.php';

try {
    $extractor = new TeraboxExtractor();
    
    // Validate URL
    if ($extractor->validateUrl($testUrl)) {
        echo "URL Validation: " . color("? Valid", "green") . "\n";
    } else {
        echo "URL Validation: " . color("? Invalid", "red") . "\n";
        exit(1);
    }
    
    echo "\nExtracting video information...\n";
    echo "This may take 10-30 seconds...\n\n";
    
    $startTime = microtime(true);
    $result = $extractor->extract($testUrl);
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    echo "Extraction Time: {$duration} seconds\n\n";
    
    if ($result['success']) {
        echo color("? EXTRACTION SUCCESSFUL!", "green") . "\n\n";
        
        $data = $result['data'];
        echo "Video Details:\n";
        echo "-------------------\n";
        echo "Title: " . $data['title'] . "\n";
        echo "Filename: " . $data['filename'] . "\n";
        echo "Size: " . $data['size_formatted'] . "\n";
        echo "Quality: " . $data['quality'] . "\n";
        echo "Platform: " . $data['platform'] . "\n";
        
        if (isset($data['direct_link']) && !empty($data['direct_link'])) {
            echo "Direct Link: " . color("? Available", "green") . "\n";
            echo "Link Preview: " . substr($data['direct_link'], 0, 80) . "...\n";
        } else {
            echo "Direct Link: " . color("? Not Available", "red") . "\n";
        }
        
        if (isset($data['thumbnail']) && !empty($data['thumbnail'])) {
            echo "Thumbnail: " . color("? Available", "green") . "\n";
        } else {
            echo "Thumbnail: " . color("? Not Available", "yellow") . "\n";
        }
        
        echo "\nExpiry Information:\n";
        echo "-------------------\n";
        echo "Expires In: " . round($data['expires_in'] / 60) . " minutes\n";
        echo "Expires At: " . $data['expires_at_formatted'] . "\n";
        
    } else {
        echo color("? EXTRACTION FAILED!", "red") . "\n\n";
        echo "Error: " . $result['error'] . "\n";
        echo "Message: " . $result['message'] . "\n";
        
        if (isset($result['rate_limit_info'])) {
            echo "\nRate Limit Information:\n";
            echo "-------------------\n";
            foreach ($result['rate_limit_info'] as $key => $value) {
                echo ucfirst(str_replace('_', ' ', $key)) . ": $value\n";
            }
        }
    }
} catch (Exception $e) {
    echo color("? EXCEPTION OCCURRED!", "red") . "\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";

// Test instant_extract.php API endpoint
echo "==========================================\n";
echo "8. INSTANT EXTRACT API TEST\n";
echo "==========================================\n";

$apiUrl = 'https://teraboxurll.in/api/instant_extract.php?url=' . urlencode($testUrl);
echo "API URL: $apiUrl\n";
echo "\nTesting API endpoint...\n";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 40);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$apiStartTime = microtime(true);
$apiResponse = curl_exec($ch);
$apiEndTime = microtime(true);
$apiDuration = round($apiEndTime - $apiStartTime, 2);
$apiHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$apiError = curl_error($ch);
curl_close($ch);

echo "Response Time: {$apiDuration} seconds\n";
echo "HTTP Code: $apiHttpCode\n";

if ($apiResponse && $apiHttpCode === 200) {
    echo "Status: " . color("? Success", "green") . "\n\n";
    
    $apiData = json_decode($apiResponse, true);
    if ($apiData) {
        echo "API Response:\n";
        echo "-------------------\n";
        echo json_encode($apiData, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Response (Raw):\n";
        echo "-------------------\n";
        echo $apiResponse . "\n";
    }
} else {
    echo "Status: " . color("? Failed", "red") . "\n";
    if ($apiError) {
        echo "Error: $apiError\n";
    }
    if ($apiResponse) {
        echo "\nResponse:\n";
        echo "-------------------\n";
        echo $apiResponse . "\n";
    }
}

echo "\n";

// Summary and recommendations
echo "==========================================\n";
echo "9. SUMMARY & RECOMMENDATIONS\n";
echo "==========================================\n\n";

// Check all components
$checks = [
    'database' => true,
    'settings_table' => $tableExists ?? false,
    'token_in_db' => isset($tokenData) && !empty($tokenData),
    'external_token' => ($response && $httpCode === 200),
    'extraction' => isset($result) && $result['success'],
    'api_endpoint' => ($apiResponse && $apiHttpCode === 200)
];

$passedChecks = count(array_filter($checks));
$totalChecks = count($checks);

echo "Checks Passed: $passedChecks / $totalChecks\n\n";

foreach ($checks as $check => $status) {
    $checkName = ucwords(str_replace('_', ' ', $check));
    if ($status) {
        echo color("?", "green") . " $checkName\n";
    } else {
        echo color("?", "red") . " $checkName\n";
    }
}

echo "\n";

if ($passedChecks === $totalChecks) {
    echo color("? ALL SYSTEMS OPERATIONAL!", "green") . "\n";
    echo "TeraBox extraction is working properly.\n";
} else {
    echo color("? ISSUES DETECTED", "yellow") . "\n\n";
    echo "Recommendations:\n";
    echo "-------------------\n";
    
    if (!$checks['token_in_db']) {
        echo "? Run cronjob: php " . __DIR__ . "/cron/fetch_terabox_token.php\n";
    }
    
    if (!$checks['external_token']) {
        echo "? External token source is unavailable - using fallback token\n";
    }
    
    if (!$checks['extraction']) {
        echo "? Check TeraBox token validity and API connectivity\n";
        echo "? Token may need to be refreshed\n";
        echo "? TeraBox may be blocking requests (CAPTCHA/rate limit)\n";
    }
    
    if (!$checks['api_endpoint']) {
        echo "? API endpoint may need configuration\n";
        echo "? Check web server configuration\n";
    }
}

echo "\n";
echo "==========================================\n";
echo "VERIFICATION COMPLETE\n";
echo "==========================================\n";
?>