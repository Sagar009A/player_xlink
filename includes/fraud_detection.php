<?php
/**
 * Smart Fraud Detection System
 * Detects suspicious activities and bot traffic
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Detect fraudulent activity for a user
 */
function detectFraudulentActivity($userId, $hours = 24) {
    global $pdo;
    $alerts = [];
    
    // 1. Rapid Click Detection (same IP, multiple views in short time)
    $stmt = $pdo->prepare("
        SELECT ip_address, COUNT(*) as click_count,
               MIN(viewed_at) as first_click,
               MAX(viewed_at) as last_click
        FROM views_log
        WHERE user_id = ? AND viewed_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        GROUP BY ip_address
        HAVING click_count >= 10 
        AND TIMESTAMPDIFF(MINUTE, first_click, last_click) <= 5
    ");
    $stmt->execute([$userId, $hours]);
    $rapidClicks = $stmt->fetchAll();
    
    foreach ($rapidClicks as $click) {
        $alerts[] = [
            'type' => 'Rapid Click Fraud',
            'severity' => 'high',
            'message' => "IP {$click['ip_address']} made {$click['click_count']} clicks in 5 minutes",
            'detected_at' => date('Y-m-d H:i:s'),
            'data' => $click
        ];
    }
    
    // 2. Bot Traffic Detection (suspicious user agents)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as bot_count
        FROM views_log
        WHERE user_id = ? 
        AND viewed_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        AND (browser = 'Unknown' OR browser = '' OR browser IS NULL)
    ");
    $stmt->execute([$userId, $hours]);
    $botTraffic = $stmt->fetch();
    
    if ($botTraffic['bot_count'] > 20) {
        $alerts[] = [
            'type' => 'Bot Traffic',
            'severity' => 'medium',
            'message' => "{$botTraffic['bot_count']} views from unknown/suspicious browsers",
            'detected_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // 3. VPN/Proxy Detection (same device_type and browser, different IPs rapidly)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ip_address) as ip_count,
               device_type, browser
        FROM views_log
        WHERE user_id = ? 
        AND viewed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY device_type, browser
        HAVING ip_count >= 5
    ");
    $stmt->execute([$userId]);
    $vpnDetection = $stmt->fetchAll();
    
    foreach ($vpnDetection as $vpn) {
        $alerts[] = [
            'type' => 'VPN/Proxy Detected',
            'severity' => 'medium',
            'message' => "Same device ({$vpn['device_type']}, {$vpn['browser']}) from {$vpn['ip_count']} different IPs in 1 hour",
            'detected_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // 4. Unusual Traffic Patterns (spike detection)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(viewed_at) as date,
            COUNT(*) as daily_views
        FROM views_log
        WHERE user_id = ? AND viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(viewed_at)
        ORDER BY date DESC
    ");
    $stmt->execute([$userId]);
    $dailyViews = $stmt->fetchAll();
    
    if (count($dailyViews) >= 2) {
        $today = $dailyViews[0]['daily_views'];
        $avgPrevious = array_sum(array_column(array_slice($dailyViews, 1), 'daily_views')) / (count($dailyViews) - 1);
        
        if ($today > $avgPrevious * 3) {
            $alerts[] = [
                'type' => 'Unusual Traffic Spike',
                'severity' => 'low',
                'message' => "Today's traffic ({$today} views) is 3x higher than average ({$avgPrevious} views)",
                'detected_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    // 5. IP Reputation Check (check if IP is in known bot/proxy lists)
    $stmt = $pdo->prepare("
        SELECT DISTINCT ip_address, COUNT(*) as view_count
        FROM views_log
        WHERE user_id = ? 
        AND viewed_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        GROUP BY ip_address
        HAVING view_count >= 20
    ");
    $stmt->execute([$userId, $hours]);
    $suspiciousIPs = $stmt->fetchAll();
    
    foreach ($suspiciousIPs as $ip) {
        $reputation = checkIPReputation($ip['ip_address']);
        if ($reputation['is_suspicious']) {
            $alerts[] = [
                'type' => 'Suspicious IP Reputation',
                'severity' => 'high',
                'message' => "IP {$ip['ip_address']} ({$ip['view_count']} views) flagged as {$reputation['reason']}",
                'detected_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    // 6. Device Fingerprint Analysis
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as same_device_count
        FROM views_log
        WHERE user_id = ? 
        AND viewed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND is_counted = 0
    ");
    $stmt->execute([$userId]);
    $blockedViews = $stmt->fetch();
    
    if ($blockedViews['same_device_count'] > 50) {
        $alerts[] = [
            'type' => 'Blocked View Attempts',
            'severity' => 'medium',
            'message' => "{$blockedViews['same_device_count']} views were blocked due to duplicate detection in last hour",
            'detected_at' => date('Y-m-d H:i:s')
        ];
    }
    
    return $alerts;
}

/**
 * Check IP Reputation against known bot/proxy lists
 */
function checkIPReputation($ipAddress) {
    // Basic checks
    $result = [
        'is_suspicious' => false,
        'reason' => 'clean'
    ];
    
    // Check if IP is from known cloud providers (often used for bots)
    $cloudProviders = [
        'amazonaws.com',
        'digitalocean.com',
        'vultr.com',
        'linode.com',
        'googlecloud.com'
    ];
    
    $hostname = @gethostbyaddr($ipAddress);
    foreach ($cloudProviders as $provider) {
        if (stripos($hostname, $provider) !== false) {
            $result['is_suspicious'] = true;
            $result['reason'] = 'Cloud hosting provider';
            return $result;
        }
    }
    
    // Check if private/local IP
    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        $result['is_suspicious'] = true;
        $result['reason'] = 'Private/Reserved IP';
        return $result;
    }
    
    return $result;
}

/**
 * Analyze user agent for bot patterns
 */
function isBotUserAgent($userAgent) {
    $botPatterns = [
        'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 
        'python', 'java', 'httpclient', 'phantom', 'headless'
    ];
    
    $userAgentLower = strtolower($userAgent);
    foreach ($botPatterns as $pattern) {
        if (strpos($userAgentLower, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Calculate fraud score for a view
 */
function calculateFraudScore($viewData) {
    $score = 0;
    
    // Check user agent
    if (empty($viewData['browser']) || $viewData['browser'] === 'Unknown') {
        $score += 30;
    }
    
    // Check if bot
    if (isset($viewData['user_agent']) && isBotUserAgent($viewData['user_agent'])) {
        $score += 50;
    }
    
    // Check watch duration (if too short, suspicious)
    if (isset($viewData['watch_duration']) && $viewData['watch_duration'] < 5) {
        $score += 20;
    }
    
    // Check IP reputation
    if (isset($viewData['ip_address'])) {
        $reputation = checkIPReputation($viewData['ip_address']);
        if ($reputation['is_suspicious']) {
            $score += 40;
        }
    }
    
    return min($score, 100); // Cap at 100
}

/**
 * Block suspicious IP addresses
 */
function blockSuspiciousIP($ipAddress, $reason) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO blocked_ips (ip_address, reason, blocked_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE reason = ?, blocked_at = NOW()
    ");
    $stmt->execute([$ipAddress, $reason, $reason]);
    
    return true;
}

/**
 * Check if IP is blocked
 */
function isIPBlocked($ipAddress) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as blocked
        FROM blocked_ips
        WHERE ip_address = ? AND is_active = 1
    ");
    $stmt->execute([$ipAddress]);
    $result = $stmt->fetch();
    
    return $result['blocked'] > 0;
}

/**
 * Get fraud statistics for admin
 */
function getFraudStatistics($days = 30) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_suspicious_views,
            COUNT(DISTINCT ip_address) as suspicious_ips,
            COUNT(DISTINCT user_id) as affected_users,
            SUM(CASE WHEN is_counted = 0 THEN 1 ELSE 0 END) as blocked_views
        FROM views_log
        WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND (browser = 'Unknown' OR browser = '')
    ");
    $stmt->execute([$days]);
    
    return $stmt->fetch();
}
