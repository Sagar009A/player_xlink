<?php
/**
 * Test 1024tera.com API Fix
 * Tests if the dynamic domain detection is working
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/extractors/TeraboxExtractor.php';

echo "<h1>Testing 1024tera.com API Fix</h1>";
echo "<style>body{font-family:Arial;padding:20px}.success{color:green;background:#d4edda;padding:10px;margin:10px 0;border-radius:5px}.error{color:#721c24;background:#f8d7da;padding:10px;margin:10px 0;border-radius:5px}.info{background:#d1ecf1;padding:10px;margin:10px 0;border-radius:5px}pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow-x:auto}</style>";

// Test URLs
$testUrls = [
    'https://1024tera.com/s/16y9PvRU-Kx5LEb83Yh6iAg',
    'https://www.1024tera.com/s/16y9PvRU-Kx5LEb83Yh6iAg',
    'https://1024terabox.com/s/16y9PvRU-Kx5LEb83Yh6iAg',
    'https://terabox.app/s/16y9PvRU-Kx5LEb83Yh6iAg',
    'https://www.terabox.com/s/16y9PvRU-Kx5LEb83Yh6iAg'
];

echo "<h2>Testing URL Domain Detection</h2>";

foreach ($testUrls as $testUrl) {
    echo "<div class='info'>";
    echo "<strong>Testing URL:</strong> " . htmlspecialchars($testUrl) . "<br>";
    
    try {
        $extractor = new TeraboxExtractor();
        
        // Test validation
        $isValid = $extractor->validateUrl($testUrl);
        echo "<strong>Valid URL:</strong> " . ($isValid ? "✓ Yes" : "✗ No") . "<br>";
        
        if ($isValid) {
            // Test extraction (will show which domain is being used in logs)
            $result = $extractor->extract($testUrl);
            
            if ($result['success']) {
                echo "<div class='success'>";
                echo "✓ <strong>Extraction Successful!</strong><br>";
                echo "Title: " . htmlspecialchars($result['data']['title'] ?? 'N/A') . "<br>";
                echo "Size: " . htmlspecialchars($result['data']['size_formatted'] ?? 'N/A') . "<br>";
                echo "Quality: " . htmlspecialchars($result['data']['quality'] ?? 'N/A') . "<br>";
                echo "Expires: " . htmlspecialchars($result['data']['expires_at_formatted'] ?? 'N/A') . "<br>";
                echo "</div>";
            } else {
                echo "<div class='error'>";
                echo "✗ <strong>Extraction Failed</strong><br>";
                echo "Error: " . htmlspecialchars($result['error'] ?? 'unknown') . "<br>";
                echo "Message: " . htmlspecialchars($result['message'] ?? 'No message') . "<br>";
                echo "</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "✗ Exception: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
    
    echo "</div><hr>";
}

echo "<h2>Check Extractor Logs</h2>";
echo "<p>Check the latest extractor log file for detailed API call information:</p>";
$logFile = __DIR__ . '/logs/extractor_' . date('Y-m-d') . '.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lastLines = array_slice(explode("\n", $logContent), -30);
    echo "<pre>" . htmlspecialchars(implode("\n", $lastLines)) . "</pre>";
} else {
    echo "<p class='info'>Log file not found: $logFile</p>";
}

echo "<h2>Summary</h2>";
echo "<div class='info'>";
echo "<p><strong>Changes Made:</strong></p>";
echo "<ul>";
echo "<li>✓ Added dynamic domain detection to TeraboxExtractor</li>";
echo "<li>✓ API calls now use the domain from the input URL (1024tera.com URLs will use 1024tera.com API)</li>";
echo "<li>✓ Host and Referer headers are now dynamic based on input domain</li>";
echo "<li>✓ Updated terabox_helper.php with the same logic</li>";
echo "</ul>";
echo "<p><strong>Domain Mapping:</strong></p>";
echo "<ul>";
echo "<li>1024tera.com → www.1024tera.com</li>";
echo "<li>1024terabox.com → www.1024terabox.com</li>";
echo "<li>terabox.com → www.terabox.com</li>";
echo "<li>terabox.app → www.terabox.app</li>";
echo "<li>Other TeraBox domains → www.terabox.app (fallback)</li>";
echo "</ul>";
echo "</div>";
?>
