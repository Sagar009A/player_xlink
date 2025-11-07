<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate required fields
    if (empty($data['link_id']) || empty($data['reason'])) {
        throw new Exception('Missing required fields');
    }
    
    $linkId = (int)$data['link_id'];
    $shortCode = sanitizeInput($data['short_code'] ?? '');
    $reason = sanitizeInput($data['reason']);
    $details = sanitizeInput($data['details'] ?? '');
    
    // Validate reason
    $validReasons = ['copyright', 'adult', 'violence', 'spam', 'other'];
    if (!in_array($reason, $validReasons)) {
        throw new Exception('Invalid report reason');
    }
    
    // Get reporter info
    $ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Check if link exists
    $stmt = $pdo->prepare("SELECT id, user_id FROM links WHERE id = ?");
    $stmt->execute([$linkId]);
    $link = $stmt->fetch();
    
    if (!$link) {
        throw new Exception('Link not found');
    }
    
    // Create reports table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS content_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            link_id INT NOT NULL,
            short_code VARCHAR(20),
            reason VARCHAR(50) NOT NULL,
            details TEXT,
            reporter_ip VARCHAR(45),
            reporter_user_agent TEXT,
            status ENUM('pending', 'reviewed', 'resolved', 'dismissed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_link_id (link_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        )
    ");
    
    // Check for duplicate reports from same IP within 24 hours
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM content_reports 
        WHERE link_id = ? AND reporter_ip = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$linkId, $ipAddress]);
    $existingReports = $stmt->fetch();
    
    if ($existingReports['count'] > 0) {
        // Report already exists, but don't tell the user to prevent abuse detection
        echo json_encode([
            'success' => true,
            'message' => 'Report submitted successfully'
        ]);
        exit;
    }
    
    // Insert report
    $stmt = $pdo->prepare("
        INSERT INTO content_reports (
            link_id, short_code, reason, details, 
            reporter_ip, reporter_user_agent, status
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->execute([
        $linkId,
        $shortCode,
        $reason,
        $details,
        $ipAddress,
        $userAgent
    ]);
    
    // Log the report
    error_log("Content report: Link ID {$linkId}, Reason: {$reason}, IP: {$ipAddress}");
    
    // If this is a serious violation (adult, violence, copyright), flag the link
    if (in_array($reason, ['adult', 'violence', 'copyright'])) {
        // Check if there are multiple reports for this link
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as report_count FROM content_reports 
            WHERE link_id = ? AND reason IN ('adult', 'violence', 'copyright')
        ");
        $stmt->execute([$linkId]);
        $reportStats = $stmt->fetch();
        
        // If 3 or more serious reports, automatically disable the link
        if ($reportStats['report_count'] >= 3) {
            $stmt = $pdo->prepare("UPDATE links SET is_active = 0 WHERE id = ?");
            $stmt->execute([$linkId]);
            error_log("Link {$linkId} automatically disabled due to multiple serious reports");
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Report submitted successfully. Thank you for helping keep our community safe.'
    ]);
    
} catch (Exception $e) {
    error_log("Report submission error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}