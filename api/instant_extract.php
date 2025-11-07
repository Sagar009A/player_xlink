<?php
/**
 * Instant Video Extractor API v3.0
 * Direct JSON generation without captcha issues
 * Multiple token sources and fallback methods
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/instant_extract.log');

// Disable display errors in production
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Function to send error response
function sendError($message, $code = 500, $details = null) {
    http_response_code($code);
    $response = [
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if ($details) {
        $response['details'] = $details;
    }
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Function to send success response
function sendSuccess($data, $message = 'Success') {
    http_response_code(200);
    $response = [
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'api_version' => '3.0'
    ];
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Get URL from request
$url = $_GET['url'] ?? $_POST['url'] ?? '';

if (empty($url)) {
    sendError('URL parameter is required', 400);
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    sendError('Invalid URL format', 400);
}

// Extract short code from TeraBox URL
function extractShortCode($url) {
    // Handle various TeraBox URL formats
    $patterns = [
        '/\/s\/([^\/\?]+)/',
        '/surl=([^&]+)/',
        '/sharing\/link\?surl=([^&]+)/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

$shortCode = extractShortCode($url);

if (!$shortCode) {
    sendError('Invalid TeraBox URL format', 400);
}

// Multiple token sources for instant access
function getTokenFromMultipleSources() {
    $tokenSources = [
        // Source 1: Database cache
        function() {
            try {
                require_once __DIR__ . '/../config/database.php';
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'terabox_js_token'");
                $stmt->execute();
                $result = $stmt->fetch();
                return $result ? trim($result['setting_value']) : null;
            } catch (Exception $e) {
                return null;
            }
        },
        
        // Source 2: External API
        function() {
            $tokenUrl = 'https://ntmtemp.xyz/token.txt';
            $ch = curl_init($tokenUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible)');
            $token = trim(curl_exec($ch));
            curl_close($ch);
            return $token ?: null;
        },
        
        // Source 3: Alternative external source
        function() {
            $tokenUrl = 'https://api.terabox-token.com/token';
            $ch = curl_init($tokenUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible)');
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                return $data['token'] ?? null;
            }
            return null;
        },
        
        // Source 4: Direct TeraBox page scraping
        function() {
            $sampleUrls = [
                'https://www.terabox.com/sharing/link?surl=1example',
                'https://www.terabox.app/sharing/link?surl=1example'
            ];
            
            foreach ($sampleUrls as $url) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
                curl_setopt($ch, CURLOPT_TIMEOUT, 12);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9'
                ]);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                if ($response) {
                    // Extract token from HTML/JS
                    if (preg_match('/jsToken["\']?\s*[:=]\s*["\']([A-F0-9]{64,})["\']/', $response, $matches)) {
                        return $matches[1];
                    }
                }
            }
            return null;
        }
    ];
    
    // Try each source until we get a valid token
    foreach ($tokenSources as $source) {
        try {
            $token = $source();
            if ($token && strlen($token) >= 64 && preg_match('/^[A-Fa-f0-9]+$/', $token)) {
                return $token;
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    return null;
}

// Get token from multiple sources
$token = getTokenFromMultipleSources();

if (!$token) {
    sendError('Unable to obtain valid token from any source', 503);
}

// Fetch video data using the token - Returns full API response like terabox_share_info.php
function fetchVideoData($shortCode, $token) {
    $apiUrl = "https://www.terabox.com/api/shorturlinfo?" . http_build_query([
        "app_id" => "250528",
        "web" => "1",
        "channel" => "dubox",
        "clienttype" => "0",
        "jsToken" => $token,
        "dp-logid" => "35980000896792010019",
        "shorturl" => $shortCode,
        "root" => "1",
        "scene" => ""
    ]);
    
    $headers = [
        "Host: www.terabox.app",
        "Cookie: browserid=ArBvk6M0xQdGymnG39wFu9_Y-XtkB-PAYReRtXIrWSYDC1MdrwIFqWZXhpc=; TSID=NtYlMiAJGEoeW5WueA2nJVkgsJFqpVK7; __bid_n=197358ca9d98712cf34207; _ga=GA1.1.1172885542.1748950101; lang=en; ndus=Y4AThvEteHui_dpx27xN7s-lZY8wOepXzeyaN_IA; csrfToken=NmlcKtX7UofCC7LAP00cMkEd; ndut_fmt=D56A2DB8F8F88F74D931798D21655E28E1E1EB08FD1A235AF4D5B52847390EE1; ndut_fmv=b37a866305518c23d8fe102c9805c5fc32e463ee7434cc89a239b701e9c67ff9683d177535da5ab734d1fdcc6d1ee790539eb63a954c0db4797a0216c389d3ee5e3c8b4ed66c88e193ffc5218175d9e4037c379b7fc7b010a85cc38cbc87dce1c2d75811cedc9080864cf1dd671668f9; _ga_06ZNKL8C2E=GS2.1.1753013452.9.0.1753013452.60.0.0",
        "X-Requested-With: XMLHttpRequest",
        "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36",
        "Accept: application/json, text/plain, */*",
        "Content-Type: application/x-www-form-urlencoded",
        "Referer: https://www.terabox.app/sharing/link?surl=" . $shortCode,
        "Accept-Encoding: gzip, deflate, br",
        "Accept-Language: en-GB,en-US;q=0.9,en;q=0.8",
        "Connection: keep-alive"
    ];
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_ENCODING, ""); // Auto decode gzip
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || $curlError) {
        return [
            'success' => false,
            'error' => 'connection_failed',
            'message' => 'Failed to connect to TeraBox API: ' . $curlError
        ];
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        return [
            'success' => false,
            'error' => 'invalid_response',
            'message' => 'Invalid response from TeraBox API'
        ];
    }
    
    if (!isset($data['errno']) || $data['errno'] !== 0) {
        $errorMsg = $data['errmsg'] ?? $data['msg'] ?? 'Unknown error';
        return [
            'success' => false,
            'error' => 'api_error',
            'message' => 'TeraBox API error: ' . $errorMsg,
            'errno' => $data['errno'] ?? null
        ];
    }
    
    $file = $data['list'][0] ?? null;
    
    if (!$file) {
        return [
            'success' => false,
            'error' => 'no_file',
            'message' => 'No file found in the shared link'
        ];
    }
    
    // Extract video information - FULL DATA like terabox_share_info.php
    $directUrl = $file['dlink'] ?? null;
    $title = $file['server_filename'] ?? 'Untitled Video';
    $size = $file['size'] ?? 0;
    
    // Determine quality from filename or size
    $quality = 'Unknown';
    if (strpos($title, '1080') !== false || strpos($title, 'HD') !== false) {
        $quality = '1080p';
    } elseif (strpos($title, '720') !== false) {
        $quality = '720p';
    } elseif (strpos($title, '480') !== false) {
        $quality = '480p';
    } elseif ($size > 100 * 1024 * 1024) {
        $quality = 'High';
    } elseif ($size > 50 * 1024 * 1024) {
        $quality = 'Medium';
    } else {
        $quality = 'Standard';
    }
    
    // Calculate expiry from dlink
    $expiresIn = 3600; // Default 1 hour
    $expiresAt = time() + $expiresIn;
    
    if ($directUrl) {
        $parsedUrl = parse_url($directUrl);
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $params);
            $expiryParams = ['expire', 'expires', 'exp', 'expiry'];
            
            foreach ($expiryParams as $param) {
                if (isset($params[$param]) && is_numeric($params[$param])) {
                    $timestamp = $params[$param];
                    if ($timestamp > time()) {
                        $expiresIn = $timestamp - time();
                        $expiresAt = $timestamp;
                        break;
                    }
                }
            }
        }
    }
    
    // Process thumbs array to get video URLs (not just thumbnails)
    $thumbs = $file['thumbs'] ?? [];
    $videoUrls = [];
    
    // Extract all video URLs from thumbs
    if (!empty($thumbs)) {
        foreach ($thumbs as $key => $url) {
            if (!empty($url) && is_string($url)) {
                $videoUrls[$key] = $url;
            }
        }
    }
    
    // Get the best quality video URL from thumbs
    $videoUrl = null;
    if (isset($thumbs['url4'])) {
        $videoUrl = $thumbs['url4'];
    } elseif (isset($thumbs['url3'])) {
        $videoUrl = $thumbs['url3'];
    } elseif (isset($thumbs['url2'])) {
        $videoUrl = $thumbs['url2'];
    } elseif (isset($thumbs['url1'])) {
        $videoUrl = $thumbs['url1'];
    }
    
    // Return FULL data structure with all details
    return [
        'success' => true,
        'data' => [
            // Basic info
            'title' => $title,
            'filename' => $title,
            'size' => $size,
            'size_formatted' => formatBytes($size),
            'quality' => $quality,
            'platform' => 'terabox',
            'short_code' => $shortCode,
            
            // Video URLs - Complete data
            'video' => $videoUrl,  // Best quality video URL
            'direct_link' => $directUrl,  // dlink for direct download
            'thumbnail' => $thumbs['url3'] ?? $thumbs['url2'] ?? $thumbs['url1'] ?? null,
            
            // Complete thumbs/urls array (all quality options)
            'urls' => $videoUrls,
            'thumbs' => $thumbs,
            
            // Expiry info
            'expires_in' => $expiresIn,
            'expires_at' => $expiresAt,
            'expires_at_formatted' => date('Y-m-d H:i:s', $expiresAt),
            
            // Full file metadata from API
            'category' => $file['category'] ?? null,
            'fs_id' => $file['fs_id'] ?? null,
            'isdir' => $file['isdir'] ?? 0,
            'local_ctime' => $file['local_ctime'] ?? null,
            'local_mtime' => $file['local_mtime'] ?? null,
            'path' => $file['path'] ?? null,
            'server_ctime' => $file['server_ctime'] ?? null,
            'server_mtime' => $file['server_mtime'] ?? null,
            'md5' => $file['md5'] ?? null
        ]
    ];
}

// Format bytes to human readable
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Fetch video data
$result = fetchVideoData($shortCode, $token);

if ($result['success']) {
    sendSuccess($result['data'], 'Video data extracted successfully');
} else {
    sendError($result['message'], 400, ['error_code' => $result['error']]);
}
?>
