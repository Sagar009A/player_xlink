<?php
/**
 * Run Fraud Detection on All Users
 * Run this every hour
 * Cron: 0 * * * * php /path/to/cron/run_fraud_detection.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/fraud_detection.php';

echo "=== Running Fraud Detection ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Get all active users
    $stmt = $pdo->query("
        SELECT DISTINCT user_id 
        FROM views_log 
        WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $totalAlerts = 0;
    $usersWithAlerts = 0;
    
    foreach ($userIds as $userId) {
        $alerts = detectFraudulentActivity($userId, 24);
        
        if (!empty($alerts)) {
            echo "User ID $userId: " . count($alerts) . " alerts\n";
            $usersWithAlerts++;
            $totalAlerts += count($alerts);
            
            // Store alerts in database
            foreach ($alerts as $alert) {
                $stmt = $pdo->prepare("
                    INSERT INTO fraud_alerts 
                    (user_id, alert_type, severity, message, alert_data)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $alert['type'],
                    $alert['severity'],
                    $alert['message'],
                    json_encode($alert['data'] ?? [])
                ]);
            }
            
            // Auto-block high severity IPs
            foreach ($alerts as $alert) {
                if ($alert['severity'] === 'high' && isset($alert['data']['ip_address'])) {
                    blockSuspiciousIP($alert['data']['ip_address'], $alert['type']);
                    echo "  ⚠ Blocked IP: {$alert['data']['ip_address']}\n";
                }
            }
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Users Checked: " . count($userIds) . "\n";
    echo "Users with Alerts: $usersWithAlerts\n";
    echo "Total Alerts: $totalAlerts\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Fraud detection error: " . $e->getMessage());
}
