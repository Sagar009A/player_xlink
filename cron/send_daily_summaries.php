<?php
/**
 * Send Daily Summary Emails to All Users
 * Run this daily at 00:30 AM
 * Cron: 30 0 * * * php /path/to/cron/send_daily_summaries.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email_notifications.php';

echo "=== Starting Daily Summary Email Sender ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Get all users who want daily summaries
    $stmt = $pdo->query("
        SELECT id, username, email 
        FROM users 
        WHERE status = 'approved' 
        AND email_notifications_enabled = 1 
        AND daily_summary_enabled = 1
        AND email IS NOT NULL AND email != ''
    ");
    
    $users = $stmt->fetchAll();
    $sent = 0;
    $failed = 0;
    
    foreach ($users as $user) {
        echo "Sending to {$user['username']} ({$user['email']})... ";
        
        if (sendDailySummaryEmail($user['id'])) {
            echo "✓ Sent\n";
            $sent++;
        } else {
            echo "✗ Failed\n";
            $failed++;
        }
        
        // Small delay to avoid overwhelming mail server
        usleep(100000); // 0.1 second
    }
    
    echo "\n=== Summary ===\n";
    echo "Total Users: " . count($users) . "\n";
    echo "Sent: $sent\n";
    echo "Failed: $failed\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Daily summaries error: " . $e->getMessage());
}
