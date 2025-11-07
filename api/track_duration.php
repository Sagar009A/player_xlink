<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$linkId = intval($data['link_id'] ?? 0);
$duration = intval($data['duration'] ?? 0);

if ($linkId && $duration > 0) {
    try {
        // Update the last view's watch duration
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $pdo->prepare("
            UPDATE views_log 
            SET watch_duration = ? 
            WHERE link_id = ? 
            AND ip_address = ? 
            ORDER BY viewed_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$duration, $linkId, $ip]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
}