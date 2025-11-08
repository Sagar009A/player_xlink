<?php
/**
 * Link Shortening API
 * Creates shortened links - Used by Telegram Bot and external apps
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Get API key
$apiKey = $_POST['api_key'] ?? '';

if (!$apiKey) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'API key is required'
    ]);
    exit;
}

// Validate API key and get user
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE api_key = ? AND status = 'approved'");
    $stmt->execute([$apiKey]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid API key or account not approved'
        ]);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
    exit;
}

// Get URL to shorten
$url = filter_var($_POST['url'] ?? '', FILTER_VALIDATE_URL);

if (!$url) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Valid URL is required'
    ]);
    exit;
}

// Optional parameters
$title = sanitizeInput($_POST['title'] ?? '');
$autoExtract = ($_POST['auto_extract'] ?? 'true') === 'true';

try {
    // Generate unique short code
    $shortCode = generateShortCode();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE short_code = ?");
    $stmt->execute([$shortCode]);
    
    while ($stmt->fetchColumn() > 0) {
        $shortCode = generateShortCode();
        $stmt->execute([$shortCode]);
    }
    
    // Auto-extract video if enabled
    $directVideoUrl = null;
    $videoPlatform = null;
    $videoQuality = null;
    $thumbnailUrl = null;
    $videoExpiresAt = null;
    
    if ($autoExtract) {
        // Load extractor if available
        if (file_exists(__DIR__ . '/../extractors/AbstractExtractor.php')) {
            require_once __DIR__ . '/../extractors/AbstractExtractor.php';
        }
        
        if (file_exists(__DIR__ . '/../services/ExtractorManager.php')) {
            require_once __DIR__ . '/../services/ExtractorManager.php';
            
            try {
                $manager = new ExtractorManager();
                $extractResult = $manager->extract($url);
                
                if ($extractResult['success']) {
                    $directVideoUrl = $extractResult['data']['direct_link'] ?? null;
                    $videoPlatform = $extractResult['platform'] ?? null;
                    $videoQuality = $extractResult['data']['quality'] ?? null;
                    $thumbnailUrl = $extractResult['data']['thumbnail'] ?? null;
                    
                    // Set expiry if provided
                    if (isset($extractResult['data']['expires_at'])) {
                        $videoExpiresAt = $extractResult['data']['expires_at'];
                    }
                    
                    // Use extracted filename as title if no title provided
                    if (!$title && isset($extractResult['data']['filename'])) {
                        $title = $extractResult['data']['filename'];
                    }
                }
            } catch (Exception $e) {
                error_log("Extraction error: " . $e->getMessage());
                // Continue without extraction
            }
        }
    }
    
    // Generate title if still empty
    if (!$title) {
        $title = 'Link ' . date('Y-m-d H:i:s');
    }
    
    // Insert into links table
    $stmt = $pdo->prepare("
        INSERT INTO links (
            user_id, original_url, short_code, title, 
            thumbnail_url, direct_video_url, video_platform, 
            video_quality, video_expires_at, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->execute([
        $user['id'],
        $url,
        $shortCode,
        $title,
        $thumbnailUrl,
        $directVideoUrl,
        $videoPlatform,
        $videoQuality,
        $videoExpiresAt
    ]);
    
    $linkId = $pdo->lastInsertId();
    
    // Build short URL
    $shortUrl = SITE_URL . '/' . $shortCode;
    
    // Return success response
    echo json_encode([
        'success' => true,
        'shortUrl' => $shortUrl,
        'short_url' => $shortUrl, // Alternative key for compatibility
        'data' => [
            'link_id' => (int)$linkId,
            'short_code' => $shortCode,
            'short_url' => $shortUrl,
            'original_url' => $url,
            'title' => $title,
            'has_video' => !empty($directVideoUrl),
            'video_platform' => $videoPlatform,
            'video_quality' => $videoQuality,
            'thumbnail' => $thumbnailUrl,
            'expires_at' => $videoExpiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    error_log("Database error in shorten.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create shortened link',
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("Error in shorten.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create shortened link',
        'message' => $e->getMessage()
    ]);
}
