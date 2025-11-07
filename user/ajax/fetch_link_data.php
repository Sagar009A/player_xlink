<?php
session_start();
header('Content-Type: application/json');

// Enable error logging but don't display errors
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// === RATE LIMITING / COOLDOWN CHECK ===
// Prevent rapid auto-fetch clicks that trigger TeraBox rate limiting
$cooldownKey = 'auto_fetch_cooldown_' . $_SESSION['user_id'];
$lastFetchTime = $_SESSION[$cooldownKey] ?? 0;
$currentTime = time();
$cooldownPeriod = 2; // Reduced to 2 seconds for instant generation

if (($currentTime - $lastFetchTime) < $cooldownPeriod) {
    $waitTime = $cooldownPeriod - ($currentTime - $lastFetchTime);
    echo json_encode([
        'success' => false,
        'message' => "Please wait {$waitTime} seconds before trying again",
        'error_type' => 'cooldown',
        'help' => 'This prevents TeraBox from blocking your requests'
    ]);
    exit;
}

// Update last fetch time
$_SESSION[$cooldownKey] = $currentTime;

$url = $_POST['url'] ?? '';

if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid URL']);
    exit;
}

try {
    // Detect if it's a TeraBox link for instant extraction
    $isTeraBox = false;
    $teraboxDomains = ['terabox.com', '1024terabox.com', 'teraboxapp.com', '4funbox.com', 
                       'mirrobox.com', 'teraboxlink.com', 'teraboxurl.com', 'terasharefile.com'];
    
    $urlHost = parse_url($url, PHP_URL_HOST);
    if ($urlHost) {
        foreach ($teraboxDomains as $domain) {
            if (strpos($urlHost, $domain) !== false) {
                $isTeraBox = true;
                break;
            }
        }
    }
    
    // Only use instant extraction for TeraBox links
    if ($isTeraBox) {
        $instantApiUrl = SITE_URL . '/api/instant_extract.php?url=' . urlencode($url);
        $ch = curl_init($instantApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible)');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode === 200) {
            $data = json_decode($response, true);
            
            if ($data['success']) {
                $extractData = $data['data'];
                
                // Properly handle expiry information
                $expiresIn = $extractData['expires_in'] ?? 0;
                $expiresAt = $extractData['expires_at'] ?? null;
                $expiresFormatted = $extractData['expires_at_formatted'] ?? null;
                
                echo json_encode([
                    'success' => true,
                    'title' => $extractData['filename'] ?? $extractData['title'] ?? 'Video',
                    'thumbnail' => $extractData['thumbnail'] ?? null,
                    'description' => null,
                    'source' => $extractData['platform'] ?? 'terabox',
                    'has_direct_link' => !empty($extractData['direct_link']),
                    'direct_link' => $extractData['direct_link'] ?? null,
                    'video_quality' => $extractData['quality'] ?? 'Unknown',
                    'video_size' => $extractData['size_formatted'] ?? null,
                    'expires_in' => $expiresIn,
                    'expires_at' => $expiresAt,
                    'expires_at_formatted' => $expiresFormatted,
                    'has_expiry' => $expiresIn > 0,
                    'platform_detected' => $extractData['platform'] ?? 'terabox',
                    'instant_generation' => true
                ]);
                exit;
            }
        }
    }
    
    // For non-TeraBox links or if instant extraction failed, use ExtractorManager
    if (file_exists(__DIR__ . '/../../services/ExtractorManager.php')) {
        // Load AbstractExtractor FIRST - critical for class inheritance
        if (file_exists(__DIR__ . '/../../extractors/AbstractExtractor.php')) {
            require_once __DIR__ . '/../../extractors/AbstractExtractor.php';
        }
        
        // Ensure database is loaded for extractors that need it
        if (!isset($GLOBALS['pdo'])) {
            $GLOBALS['pdo'] = $pdo; // Set global database connection
        }
        
        require_once __DIR__ . '/../../services/ExtractorManager.php';
        
        $manager = new ExtractorManager();
        $extractResult = $manager->extract($url, ['skip_cache' => false]);
        
        if ($extractResult['success']) {
            $data = $extractResult['data'];
            
            // Properly handle expiry information
            $expiresIn = $data['expires_in'] ?? 0;
            $expiresAt = $data['expires_at'] ?? null;
            $expiresFormatted = $data['expires_at_formatted'] ?? null;
            
            echo json_encode([
                'success' => true,
                'title' => $data['filename'] ?? $data['title'] ?? 'Video',
                'thumbnail' => $data['thumbnail'] ?? null,
                'description' => null,
                'source' => $extractResult['platform'],
                'has_direct_link' => !empty($data['direct_link']),
                'direct_link' => $data['direct_link'] ?? null,
                'video_quality' => $data['quality'] ?? 'Unknown',
                'video_size' => $data['size_formatted'] ?? null,
                'expires_in' => $expiresIn,
                'expires_at' => $expiresAt,
                'expires_at_formatted' => $expiresFormatted,
                'has_expiry' => $expiresIn > 0,
                'platform_detected' => $extractResult['platform'],
                'instant_generation' => false
            ]);
            exit;
        } else {
            // ExtractorManager failed - get error details
            $errorMsg = $extractResult['message'] ?? 'Could not extract video';
            $errorCode = $extractResult['error'] ?? 'unknown';
        }
    }
    
    // Extraction failed - provide helpful error message based on error type
    if (isset($errorCode)) {
        if ($errorCode === 'verification_required' || (isset($errorMsg) && (strpos($errorMsg, 'CAPTCHA') !== false || strpos($errorMsg, 'verification') !== false))) {
            echo json_encode([
                'success' => false,
                'message' => '⚠️ TeraBox CAPTCHA Verification Required',
                'error_type' => 'rate_limit',
                'help' => 'TeraBox has detected automated requests. This is temporary.\n\n' .
                          '✅ Solution: Wait 5-10 minutes and try again\n' .
                          '💡 Tip: You can still create the link manually - it will auto-fetch video data when users visit'
            ]);
            exit;
        }
        
        if ($errorCode === 'token_failed') {
            echo json_encode([
                'success' => false,
                'message' => '⚙️ TeraBox Service Update In Progress',
                'error_type' => 'token_error',
                'help' => 'The system is refreshing tokens. Please wait 30-60 seconds and try again.\n\n' .
                          '💡 Tip: You can create the link now - it will work when tokens are refreshed'
            ]);
            exit;
        }
        
        if ($errorCode === 'invalid_url' || $errorCode === 'invalid_link') {
            echo json_encode([
                'success' => false,
                'message' => '❌ Invalid or Expired TeraBox Link',
                'error_type' => 'invalid_link',
                'help' => 'Please check:\n' .
                          '• Link is correct and complete\n' .
                          '• File still exists on TeraBox\n' .
                          '• Link is publicly shared (not private)'
            ]);
            exit;
        }
        
        if ($errorCode === 'access_denied') {
            echo json_encode([
                'success' => false,
                'message' => '🔒 Access Denied',
                'error_type' => 'access_denied',
                'help' => 'File may be:\n' .
                          '• Password protected\n' .
                          '• Deleted or removed\n' .
                          '• Set to private\n\n' .
                          '💡 Solution: Make sure the file is publicly accessible'
            ]);
            exit;
        }
        
        if ($errorCode === 'connection_failed') {
            echo json_encode([
                'success' => false,
                'message' => '🌐 Connection Issue with TeraBox',
                'error_type' => 'connection_error',
                'help' => 'This is usually temporary.\n\n' .
                          '✅ Solution: Wait 1-2 minutes and try again\n' .
                          '💡 Note: TeraBox may be blocking rapid requests\n' .
                          '⚡ You can still create the link - it will work for visitors'
            ]);
            exit;
        }
        
        if ($errorCode === 'extraction_failed') {
            echo json_encode([
                'success' => false,
                'message' => '💡 Failed to extract video information',
                'error_type' => 'extraction_failed',
                'help' => 'The link may be invalid or TeraBox is temporarily blocking requests.\n\n' .
                          '✅ Solutions:\n' .
                          '• Wait a few minutes and try again\n' .
                          '• Create the link manually (add title yourself)\n' .
                          '• Video will still work when visitors click your short link'
            ]);
            exit;
        }
        
        if ($errorCode === 'unsupported_platform') {
            echo json_encode([
                'success' => false,
                'message' => '⚠️ Unsupported Platform',
                'error_type' => 'unsupported_platform',
                'help' => 'Supported platforms:\n' .
                          '• TeraBox (all domains)\n' .
                          '• Diskwala\n' .
                          '• StreamTape\n' .
                          '• Streaam.net\n' .
                          '• NowPlayToc\n' .
                          '• VividCast\n' .
                          '• GoFile\n' .
                          '• FileMoon\n' .
                          '• Direct Video Links (.mp4, .webm, etc.)'
            ]);
            exit;
        }
        
        // Generic error with actual message
        echo json_encode([
            'success' => false,
            'message' => $errorMsg ?? 'Could not extract data from this URL',
            'error_type' => $errorCode ?? 'unknown',
            'help' => 'If this persists, try a different link or platform'
        ]);
        exit;
    }
    
    // Fallback: YouTube
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
        preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches);
        $videoId = $matches[1] ?? null;
        
        if ($videoId) {
            echo json_encode([
                'success' => true,
                'title' => 'YouTube Video',
                'thumbnail' => "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg",
                'source' => 'youtube',
                'has_direct_link' => false,
                'instant_generation' => false
            ]);
            exit;
        }
    }
    
    // Generic fallback
    echo json_encode([
        'success' => false,
        'message' => 'Could not extract data from this URL',
        'help' => 'Supported: Public TeraBox links, Diskwala, StreamTape, YouTube (thumbnail only)'
    ]);
    
} catch (Exception $e) {
    // Log the full error for debugging
    error_log("fetch_link_data.php Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return user-friendly error
    echo json_encode([
        'success' => false,
        'message' => 'System error: ' . $e->getMessage(),
        'error_type' => 'system_error',
        'help' => 'Please try again. If the problem persists, contact support.'
    ]);
}
?>