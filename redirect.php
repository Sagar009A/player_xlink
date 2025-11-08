<?php
// Error handling
// Set display_errors to 0 for production, 1 for debugging
ini_set('display_errors', 0); // Change to 1 to see errors on page
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/redirect_errors.log');

// Set UTF-8 encoding to prevent character display issues
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

session_start();

// Debug logging function - logs to file for troubleshooting
function debugLog($message) {
    error_log("[" . date('Y-m-d H:i:s') . "] REDIRECT DEBUG: $message");
}

// Check if required files exist
$required_files = [
    __DIR__ . '/config/database.php',
    __DIR__ . '/config/config.php',
    __DIR__ . '/includes/functions.php',
    __DIR__ . '/includes/security.php'
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        error_log("Redirect.php: Missing required file: $file");
        http_response_code(500);
        die("System configuration error. Please contact administrator.");
    }
}

try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/includes/functions.php';
    require_once __DIR__ . '/includes/security.php';
} catch (Exception $e) {
    error_log("Redirect.php: Error loading files: " . $e->getMessage());
    http_response_code(500);
    die("System error. Please contact administrator.");
}

// Get short code - handle both /code and ?code=xxx
$shortCode = '';
if (isset($_GET['code'])) {
    $shortCode = $_GET['code'];
} else {
    $uri = $_SERVER['REQUEST_URI'];
    $uri = explode('?', $uri)[0];
    $shortCode = ltrim($uri, '/');
}

$shortCode = sanitizeInput($shortCode);
$shortCode = rtrim($shortCode, '/');

debugLog("Processing short code: $shortCode");

// Allow both old (5-20 chars) and new (22-26 chars) short codes for backward compatibility
if (empty($shortCode) || strlen($shortCode) < 5) {
    debugLog("Invalid short code - redirecting to 404");
    header('Location: /error_404.php');
    exit;
}

// Get link from database
try {
    $stmt = $pdo->prepare("
        SELECT l.*, u.username, u.id as user_id 
        FROM links l 
        JOIN users u ON l.user_id = u.id 
        WHERE l.short_code = ? AND l.is_active = 1
    ");
    $stmt->execute([$shortCode]);
    $link = $stmt->fetch();

    if (!$link) {
        debugLog("Link not found for short code: $shortCode");
        header('Location: /error_404.php');
        exit;
    }
    
    debugLog("Link found: {$link['title']}");
} catch (PDOException $e) {
    error_log("Redirect.php: Database error: " . $e->getMessage());
    debugLog("Database error: " . $e->getMessage());
    http_response_code(500);
    die("<h1>Database Error</h1><p>Please try again later.</p><p>Details: " . htmlspecialchars($e->getMessage()) . "</p>");
}

// Check if direct video link exists and is not expired
$hasDirectVideo = false;
$needsRefresh = false;

if ($link['direct_video_url']) {
    if ($link['video_expires_at']) {
        $expiresAt = strtotime($link['video_expires_at']);
        if (time() < $expiresAt) {
            $hasDirectVideo = true;
        } else {
            $needsRefresh = true;
        }
    } else {
        $hasDirectVideo = true;
    }
}

// Refresh expired video link
if ($needsRefresh && $link['video_platform']) {
    try {
        if (file_exists(__DIR__ . '/services/ExtractorManager.php')) {
            if (file_exists(__DIR__ . '/extractors/AbstractExtractor.php')) {
                require_once __DIR__ . '/extractors/AbstractExtractor.php';
            }
            
            require_once __DIR__ . '/services/ExtractorManager.php';
            $manager = new ExtractorManager();
            $extractResult = $manager->extract($link['original_url'], ['refresh' => true]);
            
            if ($extractResult['success'] && isset($extractResult['data']['direct_link'])) {
                $newDirectUrl = $extractResult['data']['direct_link'];
                $newExpiresAt = null;
                
                if (isset($extractResult['data']['expires_at']) && $extractResult['data']['expires_at'] > 0) {
                    $newExpiresAt = date('Y-m-d H:i:s', $extractResult['data']['expires_at']);
                } elseif (isset($extractResult['data']['expires_in']) && $extractResult['data']['expires_in'] > 0) {
                    $newExpiresAt = date('Y-m-d H:i:s', time() + $extractResult['data']['expires_in']);
                }
                
                $stmt = $pdo->prepare("
                    UPDATE links 
                    SET direct_video_url = ?, video_expires_at = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$newDirectUrl, $newExpiresAt, $link['id']]);
                
                $link['direct_video_url'] = $newDirectUrl;
                $link['video_expires_at'] = $newExpiresAt;
                $hasDirectVideo = true;
            } else {
                error_log("Failed to refresh video link for ID {$link['id']}: " . ($extractResult['error'] ?? 'Unknown error'));
            }
        }
    } catch (Exception $e) {
        error_log("Exception refreshing video link: " . $e->getMessage());
    }
}

