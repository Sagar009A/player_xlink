<?php
/**
 * Webhook Setup Script
 * 
 * Run this script once to set up your webhook:
 * php setup_webhook.php
 */

require_once __DIR__ . '/TelegramBot.php';

echo "🤖 Telegram Bot Webhook Setup\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if (WEBHOOK_URL === '') {
    echo "❌ Error: WEBHOOK_URL is not set in config_bot.php\n";
    echo "Please set your webhook URL first.\n";
    exit(1);
}

try {
    $bot = new TelegramBot();
    
    echo "Setting webhook to: " . WEBHOOK_URL . "\n";
    
    $result = $bot->setWebhook(WEBHOOK_URL);
    
    if ($result['ok']) {
        echo "✅ Webhook set successfully!\n\n";
        echo "Description: " . ($result['description'] ?? 'N/A') . "\n";
        botLog("Webhook set successfully: " . WEBHOOK_URL, 'INFO');
    } else {
        echo "❌ Failed to set webhook\n";
        echo "Error: " . ($result['description'] ?? 'Unknown error') . "\n";
        botLog("Failed to set webhook: " . json_encode($result), 'ERROR');
        exit(1);
    }
    
    echo "\n🎉 Setup complete!\n";
    echo "Your bot is now ready to receive updates via webhook.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    botLog("Webhook setup error: " . $e->getMessage(), 'ERROR');
    exit(1);
}
