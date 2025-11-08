<?php
/**
 * Complete Video API System
 * Handles: Upload, List, Update, Delete, Analytics
 * All videos tracked by user
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/security.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Authenticate API request
 */
function authenticateAPI($pdo) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
    
    if (!$apiKey) {
        return ['error' => 'API key required', 'code' => 401];
    }
    
    $stmt = $pdo->prepare("
        SELECT au.*, u.username, u.email, u.status 
        FROM api_users au
        INNER JOIN users u ON au.user_id = u.id
        WHERE au.api_key = ? AND au.api_status = 'active' AND u.status = 'approved'
    ");
    $stmt->execute([$apiKey]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['error' => 'Invalid or inactive API key', 'code' => 401];
    }
    
    // Update last API call
    $updateStmt = $pdo->prepare("UPDATE api_users SET last_api_call = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
    return $user;
}

/**
 * Log API request
 */
function logAPIRequest($pdo, $user, $endpoint, $method, $videoId = null, $success = true, $errorMsg = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO api_requests_log 
            (user_id, api_user_id, username, api_key, endpoint, method, video_id, 
             ip_address, user_agent, success, error_message, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user['user_id'],
            $user['id'],
            $user['username'],
            $user['api_key'],
            $endpoint,
            $method,
            $videoId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $success ? 1 : 0,
            $errorMsg
        ]);
    } catch (Exception $e) {
        error_log("Failed to log API request: " . $e->getMessage());
    }
}

/**
 * Generate unique video ID
 */
function generateVideoId() {
    return 'VID_' . time() . '_' . bin2hex(random_bytes(8));
}

/**
 * Generate short code
 */
function generateShortCode($pdo) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $maxAttempts = 10;
    
    for ($i = 0; $i < $maxAttempts; $i++) {
        // Generate random length between 22-26
        $length = rand(22, 26);
        $shortCode = '';
        for ($j = 0; $j < $length; $j++) {
            $shortCode .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        $stmt = $pdo->prepare("SELECT id FROM api_videos WHERE short_code = ?");
        $stmt->execute([$shortCode]);
        
        if (!$stmt->fetch()) {
            return $shortCode;
        }
    }
    
    return null;
}

// ============================================
// MAIN API ROUTING
// ============================================

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? 'list';

// Authenticate user
$user = authenticateAPI($pdo);
if (isset($user['error'])) {
    http_response_code($user['code']);
    echo json_encode($user);
    exit;
}

