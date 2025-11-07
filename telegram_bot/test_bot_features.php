<?php
/**
 * Test Bot Features
 * यह script bot के सभी features को test करती है
 */

require_once __DIR__ . '/config_bot.php';
require_once __DIR__ . '/BotUserManager.php';

echo "🧪 Testing Bot Features\n";
echo str_repeat("=", 50) . "\n\n";

$userManager = new BotUserManager();
$testResults = [];

// Test 1: Database Connection
echo "Test 1: Database Connection\n";
try {
    $pdo = getBotDB();
    echo "✅ Database connection successful\n";
    $testResults['database'] = true;
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    $testResults['database'] = false;
}
echo "\n";

// Test 2: Bot Token Configuration
echo "Test 2: Bot Token Configuration\n";
if (TELEGRAM_BOT_TOKEN !== 'YOUR_BOT_TOKEN_HERE') {
    echo "✅ Bot token is configured\n";
    $testResults['bot_token'] = true;
} else {
    echo "❌ Bot token not configured\n";
    $testResults['bot_token'] = false;
}
echo "\n";

// Test 3: Check if tables exist
echo "Test 3: Database Tables\n";
try {
    $tables = ['bot_users', 'bot_sessions', 'bot_command_logs'];
    $allTablesExist = true;
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Table '$table' exists\n";
        } else {
            echo "❌ Table '$table' missing\n";
            $allTablesExist = false;
        }
    }
    
    $testResults['tables'] = $allTablesExist;
} catch (Exception $e) {
    echo "❌ Error checking tables: " . $e->getMessage() . "\n";
    $testResults['tables'] = false;
}
echo "\n";

// Test 4: Test User Registration (Mock)
echo "Test 4: User Registration (Mock Test)\n";
try {
    // Use a test telegram user ID that won't conflict
    $testTelegramId = 999999999;
    
    $result = $userManager->registerUser($testTelegramId, [
        'username' => 'test_user',
        'first_name' => 'Test',
        'last_name' => 'User'
    ]);
    
    if ($result) {
        echo "✅ User registration works\n";
        $testResults['registration'] = true;
        
        // Clean up test data
        $pdo->exec("DELETE FROM bot_users WHERE telegram_user_id = $testTelegramId");
    } else {
        echo "❌ User registration failed\n";
        $testResults['registration'] = false;
    }
} catch (Exception $e) {
    echo "❌ Registration test error: " . $e->getMessage() . "\n";
    $testResults['registration'] = false;
}
echo "\n";

// Test 5: Check if shortened_links table exists (main table)
echo "Test 5: Main Application Tables\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'shortened_links'");
    if ($stmt->rowCount() > 0) {
        echo "✅ shortened_links table exists\n";
        $testResults['main_tables'] = true;
    } else {
        echo "⚠️  shortened_links table not found (main app tables may not be installed)\n";
        $testResults['main_tables'] = false;
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    $testResults['main_tables'] = false;
}
echo "\n";

// Test 6: Site URL Configuration
echo "Test 6: Site Configuration\n";
if (SITE_URL !== 'YOUR_SITE_URL_HERE' && !empty(SITE_URL)) {
    echo "✅ Site URL configured: " . SITE_URL . "\n";
    $testResults['site_config'] = true;
} else {
    echo "❌ Site URL not configured\n";
    $testResults['site_config'] = false;
}
echo "\n";

// Test 7: Logs Directory
echo "Test 7: Logs Directory\n";
if (is_dir(__DIR__ . '/logs') && is_writable(__DIR__ . '/logs')) {
    echo "✅ Logs directory exists and is writable\n";
    $testResults['logs'] = true;
} else {
    echo "❌ Logs directory not writable\n";
    $testResults['logs'] = false;
}
echo "\n";

// Summary
echo str_repeat("=", 50) . "\n";
echo "📊 Test Summary\n";
echo str_repeat("=", 50) . "\n\n";

$passed = 0;
$failed = 0;

foreach ($testResults as $test => $result) {
    if ($result) {
        $passed++;
        echo "✅ " . ucfirst(str_replace('_', ' ', $test)) . "\n";
    } else {
        $failed++;
        echo "❌ " . ucfirst(str_replace('_', ' ', $test)) . "\n";
    }
}

echo "\n";
echo "Total Tests: " . count($testResults) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "\n";

if ($failed === 0) {
    echo "🎉 All tests passed! Bot is ready to use.\n";
    echo "\n";
    echo "Next steps:\n";
    echo "1. Make sure your bot token is configured in config_bot.php\n";
    echo "2. Run: php polling.php (to start the bot)\n";
    echo "3. Open Telegram and search for your bot\n";
    echo "4. Send /start to begin\n";
} else {
    echo "⚠️  Some tests failed. Please fix the issues above.\n";
    echo "\n";
    echo "Common fixes:\n";
    echo "- Run: php install_bot_db.php (to create tables)\n";
    echo "- Check config_bot.php for correct settings\n";
    echo "- Verify database connection in config/database.php\n";
}

echo "\n";
