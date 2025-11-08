<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/currencies.php';

// Generate Short Code
function generateShortCode($length = null) {
    global $pdo;
    
    // Generate random length between 22-26 if not specified
    if ($length === null) {
        $length = rand(22, 26);
    }
    
    do {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Check if code exists
        $stmt = $pdo->prepare("SELECT id FROM links WHERE short_code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetch();
    } while ($exists);
    
    return $code;
}

// Check if custom alias is available
function isAliasAvailable($alias, $excludeLinkId = null) {
    global $pdo;
    
    $query = "SELECT id FROM links WHERE custom_alias = ?";
    $params = [$alias];
    
    if ($excludeLinkId) {
        $query .= " AND id != ?";
        $params[] = $excludeLinkId;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return !$stmt->fetch();
}

// Generate QR Code
function generateQRCode($shortCode, $customAlias = null) {
    $url = SITE_URL . '/' . ($customAlias ?: $shortCode);
    $filename = ($customAlias ?: $shortCode) . '.png';
    $filepath = QR_CODE_PATH . '/' . $filename;
    
    // Using Google Charts API for QR Code generation (simple and free)
    $qrUrl = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . urlencode($url) . "&choe=UTF-8";
    
    $ch = curl_init($qrUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $qrImage = curl_exec($ch);
    curl_close($ch);
    
    if ($qrImage) {
        file_put_contents($filepath, $qrImage);
        return '/uploads/qrcodes/' . $filename;
    }
    
    return null;
}

// Fetch Video Thumbnail from URL
function fetchThumbnail($url) {
    // Extract video ID based on platform
    $thumbnail = null;
    
    // YouTube
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $videoId = $matches[1];
        $thumbnail = "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg";
    }
    
    // Vimeo
    elseif (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
        $videoId = $matches[1];
        $ch = curl_init("https://vimeo.com/api/v2/video/{$videoId}.json");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            $thumbnail = $data[0]['thumbnail_large'] ?? null;
        }
    }
    
    // Dailymotion
    elseif (preg_match('/dailymotion\.com\/video\/([a-zA-Z0-9]+)/', $url, $matches)) {
        $videoId = $matches[1];
        $thumbnail = "https://www.dailymotion.com/thumbnail/video/{$videoId}";
    }
    
    // TheraBox / Custom platforms - Try to fetch via OpenGraph
    else {
        $thumbnail = fetchOGImage($url);
    }
    
    // Download and save thumbnail
    if ($thumbnail) {
        return downloadAndSaveThumbnail($thumbnail);
    }
    
    return null;
}

// Fetch OpenGraph Image
function fetchOGImage($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $html = curl_exec($ch);
    curl_close($ch);
    
    if ($html && preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
        return $matches[1];
    }
    
    return null;
}

// Download and Save Thumbnail
function downloadAndSaveThumbnail($thumbnailUrl) {
    $filename = md5($thumbnailUrl . time()) . '.jpg';
    $filepath = THUMBNAIL_PATH . '/' . $filename;
    
    $ch = curl_init($thumbnailUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $imageData) {
        file_put_contents($filepath, $imageData);
        return '/uploads/thumbnails/' . $filename;
    }
    
    return null;
}

// Extract Video Title
function extractVideoTitle($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $html = curl_exec($ch);
    curl_close($ch);
    
    if ($html) {
        // Try OpenGraph title
        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        }
        // Try regular title tag
        if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        }
    }
    
    return 'Untitled Video';
}

