<?php
/**
 * Telegram Bot & App API
 * Simple API for bot and mobile app integration
 * 
 * Features:
 * - Create short links via API
 * - Track views and statistics
 * - Get user stats
 * - Authentication via API key
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

// Get action first to determine if API key is required
$action = $_GET['action'] ?? $_POST['action'] ?? 'help';

// Public actions that don't require API key
$publicActions = ['track', 'help'];

// Get API key from header or parameter
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? $_POST['api_key'] ?? null;

// API Documentation
if (isset($_GET['help']) || (!$apiKey && !in_array($action, $publicActions))) {
    echo json_encode([
        'name' => 'LinkStreamX Bot & App API',
        'version' => '1.0',
        'authentication' => 'API Key required for most endpoints (get from user profile)',
        'public_endpoints' => ['track'],
        'endpoints' => [
            'create_link' => [
                'method' => 'POST',
                'url' => '/api/bot_api.php?action=create',
                'auth' => 'required',
                'params' => [
                    'api_key' => 'Your API key (required)',
                    'url' => 'Original URL to shorten (required)',
                    'title' => 'Link title (optional)',
                    'auto_extract' => 'true/false - Auto-extract video (default: true)'
                ],
                'example' => 'curl -X POST "https://yoursite.com/api/bot_api.php?action=create" -d "api_key=YOUR_KEY&url=https://terabox.com/s/example"'
            ],
            'get_stats' => [
                'method' => 'GET',
                'url' => '/api/bot_api.php?action=stats&api_key=YOUR_KEY',
                'auth' => 'required',
                'description' => 'Get user statistics'
            ],
            'track_view' => [
                'method' => 'POST/GET',
                'url' => '/api/bot_api.php?action=track&short_code=ABC123',
                'auth' => 'not required',
                'description' => 'Track a view (called automatically on redirect, public endpoint)',
                'params' => [
                    'short_code' => 'Short code to track (required)',
                    'ip' => 'IP address (optional, auto-detected)',
                    'user_agent' => 'User agent (optional, auto-detected)'
                ]
            ],
            'get_link' => [
                'method' => 'GET',
                'url' => '/api/bot_api.php?action=get&short_code=ABC123&api_key=YOUR_KEY',
                'auth' => 'required',
                'description' => 'Get link details'
            ]
        ],
        'response_format' => [
            'success' => true,
            'data' => '...',
            'message' => 'Success message'
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

// Validate API key for non-public actions
$user = null;
if (!in_array($action, $publicActions)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE api_key = ? AND status = 'approved'");
    $stmt->execute([$apiKey]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid API key or account not approved',
            'code' => 'INVALID_API_KEY'
        ]);
        exit;
    }
}

// ============================================================
// ACTION: CREATE LINK
// ============================================================
if ($action === 'create') {
    $url = filter_var($_POST['url'] ?? '', FILTER_VALIDATE_URL);
    $title = sanitizeInput($_POST['title'] ?? '');
    $autoExtract = ($_POST['auto_extract'] ?? 'true') === 'true';
    
    if (!$url) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Valid URL is required',
            'code' => 'INVALID_URL'
        ]);
        exit;
    }
    
    try {
        // Generate short code
        $shortCode = generateShortCode();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE short_code = ?");
        $stmt->execute([$shortCode]);
        while ($stmt->fetchColumn() > 0) {
            $shortCode = generateShortCode();
            $stmt->execute([$shortCode]);
        }
        
        // Auto-extract if enabled
        $directVideoUrl = null;
        $videoPlatform = null;
        $videoQuality = null;
        $thumbnailUrl = null;
        
        if ($autoExtract) {
            if (file_exists(__DIR__ . '/../services/ExtractorManager.php')) {
                // Load AbstractExtractor FIRST
                if (file_exists(__DIR__ . '/../extractors/AbstractExtractor.php')) {
                    require_once __DIR__ . '/../extractors/AbstractExtractor.php';
                }
                
                require_once __DIR__ . '/../services/ExtractorManager.php';
                $manager = new ExtractorManager();
                $extractResult = $manager->extract($url);
                
                if ($extractResult['success']) {
                    $directVideoUrl = $extractResult['data']['direct_link'];
                    $videoPlatform = $extractResult['platform'];
                    $videoQuality = $extractResult['data']['quality'] ?? null;
                    $thumbnailUrl = $extractResult['data']['thumbnail'] ?? null;
                    
                    if (!$title) {
                        $title = $extractResult['data']['filename'] ?? 'Video';
                    }
                }
            }
        }
        
        if (!$title) {
            $title = 'Link ' . date('Y-m-d H:i:s');
        }
        
        // Insert link
        $stmt = $pdo->prepare("
            INSERT INTO links (
                user_id, original_url, short_code, title, thumbnail_url,
                direct_video_url, video_platform, video_quality
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user['id'], $url, $shortCode, $title, $thumbnailUrl,
            $directVideoUrl, $videoPlatform, $videoQuality
        ]);
        
        $linkId = $pdo->lastInsertId();
        $shortUrl = SITE_URL . '/' . $shortCode;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'link_id' => $linkId,
                'short_code' => $shortCode,
                'short_url' => $shortUrl,
                'original_url' => $url,
                'title' => $title,
                'has_video' => !empty($directVideoUrl),
                'video_platform' => $videoPlatform,
                'video_quality' => $videoQuality,
                'thumbnail' => $thumbnailUrl
            ],
            'message' => 'Link created successfully'
        ], JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create link',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// ============================================================
// ACTION: GET STATS
// ============================================================
if ($action === 'stats') {
    echo json_encode([
        'success' => true,
        'data' => [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ],
            'stats' => [
                'total_links' => (int)$user['total_links'],
                'total_views' => (int)$user['total_views'],
                'today_views' => (int)$user['today_views'],
                'balance' => (float)$user['balance'],
                'total_earnings' => (float)$user['total_earnings']
            ],
            'limits' => [
                'daily_view_limit' => (int)$user['daily_view_limit'],
                'remaining_today' => max(0, (int)$user['daily_view_limit'] - (int)$user['today_views'])
            ]
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

// ============================================================
// ACTION: GET LINK DETAILS
// ============================================================
if ($action === 'get') {
    $shortCode = $_GET['short_code'] ?? '';
    
    if (!$shortCode) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'short_code parameter required'
        ]);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM links WHERE short_code = ? AND user_id = ?");
    $stmt->execute([$shortCode, $user['id']]);
    $link = $stmt->fetch();
    
    if (!$link) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Link not found or access denied'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $link['id'],
            'short_code' => $link['short_code'],
            'short_url' => SITE_URL . '/' . $link['short_code'],
            'original_url' => $link['original_url'],
            'title' => $link['title'],
            'views' => (int)$link['views'],
            'earnings' => (float)$link['earnings'],
            'is_active' => (bool)$link['is_active'],
            'has_video' => !empty($link['direct_video_url']),
            'video_platform' => $link['video_platform'],
            'video_quality' => $link['video_quality'],
            'created_at' => $link['created_at']
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

// ============================================================
// ACTION: TRACK VIEW (Used by apps and redirects - PUBLIC)
// ============================================================
if ($action === 'track') {
    // Support both GET and POST for short_code
    $shortCode = $_GET['short_code'] ?? $_POST['short_code'] ?? '';
    $ipAddress = $_POST['ip'] ?? $_GET['ip'] ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $userAgent = $_POST['user_agent'] ?? $_GET['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Clean and validate short code
    $shortCode = sanitizeInput($shortCode);
    
    if (!$shortCode) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'short_code parameter required',
            'code' => 'MISSING_PARAM'
        ]);
        exit;
    }
    
    try {
        // Get link with user info
        $stmt = $pdo->prepare("
            SELECT l.*, u.daily_view_limit, u.today_views 
            FROM links l 
            JOIN users u ON l.user_id = u.id 
            WHERE l.short_code = ? AND l.is_active = 1
        ");
        $stmt->execute([$shortCode]);
        $link = $stmt->fetch();
        
        if (!$link) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Link not found',
                'code' => 'LINK_NOT_FOUND',
                'short_code' => $shortCode
            ]);
            exit;
        }
        
        // Check if should count
        $shouldCount = shouldCountView($link['id'], $ipAddress, $link['user_id']);
        
        // Get country code (can be enhanced with GeoIP)
        $countryCode = 'XX';
        
        // Calculate earnings
        $earnings = 0;
        if ($shouldCount) {
            $trafficSource = $link['traffic_source'] ?? null;
            
            // Use calculateCPMRate if available, fallback to getCPMRate
            if (function_exists('calculateCPMRate')) {
                $cpmData = calculateCPMRate($trafficSource, $countryCode);
                $earnings = ($cpmData['rate'] * $cpmData['multiplier']) / 1000;
            } else {
                $cpmRate = getCPMRate($countryCode);
                $earnings = $cpmRate / 1000;
            }
        }
        
        // Log view with more details
        $stmt = $pdo->prepare("
            INSERT INTO views_log (
                link_id, user_id, ip_address, country_code, is_counted, earnings
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $link['id'], $link['user_id'], $ipAddress, $countryCode, $shouldCount ? 1 : 0, $earnings
        ]);
        
        // Update stats if counted
        if ($shouldCount) {
            $stmt = $pdo->prepare("UPDATE links SET views = views + 1, earnings = earnings + ? WHERE id = ?");
            $stmt->execute([$earnings, $link['id']]);
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET total_views = total_views + 1, 
                    today_views = today_views + 1,
                    balance = balance + ?,
                    total_earnings = total_earnings + ?
                WHERE id = ?
            ");
            $stmt->execute([$earnings, $earnings, $link['user_id']]);
        }
        
        echo json_encode([
            'success' => true,
            'counted' => $shouldCount,
            'earnings' => number_format($earnings, 6),
            'link' => [
                'id' => $link['id'],
                'title' => $link['title'],
                'total_views' => (int)$link['views'] + ($shouldCount ? 1 : 0)
            ],
            'message' => $shouldCount ? 'View counted successfully' : 'View not counted (limit reached or duplicate)'
        ]);
        
    } catch (PDOException $e) {
        error_log("Track view error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to track view',
            'code' => 'DATABASE_ERROR',
            'message' => 'An error occurred while tracking the view'
        ]);
    } catch (Exception $e) {
        error_log("Track view error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to track view',
            'code' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Unknown action
http_response_code(400);
echo json_encode([
    'success' => false,
    'error' => 'Invalid action',
    'available_actions' => ['create', 'stats', 'get', 'track', 'help']
]);