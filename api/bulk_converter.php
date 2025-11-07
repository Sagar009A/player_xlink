<?php
/**
 * Bulk Link Converter API
 * Create multiple short links at once
 * 
 * Endpoint: POST /api/bulk_converter.php
 * Authentication: API Key required
 * 
 * Request Body (JSON):
 * {
 *   "api_key": "your_api_key",
 *   "urls": [
 *     "https://teraboxlink.com/s/example1",
 *     "https://teraboxlink.com/s/example2",
 *     "https://youtube.com/watch?v=example3"
 *   ],
 *   "folder_id": null,  // Optional
 *   "auto_fetch": true, // Optional, default true
 *   "prefix": "",       // Optional prefix for titles
 *   "suffix": ""        // Optional suffix for titles
 * }
 * 
 * OR plain text (one URL per line):
 * urls=https://example.com/1
 * https://example.com/2
 * https://example.com/3
 * 
 * Response:
 * {
 *   "success": true,
 *   "total": 3,
 *   "created": 2,
 *   "failed": 1,
 *   "results": [
 *     {
 *       "success": true,
 *       "original_url": "https://example.com/1",
 *       "short_url": "https://yoursite.com/abc123",
 *       "short_code": "abc123",
 *       "title": "Video Title",
 *       "id": 123
 *     },
 *     {
 *       "success": false,
 *       "original_url": "invalid-url",
 *       "error": "Invalid URL format"
 *     }
 *   ],
 *   "processing_time": "2.45s"
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST request.',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../includes/terabox_helper.php';

$startTime = microtime(true);

// Get request data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$rawInput = file_get_contents('php://input');

// Parse input based on content type
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON format',
            'code' => 'INVALID_JSON'
        ]);
        exit;
    }
} else {
    // Parse form data or plain text
    $data = $_POST;
    if (empty($data) && !empty($rawInput)) {
        // Try to parse as URL list
        $data = [
            'urls' => array_filter(array_map('trim', explode("\n", $rawInput)))
        ];
    }
}

// Get API key
$apiKey = $data['api_key'] ?? $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;

if (empty($apiKey)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'API key is required',
        'code' => 'API_KEY_MISSING',
        'hint' => 'Include api_key in request body or X-API-KEY header'
    ]);
    exit;
}

// Validate API key and get user
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

// Check rate limit
$rateLimitKey = 'bulk_converter_' . $user['id'];
if (!checkRateLimit($rateLimitKey, 10, 3600)) { // 10 requests per hour
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Rate limit exceeded. Max 10 bulk conversions per hour.',
        'code' => 'RATE_LIMIT_EXCEEDED',
        'retry_after' => 3600
    ]);
    exit;
}

// Get URLs
$urls = $data['urls'] ?? [];
if (is_string($urls)) {
    // If string, split by newlines
    $urls = array_filter(array_map('trim', explode("\n", $urls)));
}

if (empty($urls) || !is_array($urls)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'No URLs provided',
        'code' => 'NO_URLS',
        'hint' => 'Send URLs as array in "urls" parameter'
    ]);
    exit;
}

// Limit URLs per request
$maxUrls = 100;
if (count($urls) > $maxUrls) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => "Maximum $maxUrls URLs allowed per request",
        'code' => 'TOO_MANY_URLS',
        'received' => count($urls),
        'max' => $maxUrls
    ]);
    exit;
}

// Get options
$folderId = isset($data['folder_id']) && !empty($data['folder_id']) ? intval($data['folder_id']) : null;
$autoFetch = $data['auto_fetch'] ?? true;
$prefix = $data['prefix'] ?? '';
$suffix = $data['suffix'] ?? '';

// Validate folder if provided
if ($folderId) {
    $stmt = $pdo->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ?");
    $stmt->execute([$folderId, $user['id']]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid folder ID',
            'code' => 'INVALID_FOLDER'
        ]);
        exit;
    }
}

// Process URLs
$results = [];
$created = 0;
$failed = 0;

foreach ($urls as $index => $url) {
    $url = trim($url);
    
    // Skip empty URLs
    if (empty($url)) {
        continue;
    }
    
    $result = [
        'index' => $index,
        'original_url' => $url,
        'success' => false
    ];
    
    try {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $result['error'] = 'Invalid URL format';
            $failed++;
            $results[] = $result;
            continue;
        }
        
        // Check if URL already exists for this user
        $stmt = $pdo->prepare("SELECT short_code FROM links WHERE original_url = ? AND user_id = ?");
        $stmt->execute([$url, $user['id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $result['success'] = true;
            $result['short_code'] = $existing['short_code'];
            $result['short_url'] = SITE_URL . '/' . $existing['short_code'];
            $result['message'] = 'URL already exists';
            $result['existing'] = true;
            $created++;
            $results[] = $result;
            continue;
        }
        
        // Generate unique short code
        $shortCode = generateShortCode();
        $attempts = 0;
        $maxAttempts = 10;
        
        while ($attempts < $maxAttempts) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE short_code = ?");
            $stmt->execute([$shortCode]);
            if ($stmt->fetchColumn() == 0) {
                break;
            }
            $shortCode = generateShortCode();
            $attempts++;
        }
        
        if ($attempts >= $maxAttempts) {
            $result['error'] = 'Failed to generate unique short code';
            $failed++;
            $results[] = $result;
            continue;
        }
        
        // Initialize default data
        $title = 'Link ' . date('Y-m-d H:i:s');
        $description = null;
        $thumbnailUrl = null;
        $thumbnailPath = null;
        
        // Auto-fetch data if enabled
        if ($autoFetch) {
            // Check if TeraBox link
            if (isTeraBoxLink($url)) {
                $fetchedData = fetchTeraBoxData($url);
                if ($fetchedData && !empty($fetchedData['title'])) {
                    $title = $fetchedData['title'];
                    $description = $fetchedData['description'] ?? null;
                    $thumbnailUrl = $fetchedData['thumbnail'] ?? null;
                }
            }
            // Check if YouTube link
            elseif (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
                $videoId = $matches[1];
                $title = 'YouTube Video';
                $thumbnailUrl = "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg";
            }
        }
        
        // Apply prefix and suffix
        if (!empty($prefix)) {
            $title = $prefix . ' ' . $title;
        }
        if (!empty($suffix)) {
            $title = $title . ' ' . $suffix;
        }
        
        // Limit title length
        $title = substr($title, 0, 255);
        
        // Download thumbnail if URL provided
        if ($thumbnailUrl && getSetting('auto_fetch_thumbnail', 1)) {
            $thumbnailPath = downloadThumbnail($thumbnailUrl, $shortCode);
        }
        
        // Insert link
        $stmt = $pdo->prepare("
            INSERT INTO links (
                user_id, 
                original_url, 
                short_code, 
                title, 
                description, 
                thumbnail_url, 
                thumbnail_path, 
                folder_id,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $success = $stmt->execute([
            $user['id'],
            $url,
            $shortCode,
            $title,
            $description,
            $thumbnailUrl,
            $thumbnailPath,
            $folderId
        ]);
        
        if ($success) {
            $linkId = $pdo->lastInsertId();
            $result['success'] = true;
            $result['id'] = $linkId;
            $result['short_code'] = $shortCode;
            $result['short_url'] = SITE_URL . '/' . $shortCode;
            $result['title'] = $title;
            if ($description) {
                $result['description'] = $description;
            }
            if ($thumbnailPath) {
                $result['thumbnail'] = SITE_URL . '/' . $thumbnailPath;
            }
            $created++;
        } else {
            $result['error'] = 'Database insertion failed';
            $failed++;
        }
        
    } catch (Exception $e) {
        $result['error'] = 'Error: ' . $e->getMessage();
        $failed++;
        error_log("Bulk converter error for URL $url: " . $e->getMessage());
    }
    
    $results[] = $result;
}

$processingTime = round(microtime(true) - $startTime, 2);

// Response
$response = [
    'success' => $created > 0,
    'total' => count($urls),
    'created' => $created,
    'failed' => $failed,
    'results' => $results,
    'processing_time' => $processingTime . 's',
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'total_links' => $user['total_links'] + $created
    ]
];

// Update user's total links
if ($created > 0) {
    $stmt = $pdo->prepare("UPDATE users SET total_links = total_links + ? WHERE id = ?");
    $stmt->execute([$created, $user['id']]);
}

http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT);