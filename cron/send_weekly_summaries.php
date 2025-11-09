<?php
/**
 * Send Weekly Summary Emails to All Users
 * Run this weekly on Monday at 01:00 AM
 * Cron: 0 1 * * 1 php /path/to/cron/send_weekly_summaries.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email_notifications.php';

echo "=== Starting Weekly Summary Email Sender ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Get all users who want weekly summaries
    $stmt = $pdo->query("
        SELECT id, username, email 
        FROM users 
        WHERE status = 'approved' 
        AND email_notifications_enabled = 1 
        AND weekly_summary_enabled = 1
        AND email IS NOT NULL AND email != ''
    ");
    
    $users = $stmt->fetchAll();
    $sent = 0;
    $failed = 0;
    
    foreach ($users as $user) {
        echo "Sending to {$user['username']} ({$user['email']})... ";
        
        if (sendWeeklySummaryEmail($user['id'])) {
            echo "✓ Sent\n";
            $sent++;
        } else {
            echo "✗ Failed\n";
            $failed++;
        }
        
        usleep(100000);
    }
    
    echo "\n=== Summary ===\n";
    echo "Total Users: " . count($users) . "\n";
    echo "Sent: $sent\n";
    echo "Failed: $failed\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Weekly summaries error: " . $e->getMessage());
}