// Calculate CPM Rate based on traffic source and country
function calculateCPMRate($trafficSource, $countryCode) {
    global $pdo;
    
    // Get all active rates ordered by priority
    $stmt = $pdo->prepare("
        SELECT * FROM cpm_rates 
        WHERE is_active = 1 
        ORDER BY priority DESC
    ");
    $stmt->execute();
    $rates = $stmt->fetchAll();
    
    $matchedRate = null;
    
    foreach ($rates as $rate) {
        // Check country match
        $countries = explode(',', $rate['countries']);
        $countryMatch = in_array('*', $countries) || in_array($countryCode, $countries);
        
        // Check traffic source match
        $sourceMatch = empty($rate['traffic_source']) || $rate['traffic_source'] === $trafficSource;
        
        if ($countryMatch && $sourceMatch) {
            $matchedRate = $rate;
            break;
        }
    }
    
    if (!$matchedRate) {
        // Fallback to default rate
        $defaultRate = getSetting('default_cpm_rate', 1.0000);
        return ['rate' => floatval($defaultRate), 'multiplier' => 1.00];
    }
    
    return [
        'rate' => floatval($matchedRate['rate_per_1000']),
        'multiplier' => floatval($matchedRate['bonus_multiplier'])
    ];
}

// Check if view should be counted
function shouldCountView($linkId, $ip, $userId) {
    global $pdo;
    
    // Check if same IP viewed this link in last 24 hours
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM views_log 
        WHERE link_id = ? 
        AND ip_address = ? 
        AND viewed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$linkId, $ip]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        return false; // Duplicate view
    }
    
    // Check daily IP limit for this user's links
    $dailyLimit = getSetting('daily_view_limit_per_ip', 50);
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM views_log 
        WHERE user_id = ? 
        AND ip_address = ? 
        AND DATE(viewed_at) = CURDATE()
    ");
    $stmt->execute([$userId, $ip]);
    $result = $stmt->fetch();
    
    if ($result['count'] >= $dailyLimit) {
        return false; // Daily limit reached
    }
    
    return true;
}

// Log View and Calculate Earnings
function logView($linkId, $userId, $ip, $countryCode, $countryName, $referrer = '') {
    global $pdo;
    
    // Check if view should be counted
    if (!shouldCountView($linkId, $ip, $userId)) {
        // Still log but mark as not counted
        $stmt = $pdo->prepare("
            INSERT INTO views_log (link_id, user_id, ip_address, country_code, country_name, 
                                   device_type, browser, os, referrer, is_counted, hour_of_day)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, HOUR(NOW()))
        ");
        $stmt->execute([
            $linkId, $userId, $ip, $countryCode, $countryName,
            getDeviceType(), getBrowser(), getOS(), $referrer
        ]);
        return false;
    }
    
    // Get link and user info
    $stmt = $pdo->prepare("SELECT l.*, u.traffic_source FROM links l JOIN users u ON l.user_id = u.id WHERE l.id = ?");
    $stmt->execute([$linkId]);
    $link = $stmt->fetch();
    
    if (!$link) {
        return false;
    }
    
    // Calculate CPM rate
    $cpmData = calculateCPMRate($link['traffic_source'], $countryCode);
    $earnings = ($cpmData['rate'] * $cpmData['multiplier']) / 1000;
    
    // Log the view
    $stmt = $pdo->prepare("
        INSERT INTO views_log (link_id, user_id, ip_address, country_code, country_name, 
                               device_type, browser, os, referrer, is_counted, hour_of_day)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, HOUR(NOW()))
    ");
    $stmt->execute([
        $linkId, $userId, $ip, $countryCode, $countryName,
        getDeviceType(), getBrowser(), getOS(), $referrer
    ]);
    
    // Update link stats
    $stmt = $pdo->prepare("
        UPDATE links 
        SET views = views + 1, 
            today_views = today_views + 1, 
            earnings = earnings + ?,
            last_view_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$earnings, $linkId]);
    
    // Update user stats
    $stmt = $pdo->prepare("
        UPDATE users 
        SET total_views = total_views + 1, 
            total_earnings = total_earnings + ?,
            balance = balance + ?
        WHERE id = ?
    ");
    $stmt->execute([$earnings, $earnings, $userId]);
    
    // Check for referral commission
    $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user && $user['referred_by']) {
        $commissionPercent = floatval(getSetting('referral_commission', 10));
        $commissionAmount = ($earnings * $commissionPercent) / 100;
        
        // Add referral earnings
        $stmt = $pdo->prepare("
            INSERT INTO referral_earnings (referrer_id, referred_user_id, amount, commission_percent)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user['referred_by'], $userId, $commissionAmount, $commissionPercent]);
        
        // Update referrer balance
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$commissionAmount, $user['referred_by']]);
    }
    
    return true;
}

