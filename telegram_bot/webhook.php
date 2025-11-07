<?php
/**
 * Telegram Bot Webhook Handler
 * 
 * Setup:
 * 1. Upload this file to your server
 * 2. Set webhook URL: https://your-domain.com/telegram_bot/webhook.php
 * 3. Use setup_webhook.php to register the webhook
 */

require_once __DIR__ . '/TelegramBot.php';

// Verify it's from Telegram (optional but recommended)
$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!$update) {
    http_response_code(400);
    exit('Invalid request');
}

// Log incoming update
botLog("Webhook received: " . json_encode($update), 'INFO');

// Process update
try {
    $bot = new TelegramBot();
    $bot->handleUpdate($update);
    
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    botLog("Webhook error: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo 'Error';
}
