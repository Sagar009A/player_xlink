<?php
/**
 * Bot Database Installer
 * Run this file once to create bot-related tables
 */

require_once __DIR__ . '/config_bot.php';

echo "🤖 Installing Telegram Bot Database Tables...\n\n";

try {
    $pdo = getBotDB();
    
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/bot_database.sql');
    
    // Split queries
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    $success = 0;
    $errors = 0;
    
    foreach ($queries as $query) {
        if (empty($query) || strpos($query, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($query);
            $success++;
            echo "✅ Query executed successfully\n";
        } catch (PDOException $e) {
            $errors++;
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Installation Complete!\n";
    echo "✅ Successful: $success\n";
    echo "❌ Errors: $errors\n";
    echo str_repeat("=", 50) . "\n\n";
    
    if ($errors === 0) {
        echo "🎉 All tables created successfully!\n";
        echo "You can now start using the Telegram bot.\n\n";
        echo "Next steps:\n";
        echo "1. Update TELEGRAM_BOT_TOKEN in config_bot.php\n";
        echo "2. Run: php polling.php (for polling mode)\n";
        echo "   OR setup webhook using setup_webhook.php\n";
    }
    
} catch (Exception $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}