// Update Today Views (Reset daily)
function resetTodayViews() {
    global $pdo;
    
    $lastReset = getSetting('last_view_reset', date('Y-m-d', strtotime('-1 day')));
    
    if ($lastReset < date('Y-m-d')) {
        $pdo->query("UPDATE links SET today_views = 0");
        updateSetting('last_view_reset', date('Y-m-d'));
    }
}

// Bulk Stats Update (called by cron or admin)
function updateAllStats() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT id FROM users WHERE status = 'approved'");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        updateUserStats($user['id']);
    }
    
    updateSetting('last_stats_update', date('Y-m-d H:i:s'));
    return count($users);
}

// Update Single User Stats
function updateUserStats($userId) {
    global $pdo;
    
    // Recalculate total views
    $stmt = $pdo->prepare("
        SELECT SUM(views) as total_views, SUM(earnings) as total_earnings 
        FROM links 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    
    // Update user
    $stmt = $pdo->prepare("
        UPDATE users 
        SET total_views = ?, 
            total_earnings = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $stats['total_views'] ?? 0,
        $stats['total_earnings'] ?? 0,
        $userId
    ]);
    
    return true;
}

// Get Traffic Analytics
function getTrafficAnalytics($userId, $linkId = null, $days = 30) {
    global $pdo;
    
    $query = "
        SELECT 
            DATE(viewed_at) as date,
            COUNT(*) as views,
            COUNT(DISTINCT country_code) as unique_countries,
            COUNT(DISTINCT ip_address) as unique_ips
        FROM views_log
        WHERE user_id = ? 
        AND viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        AND is_counted = 1
    ";
    
    $params = [$userId, $days];
    
    if ($linkId) {
        $query .= " AND link_id = ?";
        $params[] = $linkId;
    }
    
    $query .= " GROUP BY DATE(viewed_at) ORDER BY date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// Get Country-wise Stats
function getCountryStats($userId, $linkId = null) {
    global $pdo;
    
    $query = "
        SELECT 
            country_code,
            country_name,
            COUNT(*) as views,
            COUNT(DISTINCT ip_address) as unique_visitors
        FROM views_log
        WHERE user_id = ? AND is_counted = 1
    ";
    
    $params = [$userId];
    
    if ($linkId) {
        $query .= " AND link_id = ?";
        $params[] = $linkId;
    }
    
    $query .= " GROUP BY country_code ORDER BY views DESC LIMIT 20";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// Get Device Stats
function getDeviceStats($userId, $linkId = null) {
    global $pdo;
    
    $query = "
        SELECT 
            device_type,
            COUNT(*) as views,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM views_log WHERE user_id = ? AND is_counted = 1), 2) as percentage
        FROM views_log
        WHERE user_id = ? AND is_counted = 1
    ";
    
    $params = [$userId, $userId];
    
    if ($linkId) {
        $query .= " AND link_id = ?";
        $params[] = $linkId;
    }
    
    $query .= " GROUP BY device_type ORDER BY views DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// Get Peak Hours
function getPeakHours($userId, $linkId = null) {
    global $pdo;
    
    $query = "
        SELECT 
            hour_of_day,
            COUNT(*) as views
        FROM views_log
        WHERE user_id = ? AND is_counted = 1
    ";
    
    $params = [$userId];
    
    if ($linkId) {
        $query .= " AND link_id = ?";
        $params[] = $linkId;
    }
    
    $query .= " GROUP BY hour_of_day ORDER BY hour_of_day";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// Get Browser/OS Stats
function getBrowserOSStats($userId, $linkId = null) {
    global $pdo;
    
    $query = "
        SELECT 
            browser,
            os,
            COUNT(*) as views
        FROM views_log
        WHERE user_id = ? AND is_counted = 1
    ";
    
    $params = [$userId];
    
    if ($linkId) {
        $query .= " AND link_id = ?";
        $params[] = $linkId;
    }
    
    $query .= " GROUP BY browser, os ORDER BY views DESC LIMIT 10";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}