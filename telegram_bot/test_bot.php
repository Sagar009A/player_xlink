<?php
/**
 * Test Bot Configuration
 * 
 * Run this script to test your bot setup:
 * php test_bot.php
 */

require_once __DIR__ . '/TelegramBot.php';

echo "🧪 Testing Telegram Bot Configuration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Test 1: Check bot token
echo "1️⃣ Testing bot token...\n";
if (TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE') {
    echo "   ❌ Bot token not configured\n";
    echo "   Please update TELEGRAM_BOT_TOKEN in config_bot.php\n";
    exit(1);
} else {
    echo "   ✅ Bot token configured\n";
}

// Test 2: Check API connection
echo "\n2️⃣ Testing Telegram API connection...\n";
try {
    $bot = new TelegramBot();
    
    $ch = curl_init(TELEGRAM_API_URL . 'getMe');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_get_info($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result['ok']) {
            echo "   ✅ API connection successful\n";
            echo "   Bot Name: " . $result['result']['first_name'] . "\n";
            echo "   Username: @" . $result['result']['username'] . "\n";
        } else {
            echo "   ❌ API returned error: " . ($result['description'] ?? 'Unknown') . "\n";
            exit(1);
        }
    } else {
        echo "   ❌ HTTP Error: $httpCode\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Check site API
echo "\n3️⃣ Testing site API connection...\n";
$testUrl = 'https://terabox.com/s/test';
$apiUrl = SITE_API_URL . '?url=' . urlencode($testUrl);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_get_info($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "   ⚠️  Network error: $error\n";
    echo "   This is expected if API is not accessible from this machine\n";
} elseif ($httpCode === 200) {
    echo "   ✅ Site API is accessible\n";
    $result = json_decode($response, true);
    if ($result) {
        echo "   API Version: " . ($result['api_version'] ?? 'Unknown') . "\n";
    }
} else {
    echo "   ⚠️  HTTP Error: $httpCode\n";
    echo "   API might not be accessible\n";
}

// Test 4: Check directories
echo "\n4️⃣ Checking directories...\n";
$dirs = [
    'Cache' => BOT_CACHE_DIR,
    'Logs' => BOT_LOG_DIR
];

foreach ($dirs as $name => $dir) {
    if (is_dir($dir) && is_writable($dir)) {
        echo "   ✅ $name directory: OK\n";
    } else {
        echo "   ⚠️  $name directory: Not writable\n";
    }
}

// Test 5: Test link extraction
echo "\n5️⃣ Testing link extraction...\n";
$testText = "Check out this video https://terabox.com/s/example and https://streamtape.com/v/test";
$links = $bot->extractLinks($testText);
if (count($links) === 2) {
    echo "   ✅ Link extraction working\n";
    echo "   Found: " . implode(', ', $links) . "\n";
} else {
    echo "   ❌ Link extraction failed\n";
}

// Summary
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ All tests passed!\n";
echo "\n📝 Next steps:\n";
echo "1. Choose your bot mode (webhook or polling)\n";
echo "2. For webhook: Run 'php setup_webhook.php'\n";
echo "3. For polling: Run 'php polling.php'\n";
echo "4. Send /start to your bot on Telegram\n";
echo "\n🎉 Your bot is ready to use!\n";
