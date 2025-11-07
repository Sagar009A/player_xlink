<?php
/**
 * Track API - Link Tracking by Short Code
 * 
 * Requirement from req.md:
 * - Track link by short_code
 * - Validate API key
 * - Return link details with user info
 * 
 * Usage:
 * GET /api/track_api.php?action=track&short_code=ABC123&api_key=YOUR_KEY
 * POST /api/track_api.php with JSON body
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get request data
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$short_code = $_GET['short_code'] ?? $_POST['short_code'] ?? '';
$api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';

// Alternative: Check X-API-Key header
if (empty($api_key)) {
    $headers = getallheaders();
    $api_key = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? '';
}

/**
 * Track Action - Main Implementation from req.md
 */
if ($action === 'track') {
    
    // Validate API key
    if (empty($api_key)) {
        echo json_encode([
            "status" => "error",
            "message" => "API key is required"
        ]);
        exit;
    }
    
    // Verify API key exists and is active
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT au.*, u.username, u.status as user_status
            FROM api_users au
            JOIN users u ON au.user_id = u.id
            WHERE au.api_key = ? AND au.api_status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$api_key]);
        $apiUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$apiUser) {
            echo json_encode([
                "status" => "error",
                "message" => "Invalid API key"
            ]);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $e->getMessage()
        ]);
        exit;
    }
    
    // Validate short code
    if (empty($short_code)) {
        echo json_encode([
            "status" => "error",
            "message" => "Missing short_code parameter"
        ]);
        exit;
    }
    
    // Find link by short_code
    try {
        $sql = "SELECT 
                    l.id, 
                    l.user_id, 
                    l.views, 
                    l.title, 
                    l.direct_video_url,
                    l.original_url,
                    l.short_code,
                    l.custom_alias,
                    l.thumbnail_url,
                    l.earnings,
                    l.today_views,
                    l.created_at,
                    l.last_view_at,
                    l.is_active,
                    u.username,
                    u.email
                FROM links l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE (l.short_code = ? OR l.custom_alias = ?) 
                AND l.is_active = 1 
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$short_code, $short_code]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$link) {
            echo json_encode([
                "status" => "error",
                "message" => "Invalid or inactive short_code"
            ]);
            exit;
        }
        
        // Log API request
        try {
            $logStmt = $pdo->prepare("
                INSERT INTO api_requests_log 
                (api_user_id, endpoint, request_method, request_params, response_status, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $logStmt->execute([
                $apiUser['user_id'],
                '/api/track_api.php',
                $_SERVER['REQUEST_METHOD'],
                json_encode(['action' => 'track', 'short_code' => $short_code]),
                'success',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Log error silently, don't break the response
        }
        
        // Build full URLs
        $shortUrl = defined('SITE_URL') ? SITE_URL . '/' . ($link['custom_alias'] ?: $link['short_code']) : '';
        $thumbnailUrl = $link['thumbnail_url'] ? (defined('SITE_URL') ? SITE_URL : '') . $link['thumbnail_url'] : null;
        
        // Return successful response (as per req.md format)
        echo json_encode([
            "status" => "success",
            "data" => [
                "link_id" => $link['id'],
                "user_id" => $link['user_id'],
                "username" => $link['username'],
                "email" => $link['email'],
                "short_code" => $link['short_code'],
                "custom_alias" => $link['custom_alias'],
                "title" => $link['title'],
                "original_url" => $link['original_url'],
                "direct_video_url" => $link['direct_video_url'],
                "short_url" => $shortUrl,
                "thumbnail_url" => $thumbnailUrl,
                "views" => (int)$link['views'],
                "today_views" => (int)$link['today_views'],
                "earnings" => (float)$link['earnings'],
                "is_active" => (bool)$link['is_active'],
                "created_at" => $link['created_at'],
                "last_view_at" => $link['last_view_at']
            ],
            "message" => "Link tracked successfully"
        ], JSON_PRETTY_PRINT);
        
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $e->getMessage()
        ]);
        exit;
    }
}

/**
 * Get Link Stats by Short Code
 */
elseif ($action === 'stats') {
    
    // Validate API key (same as above)
    if (empty($api_key)) {
        echo json_encode(["status" => "error", "message" => "API key is required"]);
        exit;
    }
    
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM api_users WHERE api_key = ? AND api_status = 'active' LIMIT 1");
        $stmt->execute([$api_key]);
        $apiUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$apiUser) {
            echo json_encode(["status" => "error", "message" => "Invalid API key"]);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Database error"]);
        exit;
    }
    
    if (empty($short_code)) {
        echo json_encode(["status" => "error", "message" => "Missing short_code"]);
        exit;
    }
    
    try {
        // Get link
        $stmt = $pdo->prepare("SELECT * FROM links WHERE short_code = ? OR custom_alias = ? LIMIT 1");
        $stmt->execute([$short_code, $short_code]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$link) {
            echo json_encode(["status" => "error", "message" => "Link not found"]);
            exit;
        }
        
        // Get view stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_views,
                COUNT(DISTINCT ip_address) as unique_visitors,
                COUNT(DISTINCT country_code) as countries,
                DATE(MAX(viewed_at)) as last_view_date
            FROM views_log
            WHERE link_id = ?
        ");
        $stmt->execute([$link['id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get country breakdown
        $stmt = $pdo->prepare("
            SELECT country_code, country_name, COUNT(*) as views
            FROM views_log
            WHERE link_id = ?
            GROUP BY country_code
            ORDER BY views DESC
            LIMIT 10
        ");
        $stmt->execute([$link['id']]);
        $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            "status" => "success",
            "data" => [
                "short_code" => $link['short_code'],
                "title" => $link['title'],
                "total_views" => (int)$stats['total_views'],
                "unique_visitors" => (int)$stats['unique_visitors'],
                "countries_reached" => (int)$stats['countries'],
                "last_view_date" => $stats['last_view_date'],
                "earnings" => (float)$link['earnings'],
                "top_countries" => $countries
            ]
        ], JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Database error"]);
        exit;
    }
}

/**
 * Invalid Action
 */
else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid action. Supported actions: track, stats"
    ]);
    exit;
}
