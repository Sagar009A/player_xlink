<?php
/**
 * Telegram Bot Polling Script
 * 
 * Run this script to start the bot in polling mode:
 * php polling.php
 * 
 * For continuous operation, use:
 * nohup php polling.php > /dev/null 2>&1 &
 * 
 * Or create a systemd service (recommended)
 */

require_once __DIR__ . '/TelegramBot.php';

// Increase execution time
set_time_limit(0);
ini_set('max_execution_time', 0);

echo "🤖 Starting Telegram Bot in polling mode...\n";
echo "⏳ Press Ctrl+C to stop\n\n";

botLog("Bot started in polling mode", 'INFO');

try {
    $bot = new TelegramBot();
    
    // Delete webhook if exists
    $bot->deleteWebhook();
    echo "✅ Webhook cleared\n";
    
    $offset = 0;
    
    while (true) {
        try {
            // Get updates
            $response = $bot->getUpdates($offset);
            
            if (!$response['ok']) {
                botLog("Failed to get updates: " . json_encode($response), 'ERROR');
                sleep(3);
                continue;
            }
            
            $updates = $response['result'];
            
            if (empty($updates)) {
                // No new updates
                sleep(1);
                continue;
            }
            
            // Process each update
            foreach ($updates as $update) {
                try {
                    echo "📨 Processing update ID: " . $update['update_id'] . "\n";
                    $bot->handleUpdate($update);
                    
                    // Update offset
                    $offset = $update['update_id'] + 1;
                    
                } catch (Exception $e) {
                    botLog("Error processing update: " . $e->getMessage(), 'ERROR');
                    echo "❌ Error: " . $e->getMessage() . "\n";
                }
            }
            
            // Small delay between batches
            usleep(100000); // 0.1 second
            
        } catch (Exception $e) {
            botLog("Polling error: " . $e->getMessage(), 'ERROR');
            echo "❌ Polling error: " . $e->getMessage() . "\n";
            sleep(5);
        }
    }
    
} catch (Exception $e) {
    botLog("Fatal error: " . $e->getMessage(), 'ERROR');
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
