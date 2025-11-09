<?php
/**
 * Real-time Statistics API
 * Returns live stats for dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';

$userId = intval($_GET['user_id'] ?? 0);

if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

// Verify user session or API key
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $userId) {
    // Check API key if session not valid
    $apiKey = $_GET['api_key'] ?? '';
    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND api_key = ?");
    $stmt->execute([$userId, $apiKey]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }
}

try {
    // Get real-time stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as today_views,
            COALESCE(SUM(earnings), 0) as today_earnings,
            COUNT(DISTINCT ip_address) as today_unique_visitors,
            COUNT(CASE WHEN viewed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as last_hour_views
        FROM views_log
        WHERE user_id = ? AND DATE(viewed_at) = CURDATE() AND is_counted = 1
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    
    // Get user current balance
    $stmt = $pdo->prepare("SELECT balance, total_views, total_earnings FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // Get active links count
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_links FROM links WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    $linksCount = $stmt->fetch();
    
    // Get recent activity (last 5 minutes)
    $stmt = $pdo->prepare("
        SELECT 
            l.title,
            v.country_name,
            v.viewed_at
        FROM views_log v
        JOIN links l ON v.link_id = l.id
        WHERE v.user_id = ? AND v.viewed_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY v.viewed_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $recentActivity = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'today_views' => (int)$stats['today_views'],
        'today_earnings' => (float)$stats['today_earnings'],
        'today_unique_visitors' => (int)$stats['today_unique_visitors'],
        'last_hour_views' => (int)$stats['last_hour_views'],
        'current_balance' => (float)$user['balance'],
        'total_views' => (int)$user['total_views'],
        'total_earnings' => (float)$user['total_earnings'],
        'active_links' => (int)$linksCount['active_links'],
        'recent_activity' => $recentActivity
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch stats',
        'message' => $e->getMessage()
    ]);
}
