<?php
/**
 * Check and Send Milestone Notifications
 * Run this hourly
 * Cron: 0 * * * * php /path/to/cron/check_milestones.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email_notifications.php';

echo "=== Checking Milestones ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Define milestones
    $milestones = [
        ['type' => '1000_views', 'field' => 'total_views', 'value' => 1000, 'column' => 'milestone_1000_views_sent'],
        ['type' => '10000_views', 'field' => 'total_views', 'value' => 10000, 'column' => 'milestone_10000_views_sent'],
        ['type' => '100000_views', 'field' => 'total_views', 'value' => 100000, 'column' => 'milestone_100000_views_sent'],
        ['type' => '100_earned', 'field' => 'total_earnings', 'value' => 100, 'column' => 'milestone_100_earned_sent'],
        ['type' => '1000_earned', 'field' => 'total_earnings', 'value' => 1000, 'column' => 'milestone_1000_earned_sent'],
        ['type' => '10000_earned', 'field' => 'total_earnings', 'value' => 10000, 'column' => 'milestone_10000_earned_sent']
    ];
    
    $totalSent = 0;
    
    foreach ($milestones as $milestone) {
        // First, ensure column exists (create if not)
        $checkColumn = $pdo->query("SHOW COLUMNS FROM users LIKE '{$milestone['column']}'");
        if ($checkColumn->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$milestone['column']} TINYINT(1) DEFAULT 0");
        }
        
        // Find users who reached milestone but haven't been notified
        $stmt = $pdo->prepare("
            SELECT id, username, {$milestone['field']} as current_value
            FROM users
            WHERE {$milestone['field']} >= ?
            AND ({$milestone['column']} = 0 OR {$milestone['column']} IS NULL)
            AND status = 'approved'
            AND milestone_alerts_enabled = 1
        ");
        $stmt->execute([$milestone['value']]);
        $users = $stmt->fetchAll();
        
        foreach ($users as $user) {
            echo "Milestone {$milestone['type']} for {$user['username']} (Value: {$user['current_value']})... ";
            
            if (sendMilestoneEmail($user['id'], $milestone['type'], $user['current_value'])) {
                // Mark as sent
                $update = $pdo->prepare("UPDATE users SET {$milestone['column']} = 1 WHERE id = ?");
                $update->execute([$user['id']]);
                echo "✓ Sent\n";
                $totalSent++;
            } else {
                echo "✗ Failed\n";
            }
            
            usleep(100000);
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Total Milestone Emails Sent: $totalSent\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Milestones check error: " . $e->getMessage());
}