try {
    switch ($path) {
        
        // ============================================
        // 1. UPLOAD VIDEO
        // ============================================
        case 'upload':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $requiredFields = ['original_url', 'video_platform'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }
            
            // Check video limit
            if ($user['total_videos'] >= $user['max_videos_allowed']) {
                throw new Exception('Video limit reached. Upgrade your plan.');
            }
            
            // Generate IDs
            $videoId = generateVideoId();
            $shortCode = $data['custom_alias'] ?? generateShortCode($pdo);
            
            if (!$shortCode) {
                throw new Exception('Failed to generate short code');
            }
            
            // Insert video
            $stmt = $pdo->prepare("
                INSERT INTO api_videos (
                    video_id, user_id, username, api_user_id, original_url,
                    video_title, video_description, thumbnail_url, video_server_url,
                    video_platform, video_quality, video_duration, video_size,
                    short_code, custom_alias, is_public, is_monetized,
                    category, tags, upload_ip, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");
            
            $stmt->execute([
                $videoId,
                $user['user_id'],
                $user['username'],
                $user['id'],
                $data['original_url'],
                $data['title'] ?? 'Untitled Video',
                $data['description'] ?? '',
                $data['thumbnail_url'] ?? null,
                $data['video_server_url'] ?? null,
                $data['video_platform'],
                $data['quality'] ?? '720p',
                $data['duration'] ?? null,
                $data['size'] ?? null,
                $shortCode,
                $data['custom_alias'] ?? null,
                $data['is_public'] ?? 1,
                $data['is_monetized'] ?? 1,
                $data['category'] ?? 'general',
                json_encode($data['tags'] ?? []),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            $insertedId = $pdo->lastInsertId();
            
            // Add server link if provided
            if (!empty($data['video_server_url'])) {
                $serverStmt = $pdo->prepare("
                    INSERT INTO api_video_servers 
                    (video_id, api_video_id, user_id, server_name, server_url, 
                     server_type, quality, is_primary, is_active, created_at)
                    VALUES (?, ?, ?, 'primary', ?, ?, ?, 1, 1, NOW())
                ");
                $serverStmt->execute([
                    $videoId,
                    $insertedId,
                    $user['user_id'],
                    $data['video_server_url'],
                    $data['video_platform'],
                    $data['quality'] ?? '720p'
                ]);
            }
            
            logAPIRequest($pdo, $user, 'upload', 'POST', $videoId, true);
            
            $response = [
                'success' => true,
                'message' => 'Video uploaded successfully',
                'data' => [
                    'video_id' => $videoId,
                    'short_code' => $shortCode,
                    'short_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/' . $shortCode,
                    'watch_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/redirect.php?code=' . $shortCode,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
            
            http_response_code(201);
            echo json_encode($response);
            break;
            
        // ============================================
        // 2. LIST USER VIDEOS
        // ============================================
        case 'list':
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            // Count total videos
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) as total FROM api_videos WHERE user_id = ?
            ");
            $countStmt->execute([$user['user_id']]);
            $totalVideos = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get videos with server info
            $stmt = $pdo->prepare("
                SELECT 
                    av.*,
                    GROUP_CONCAT(DISTINCT avs.server_url ORDER BY avs.is_primary DESC) as all_server_urls,
                    COUNT(DISTINCT avs.id) as server_count
                FROM api_videos av
                LEFT JOIN api_video_servers avs ON avs.api_video_id = av.id AND avs.is_active = 1
                WHERE av.user_id = ?
                GROUP BY av.id
                ORDER BY av.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user['user_id'], $limit, $offset]);
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format response
            foreach ($videos as &$video) {
                $video['tags'] = json_decode($video['tags'], true);
                $video['short_url'] = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $video['short_code'];
                $video['watch_url'] = 'https://' . $_SERVER['HTTP_HOST'] . '/redirect.php?code=' . $video['short_code'];
                $video['server_urls'] = $video['all_server_urls'] ? explode(',', $video['all_server_urls']) : [];
                unset($video['all_server_urls']);
            }
            
            logAPIRequest($pdo, $user, 'list', 'GET', null, true);
            
            $response = [
                'success' => true,
                'data' => [
                    'videos' => $videos,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $totalVideos,
                        'total_pages' => ceil($totalVideos / $limit)
                    ],
                    'user_stats' => [
                        'total_videos' => $user['total_videos'],
                        'total_api_calls' => $user['total_api_calls'],
                        'api_tier' => $user['api_tier'],
                        'max_videos_allowed' => $user['max_videos_allowed']
                    ]
                ]
            ];
            
            echo json_encode($response);
            break;
            
        // ============================================
        // 3. GET SINGLE VIDEO
        // ============================================
        case 'get':
            $videoId = $_GET['video_id'] ?? null;
            
            if (!$videoId) {
                throw new Exception('video_id required');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    av.*,
                    GROUP_CONCAT(DISTINCT avs.server_url ORDER BY avs.is_primary DESC) as all_server_urls,
                    GROUP_CONCAT(DISTINCT avs.quality ORDER BY avs.is_primary DESC) as all_qualities
                FROM api_videos av
                LEFT JOIN api_video_servers avs ON avs.api_video_id = av.id AND avs.is_active = 1
                WHERE av.video_id = ? AND av.user_id = ?
                GROUP BY av.id
            ");
            $stmt->execute([$videoId, $user['user_id']]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$video) {
                throw new Exception('Video not found', 404);
            }
            
            $video['tags'] = json_decode($video['tags'], true);
            $video['short_url'] = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $video['short_code'];
            $video['watch_url'] = 'https://' . $_SERVER['HTTP_HOST'] . '/redirect.php?code=' . $video['short_code'];
            $video['server_urls'] = $video['all_server_urls'] ? explode(',', $video['all_server_urls']) : [];
            $video['available_qualities'] = $video['all_qualities'] ? explode(',', $video['all_qualities']) : [];
            
            logAPIRequest($pdo, $user, 'get', 'GET', $videoId, true);
            
            $response = [
                'success' => true,
                'data' => $video
            ];
            
            echo json_encode($response);
            break;
            
        // ============================================
        // 4. UPDATE VIDEO
        // ============================================
        case 'update':
            if ($method !== 'PUT' && $method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $videoId = $data['video_id'] ?? $_GET['video_id'] ?? null;
            
            if (!$videoId) {
                throw new Exception('video_id required');
            }
            
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM api_videos WHERE video_id = ? AND user_id = ?");
            $stmt->execute([$videoId, $user['user_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Video not found or access denied', 404);
            }
            
            // Build update query
            $updates = [];
            $params = [];
            
            $allowedFields = [
                'video_title', 'video_description', 'thumbnail_url', 'video_server_url',
                'video_quality', 'is_public', 'is_active', 'category'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (isset($data['tags'])) {
                $updates[] = "tags = ?";
                $params[] = json_encode($data['tags']);
            }
            
            if (empty($updates)) {
                throw new Exception('No fields to update');
            }
            
            $params[] = $videoId;
            $params[] = $user['user_id'];
            
            $updateStmt = $pdo->prepare("
                UPDATE api_videos 
                SET " . implode(', ', $updates) . ", updated_at = NOW()
                WHERE video_id = ? AND user_id = ?
            ");
            $updateStmt->execute($params);
            
            logAPIRequest($pdo, $user, 'update', 'PUT', $videoId, true);
            
            $response = [
                'success' => true,
                'message' => 'Video updated successfully',
                'data' => ['video_id' => $videoId]
            ];
            
            echo json_encode($response);
            break;
            
        // ============================================
        // 5. DELETE VIDEO
        // ============================================
        case 'delete':
            if ($method !== 'DELETE' && $method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $videoId = $_GET['video_id'] ?? $_POST['video_id'] ?? null;
            
            if (!$videoId) {
                throw new Exception('video_id required');
            }
            
            // Verify ownership and delete
            $stmt = $pdo->prepare("
                DELETE FROM api_videos 
                WHERE video_id = ? AND user_id = ?
            ");
            $stmt->execute([$videoId, $user['user_id']]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Video not found or already deleted', 404);
            }
            
            logAPIRequest($pdo, $user, 'delete', 'DELETE', $videoId, true);
            
            $response = [
                'success' => true,
                'message' => 'Video deleted successfully',
                'data' => ['video_id' => $videoId]
            ];
            
            echo json_encode($response);
            break;
            
        // ============================================
        // 6. VIDEO ANALYTICS
        // ============================================
        case 'analytics':
            $videoId = $_GET['video_id'] ?? null;
            $days = min(365, max(1, intval($_GET['days'] ?? 30)));
            
            if ($videoId) {
                // Single video analytics
                $stmt = $pdo->prepare("
                    SELECT * FROM api_video_analytics
                    WHERE video_id = ? 
                        AND user_id = ?
                        AND view_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                    ORDER BY view_date DESC
                ");
                $stmt->execute([$videoId, $user['user_id'], $days]);
                $analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response = [
                    'success' => true,
                    'data' => [
                        'video_id' => $videoId,
                        'period_days' => $days,
                        'analytics' => $analytics
                    ]
                ];
            } else {
                // All videos summary
                $stmt = $pdo->prepare("
                    SELECT 
                        view_date,
                        SUM(views_count) as total_views,
                        SUM(unique_views) as total_unique_views,
                        SUM(downloads_count) as total_downloads,
                        SUM(earnings) as total_earnings
                    FROM api_video_analytics
                    WHERE user_id = ? 
                        AND view_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                    GROUP BY view_date
                    ORDER BY view_date DESC
                ");
                $stmt->execute([$user['user_id'], $days]);
                $analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response = [
                    'success' => true,
                    'data' => [
                        'period_days' => $days,
                        'total_videos' => $user['total_videos'],
                        'analytics' => $analytics
                    ]
                ];
            }
            
            logAPIRequest($pdo, $user, 'analytics', 'GET', $videoId, true);
            echo json_encode($response);
            break;
            
        // ============================================
        // 7. USER SUMMARY
        // ============================================
        case 'summary':
            $stmt = $pdo->prepare("
                SELECT * FROM api_user_video_collections WHERE user_id = ?
            ");
            $stmt->execute([$user['user_id']]);
            $collection = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($collection) {
                $collection['video_ids'] = json_decode($collection['video_ids_json'], true);
                $collection['platforms_used'] = json_decode($collection['platforms_used'], true);
                $collection['categories'] = json_decode($collection['categories'], true);
                unset($collection['video_ids_json']);
            }
            
            logAPIRequest($pdo, $user, 'summary', 'GET', null, true);
            
            $response = [
                'success' => true,
                'data' => [
                    'user_info' => [
                        'username' => $user['username'],
                        'api_tier' => $user['api_tier'],
                        'api_status' => $user['api_status']
                    ],
                    'collection' => $collection,
                    'limits' => [
                        'max_videos' => $user['max_videos_allowed'],
                        'max_storage_bytes' => $user['max_storage_allowed'],
                        'rate_limit_per_minute' => $user['rate_limit_per_minute']
                    ]
                ]
            ];
            
            echo json_encode($response);
            break;
            
        default:
            throw new Exception('Invalid action', 400);
    }
    
} catch (Exception $e) {
    logAPIRequest($pdo, $user ?? ['user_id' => 0, 'id' => 0, 'username' => 'unknown', 'api_key' => 'unknown'], 
                  $path, $method, null, false, $e->getMessage());
    
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