// Get user IP and info
$ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

// Detect device
$deviceType = 'desktop';
if (preg_match('/mobile|android|iphone|ipad|tablet/i', $userAgent)) {
    $deviceType = preg_match('/tablet|ipad/i', $userAgent) ? 'tablet' : 'mobile';
}

// Detect browser
$browser = 'Unknown';
if (preg_match('/Chrome/i', $userAgent)) $browser = 'Chrome';
elseif (preg_match('/Firefox/i', $userAgent)) $browser = 'Firefox';
elseif (preg_match('/Safari/i', $userAgent)) $browser = 'Safari';
elseif (preg_match('/Edge/i', $userAgent)) $browser = 'Edge';

$countryCode = 'XX';
$countryName = 'Unknown';

// Check if view should be counted
$shouldCount = shouldCountView($link['id'], $ipAddress, $link['user_id']);

// Calculate earnings
$earnings = 0;
if ($shouldCount) {
    $trafficSource = $link['traffic_source'] ?? null;
    $cpmData = calculateCPMRate($trafficSource, $countryCode);
    $earnings = ($cpmData['rate'] * $cpmData['multiplier']) / 1000;
}

// Log view
try {
    $stmt = $pdo->prepare("
        INSERT INTO views_log (
            link_id, user_id, ip_address, country_code, country_name,
            device_type, browser, referrer, is_counted, earnings
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $link['id'], $link['user_id'], $ipAddress, $countryCode, $countryName,
        $deviceType, $browser, $referer, $shouldCount ? 1 : 0, $earnings
    ]);

    if ($shouldCount) {
        $stmt = $pdo->prepare("
            UPDATE links 
            SET views = views + 1, earnings = earnings + ? 
            WHERE id = ?
        ");
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
} catch (PDOException $e) {
    error_log("Error logging view: " . $e->getMessage());
}

$waitTime = getSetting('redirect_wait_time', 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($link['title']) ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #0a0a0a;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #ffffff;
            min-height: 100vh;
        }
        
        /* Header */
        .header {
            background: #141414;
            padding: 15px 0;
            border-bottom: 1px solid #1f1f1f;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            text-decoration: none;
            letter-spacing: -0.5px;
        }
        
        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #ff0844 0%, #ff4d00 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(255, 8, 68, 0.4);
        }
        
        .logo-text {
            background: linear-gradient(135deg, #ff0844 0%, #ff4d00 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .btn-report {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-report:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        /* Video Section */
        .video-section {
            background: #141414;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
        }
        
        .video-player {
            position: relative;
            background: #000;
            width: 100%;
            height: 500px;
            cursor: pointer;
            overflow: hidden;
        }
        
        .video-thumbnail {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .video-player:hover .video-thumbnail {
            transform: scale(1.05);
        }
        
        .video-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0.8) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        
        .video-player:hover .video-overlay {
            background: linear-gradient(to bottom, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.85) 100%);
        }
        
        .play-button {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #ff0844 0%, #ff4d00 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 10px 40px rgba(255, 8, 68, 0.5);
            margin-bottom: 30px;
        }
        
        .video-player:hover .play-button {
            transform: scale(1.15);
            box-shadow: 0 15px 50px rgba(255, 8, 68, 0.7);
        }
        
        .play-button i {
            margin-left: 6px;
        }
        
        .video-info-overlay {
            text-align: center;
            padding: 0 30px;
        }
        
        .video-title-overlay {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 15px;
            color: white;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.8);
        }
        
        .play-text {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }
        
        /* Content Section */
        .content-section {
            padding: 30px;
            background: #141414;
            border-radius: 16px;
            margin-bottom: 30px;
        }
        
        .video-title {
            font-size: 26px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 25px;
            line-height: 1.4;
        }
        
        .uploader-card {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .uploader-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ff0844 0%, #ff4d00 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(255, 8, 68, 0.3);
        }
        
        .uploader-info {
            flex: 1;
        }
        
        .uploader-name {
            font-size: 18px;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 6px;
        }
        
        .video-stats {
            font-size: 14px;
            color: #888;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .video-stats i {
            color: #ff0844;
        }
        
        .platform-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: linear-gradient(135deg, #ff0844 0%, #ff4d00 100%);
            color: white;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ff0844 0%, #ff4d00 100%);
            color: white;
            border: none;
            padding: 18px 40px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 6px 25px rgba(255, 8, 68, 0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 35px rgba(255, 8, 68, 0.6);
        }
        
        .btn-primary:active {
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.1);
            padding: 18px 40px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
        }
        
        /* Benefits Section */
        .benefits-section {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .benefits-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .benefits-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 15px;
        }
        
        .benefits-title .highlight {
            background: linear-gradient(135deg, #ff0844 0%, #ff4d00 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .benefits-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }
        
        .benefit-item:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 8, 68, 0.3);
            transform: translateY(-3px);
        }
        
        .benefit-icon {
            font-size: 28px;
            flex-shrink: 0;
        }
        
        .benefit-text {
            flex: 1;
        }
        
        .benefit-text strong {
            color: #ffffff;
            font-weight: 600;
            font-size: 15px;
            display: block;
            margin-bottom: 5px;
        }
        
        .benefit-text p {
            color: #888;
            font-size: 13px;
            margin: 0;
            line-height: 1.5;
        }
        
        .join-button {
            text-align: center;
        }
        
        .btn-join {
            background: linear-gradient(135deg, #ff0844 0%, #ff4d00 100%);
            color: white;
            border: none;
            padding: 20px 60px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 8px 30px rgba(255, 8, 68, 0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-join:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(255, 8, 68, 0.7);
        }
        
        /* Footer */
        .footer {
            background: #141414;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .footer-text {
            color: #888;
            font-size: 14px;
            line-height: 1.8;
            margin-bottom: 20px;
        }
        
        .footer-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .footer-links a {
            color: #888;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s;
            font-weight: 500;
        }
        
        .footer-links a:hover {
            color: #ff0844;
        }
        
        /* Report Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }
        
        .modal-content {
            background: #1a1a1a;
            border-radius: 16px;
            padding: 40px;
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .modal-header h3 {
            margin: 0;
            color: #ffffff;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
            transition: color 0.3s;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #ffffff;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff0844;
            background: rgba(255, 255, 255, 0.08);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #ff0844 0%, #ff4d00 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 8, 68, 0.5);
        }
        
        .btn-cancel {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 14px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
        }
        
        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .success-message {
            display: none;
            text-align: center;
            padding: 40px 20px;
        }
        
        .success-icon {
            font-size: 64px;
            color: #00ff88;
            margin-bottom: 20px;
        }
        
        .success-message h4 {
            color: #ffffff;
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .success-message p {
            color: #888;
            line-height: 1.6;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }
            
            .logo {
                font-size: 20px;
            }
            
            .logo-icon {
                width: 38px;
                height: 38px;
                font-size: 20px;
            }
            
            .video-player {
                height: 280px;
            }
            
            .play-button {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
            
            .video-title-overlay {
                font-size: 20px;
            }
            
            .content-section,
            .benefits-section,
            .footer {
                padding: 20px;
            }
            
            .video-title {
                font-size: 20px;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .benefits-title {
                font-size: 28px;
            }
            
            .benefits-list {
                grid-template-columns: 1fr;
            }
            
            .form-buttons {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="/" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-play"></i>
                </div>
                <span class="logo-text"><?= SITE_NAME ?></span>
            </a>
            <button class="btn-report" onclick="reportContent()">
                <i class="fas fa-flag"></i> Report
            </button>
        </div>
    </div>
    
    <div class="container">
        
        <!-- Video Section -->
        <div class="video-section">
            <div class="video-player" onclick="openInApp()">
                <?php if ($link['thumbnail_path']): ?>
                <img src="<?= htmlspecialchars($link['thumbnail_path']) ?>" 
                     alt="Video Thumbnail" 
                     class="video-thumbnail">
                <?php else: ?>
                <div class="video-thumbnail" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-play-circle" style="font-size: 100px; color: rgba(255, 8, 68, 0.3);"></i>
                </div>
                <?php endif; ?>
                
                <div class="video-overlay">
                    <div class="play-button">
                        <i class="fas fa-play"></i>
                    </div>
                    <div class="video-info-overlay">
                        <h2 class="video-title-overlay"><?= htmlspecialchars($link['title']) ?></h2>
                        <div class="play-text">
                            <i class="fas fa-hand-pointer"></i> Tap to play with <?= SITE_NAME ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Content Section -->
        <div class="content-section">
            <h1 class="video-title"><?= htmlspecialchars($link['title']) ?></h1>
            
            <div class="uploader-card">
                <div class="uploader-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="uploader-info">
                    <div class="uploader-name"><?= htmlspecialchars($link['username']) ?></div>
                    <div class="video-stats">
                        <span><i class="fas fa-eye"></i> <?= number_format($link['views']) ?> views</span>
                        <?php if ($link['video_platform']): ?>
                        <span class="platform-badge">
                            <?= htmlspecialchars($link['video_platform']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <button onclick="openInApp()" class="btn-primary">
                    <i class="fas fa-play"></i> Play Now
                </button>
                <button onclick="handleDownload()" class="btn-secondary">
                    <i class="fas fa-download"></i> Download
                </button>
            </div>
        </div>
        
        <!-- Benefits Section -->
        <div class="benefits-section">
            <div class="benefits-header">
                <h2 class="benefits-title">JOIN <span class="highlight"><?= SITE_NAME ?></span> NOW</h2>
            </div>
            
            <div class="benefits-list">
                <div class="benefit-item">
                    <div class="benefit-icon">??</div>
                    <div class="benefit-text">
                        <strong>?? High CPM</strong>
                        <p>Earn up to $4 per 1000 views</p>
                    </div>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">??</div>
                    <div class="benefit-text">
                        <strong>?? Automation Tools</strong>
                        <p>Variety of tools to automate your earnings</p>
                    </div>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">??</div>
                    <div class="benefit-text">
                        <strong>?? Customer Support</strong>
                        <p>Top-notch customer support always available</p>
                    </div>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">?</div>
                    <div class="benefit-text">
                        <strong>? Instant Upload</strong>
                        <p>Upload & share your videos instantly</p>
                    </div>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">??</div>
                    <div class="benefit-text">
                        <strong>?? Secure Hosting</strong>
                        <p>Secure & reliable hosting</p>
                    </div>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">??</div>
                    <div class="benefit-text">
                        <strong>?? Easy Analytics</strong>
                        <p>Track your growth with easy analytics</p>
                    </div>
                </div>
            </div>
            
            <div class="join-button">
                <button class="btn-join" onclick="window.location.href='/user/register.php'">
                    ?? Join Now & Start Earning
                </button>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">
                This website only provide service of Video hosting. You can report the video that contains a problem 
                like copyright, Adult, violence, etc, we will not provide service for those videos.
            </p>
            <div class="footer-links">
                <a href="/copyright">Copyright Policy</a>
                <a href="/privacy">Privacy Policy</a>
                <a href="/terms">Terms & Conditions</a>
            </div>
        </div>
    </div>
    
    <!-- Report Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-flag"></i> Report Content</h3>
                <button class="modal-close" onclick="closeReportModal()">?</button>
            </div>
            
            <form id="reportForm">
                <div class="form-group">
                    <label>Reason for reporting:</label>
                    <select id="reportReason" required>
                        <option value="">Select a reason...</option>
                        <option value="copyright">Copyright Violation</option>
                        <option value="adult">Adult Content</option>
                        <option value="violence">Violence or Harmful Content</option>
                        <option value="spam">Spam or Misleading</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Additional details (optional):</label>
                    <textarea id="reportDetails" placeholder="Provide more information about the issue..."></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Report
                    </button>
                    <button type="button" onclick="closeReportModal()" class="btn-cancel">
                        Cancel
                    </button>
                </div>
            </form>
            
            <div id="reportSuccess" class="success-message">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4>Report Submitted</h4>
                <p>Thank you for helping us maintain a safe community. We'll review your report shortly.</p>
                <button onclick="closeReportModal()" class="btn-primary" style="margin-top: 25px;">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        let watchDuration = 0;
        
        /**
         * Smart App Redirect System
         * Automatically detects if app is installed and redirects accordingly:
         * - If app installed ? Opens app directly (deep link)
         * - If app not installed ? Redirects to Play Store/App Store
         * Works seamlessly across Chrome, Safari, Edge, and other browsers
         */
        
        // Enhanced App Redirect Function for Video Playback
        function openInApp() {
            const shortCode = '<?= $link['short_code'] ?>';
            const videoUrl = '<?= addslashes($link['original_url'] ?? '') ?>';
            const appScheme = '<?= APP_SCHEME ?>';
            const appPackage = '<?= APP_PACKAGE_ANDROID ?>';
            const playStoreUrl = '<?= PLAY_STORE_URL ?>';
            const appStoreUrl = '<?= APP_STORE_URL ?>';
            
            // Detect device platform
            const isAndroid = /Android/i.test(navigator.userAgent);
            const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
            const isMobile = isAndroid || isIOS;
            const isChrome = /Chrome/i.test(navigator.userAgent);
            const isSafari = /Safari/i.test(navigator.userAgent) && !isChrome;
            const isEdge = /Edg/i.test(navigator.userAgent);
            
            console.log('?? Platform Detection:', {
                isAndroid,
                isIOS,
                isMobile,
                browser: isChrome ? 'Chrome' : isSafari ? 'Safari' : isEdge ? 'Edge' : 'Other',
                userAgent: navigator.userAgent
            });
            
            if (!isMobile) {
                // Desktop: Show app download option
                if (confirm('This feature is available on our mobile app. Would you like to download it?')) {
                    window.open(playStoreUrl, '_blank');
                }
                return;
            }
            
            // Track app open attempt
            let appOpened = false;
            let redirectTimeout = null;
            const appCheckTimeout = 2000; // 2 seconds to check if app opens
            
            // Event listeners to detect if app opened successfully
            const handleBlur = () => {
                appOpened = true;
                console.log('? App opened successfully (blur event)');
                if (redirectTimeout) clearTimeout(redirectTimeout);
            };
            
            const handleVisibilityChange = () => {
                if (document.hidden) {
                    appOpened = true;
                    console.log('? App opened successfully (visibility change)');
                    if (redirectTimeout) clearTimeout(redirectTimeout);
                }
            };
            
            const handlePageHide = () => {
                appOpened = true;
                console.log('? App opened successfully (pagehide event)');
                if (redirectTimeout) clearTimeout(redirectTimeout);
            };
            
            // Attach event listeners
            window.addEventListener('blur', handleBlur, { once: true });
            document.addEventListener('visibilitychange', handleVisibilityChange, { once: true });
            window.addEventListener('pagehide', handlePageHide, { once: true });
            
            if (isAndroid) {
                // Android: Use Intent URL with actual video data
                // Format: intent://open?data=<video_url>#Intent;scheme=yourapp;package=com.teravideo.downloader;S.browser_fallback_url=<play_store>;end
                //const intentUrl = `intent://open?data=${encodeURIComponent(videoUrl)}#Intent;scheme=${appScheme};package=${appPackage};S.browser_fallback_url=${encodeURIComponent(playStoreUrl)};end`;
                
                const intentUrl = `intent://teraboxurll.in/<?= $link['short_code'] ?>#Intent;scheme=https;package=com.ymg.pricetracker;end`;
                
                console.log('?? Opening Android app with Intent URL:', intentUrl);
                console.log('?? Video URL being passed:', videoUrl);
                
                // Try opening with Intent URL (works best on Chrome/modern browsers)
                try {
                    window.location.href = intentUrl;
                } catch (e) {
                    console.error('? Intent URL failed:', e);
                }
                
                // Fallback: If app not opened after timeout, redirect to Play Store
                redirectTimeout = setTimeout(() => {
                    if (!appOpened && !document.hidden) {
                        console.log('?? App not detected, trying direct deep link...');
                        
                        // Try direct deep link as fallback with video data
                        const deepLink = `${appScheme}://open?data=${encodeURIComponent(videoUrl)}`;
                        window.location.href = deepLink;
                        
                        // Final fallback to Play Store
                        setTimeout(() => {
                            if (!appOpened && !document.hidden) {
                                console.log('?? Redirecting to Play Store');
                                window.location.href = playStoreUrl;
                            }
                        }, 1500);
                    }
                }, appCheckTimeout);
                
            } else if (isIOS) {
                // iOS: Use Universal Links or URL Scheme with video data
                const deepLink = `${appScheme}://open?data=${encodeURIComponent(videoUrl)}`;
                
                console.log('?? Opening iOS app with deep link:', deepLink);
                console.log('?? Video URL being passed:', videoUrl);
                
                // Try opening the app
                window.location.href = deepLink;
                
                // If app doesn't open, redirect to App Store
                redirectTimeout = setTimeout(() => {
                    if (!appOpened && !document.hidden) {
                        console.log('?? Redirecting to App Store');
                        window.location.href = appStoreUrl;
                    }
                }, appCheckTimeout);
            }
            
            // Cleanup event listeners after timeout
            setTimeout(() => {
                window.removeEventListener('blur', handleBlur);
                document.removeEventListener('visibilitychange', handleVisibilityChange);
                window.removeEventListener('pagehide', handlePageHide);
            }, appCheckTimeout + 2000);
        }
        
        // Download Handler with Smart Redirect
        function handleDownload() {
            const shortCode = '<?= $link['short_code'] ?>';
            const videoUrl = '<?= addslashes($link['original_url'] ?? '') ?>';
            const appScheme = '<?= APP_SCHEME ?>';
            const appPackage = '<?= APP_PACKAGE_ANDROID ?>';
            const playStoreUrl = '<?= PLAY_STORE_URL ?>';
            const appStoreUrl = '<?= APP_STORE_URL ?>';
            
            const isAndroid = /Android/i.test(navigator.userAgent);
            const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
            const isMobile = isAndroid || isIOS;
            
            if (!isMobile) {
                window.open(playStoreUrl, '_blank');
                return;
            }
            
            let appOpened = false;
            let redirectTimeout = null;
            const appCheckTimeout = 2000;
            
            const handleBlur = () => {
                appOpened = true;
                console.log('? Download - App opened successfully');
                if (redirectTimeout) clearTimeout(redirectTimeout);
            };
            
            const handleVisibilityChange = () => {
                if (document.hidden) {
                    appOpened = true;
                    console.log('? Download - App opened successfully');
                    if (redirectTimeout) clearTimeout(redirectTimeout);
                }
            };
            
            const handlePageHide = () => {
                appOpened = true;
                console.log('? Download - App opened successfully');
                if (redirectTimeout) clearTimeout(redirectTimeout);
            };
            
            window.addEventListener('blur', handleBlur, { once: true });
            document.addEventListener('visibilitychange', handleVisibilityChange, { once: true });
            window.addEventListener('pagehide', handlePageHide, { once: true });
            
            if (isAndroid) {
                // Android Intent URL for download action
                //const intentUrl = `intent://open?data=${encodeURIComponent(videoUrl)}&action=download#Intent;scheme=${appScheme};package=${appPackage};S.browser_fallback_url=${encodeURIComponent(playStoreUrl)};end`;
                
                const intentUrl = `intent://teraboxurll.in/<?= $link['short_code'] ?>#Intent;scheme=https;package=com.ymg.pricetracker;end`;
                
                console.log('?? Opening Android app for download:', intentUrl);
                console.log('?? Video URL being passed:', videoUrl);
                
                try {
                    window.location.href = intentUrl;
                } catch (e) {
                    console.error('? Intent URL failed:', e);
                }
                
                redirectTimeout = setTimeout(() => {
                    if (!appOpened && !document.hidden) {
                        const deepLink = `${appScheme}://open?data=${encodeURIComponent(videoUrl)}&action=download`;
                        window.location.href = deepLink;
                        
                        setTimeout(() => {
                            if (!appOpened && !document.hidden) {
                                console.log('?? Redirecting to Play Store');
                                window.location.href = playStoreUrl;
                            }
                        }, 1500);
                    }
                }, appCheckTimeout);
                
            } else if (isIOS) {
                const deepLink = `${appScheme}://open?data=${encodeURIComponent(videoUrl)}&action=download`;
                
                console.log('?? Opening iOS app for download:', deepLink);
                console.log('?? Video URL being passed:', videoUrl);
                window.location.href = deepLink;
                
                redirectTimeout = setTimeout(() => {
                    if (!appOpened && !document.hidden) {
                        console.log('?? Redirecting to App Store');
                        window.location.href = appStoreUrl;
                    }
                }, appCheckTimeout);
            }
            
            setTimeout(() => {
                window.removeEventListener('blur', handleBlur);
                document.removeEventListener('visibilitychange', handleVisibilityChange);
                window.removeEventListener('pagehide', handlePageHide);
            }, appCheckTimeout + 2000);
        }
        
        // Track duration
        const startTime = Date.now();
        window.addEventListener('beforeunload', function() {
            const duration = Math.floor((Date.now() - startTime) / 1000);
            if (duration > 0) {
                navigator.sendBeacon('/api/track_duration.php', JSON.stringify({
                    link_id: <?= $link['id'] ?>,
                    duration: duration,
                    watch_duration: watchDuration
                }));
            }
        });
        
        // Report Modal Functions
        function reportContent() {
            const modal = document.getElementById('reportModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeReportModal() {
            const modal = document.getElementById('reportModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            
            document.getElementById('reportForm').reset();
            document.getElementById('reportForm').style.display = 'block';
            document.getElementById('reportSuccess').style.display = 'none';
        }
        
        // Report form submission
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const reason = document.getElementById('reportReason').value;
            const details = document.getElementById('reportDetails').value;
            
            fetch('/api/report_content.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    link_id: <?= $link['id'] ?>,
                    short_code: '<?= $link['short_code'] ?>',
                    reason: reason,
                    details: details
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('reportForm').style.display = 'none';
                document.getElementById('reportSuccess').style.display = 'block';
            })
            .catch(error => {
                console.error('Error submitting report:', error);
                alert('Failed to submit report. Please try again.');
            });
        });
        
        // Close modal on outside click
        document.getElementById('reportModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeReportModal();
            }
        });
    </script>
</body>
</html>
<?php
// Ensure output is flushed
if (ob_get_length()) {
    ob_end_flush();
}
?>