<?php
/**
 * TeraBox Helper Functions
 * Simplified version for quick extraction
 */

/**
 * Extract TeraBox Video URL
 */
function getTeraboxVideoUrl($url) {
    // Extract short code
    if (preg_match('//s/([^/?#]+)/', $url, $matches)) {
        $shortCode = $matches[1];
    } else {
        return null;
    }
    
    // Get token from database cache (updated by cron)
    global $pdo;
    $token = null;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'terabox_js_token'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result && !empty($result['setting_value'])) {
            $token = trim($result['setting_value']);
        }
    } catch (Exception $e) {
        // Database error, try fallback
    }
    
    // Fallback: Fetch from external source if database token not available
    if (!$token) {
        $tokenUrl = 'https://ntmtemp.xyz/token.txt';
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible)');
        $token = trim(curl_exec($ch));
        curl_close($ch);
    }
    
    if (!$token) {
        return null;
    }
    
    // Fetch video info
    $apiUrl = "https://www.terabox.com/api/shorturlinfo?" . http_build_query([
        "app_id" => "250528",
        "web" => "1",
        "channel" => "dubox",
        "clienttype" => "0",
        "jsToken" => $token,
        "shorturl" => $shortCode,
        "root" => "1"
    ]);
    
    $headers = [
        "Cookie: browserid=ArBvk6M0xQdGymnG39wFu9_Y-XtkB-PAYReRtXIrWSYDC1MdrwIFqWZXhpc=; csrfToken=NmlcKtX7UofCC7LAP00cMkEd;",
        "Referer: https://www.terabox.app/sharing/link?surl=" . $shortCode
    ];
    
    // Enhanced curl options for better connection handling
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    
    // Retry once if connection fails
    if ($response === false && (strpos($curlError, 'SSL') !== false || strpos($curlError, 'Connection reset') !== false)) {
        curl_close($ch);
        sleep(2);
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36');
        $response = curl_exec($ch);
    }
    
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (!$data || $data['errno'] !== 0) {
        return null;
    }
    
    $file = $data['list'][0] ?? null;
    
    if (!$file) {
        return null;
    }
    
    $directUrl = $file['dlink'] ?? null;
    
    if (!$directUrl) {
        return null;
    }
    
    // Resolve the actual video URL from TeraBox dlink
    $actualVideoUrl = resolveTeraboxVideoUrl($directUrl);
    
    if (!$actualVideoUrl) {
        return null;
    }
    
    // Extract expiry from URL
    $expiresIn = 3600; // Default 1 hour
    $expiresAt = time() + 3600;
    
    // Parse URL to get expiry parameter
    $parsedUrl = parse_url($actualVideoUrl);
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
    
    return [
        'direct_url' => $actualVideoUrl,
        'title' => $file['server_filename'] ?? 'Untitled',
        'thumbnail' => $file['thumbs']['url3'] ?? null,
        'size' => $file['size'] ?? 0,
        'expires_in' => $expiresIn,
        'expires_at' => $expiresAt,
        'expires_at_formatted' => date('Y-m-d H:i:s', $expiresAt)
    ];
}

/**
 * Resolve the actual video URL from TeraBox dlink
 * This function follows redirects to get the real video file URL
 */
function resolveTeraboxVideoUrl($dlink) {
    // Initialize cURL to follow redirects
    $ch = curl_init();
    
    // Set cURL options to follow redirects and get final URL
    curl_setopt($ch, CURLOPT_URL, $dlink);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    // Don't download the actual content, just follow redirects
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    // Set headers to avoid mobile redirects
    $headers = [
        'Accept: video/*,*/*;q=0.9',
        'Accept-Language: en-US,en;q=0.9',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Sec-Fetch-Dest: video',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: cross-site',
        'Upgrade-Insecure-Requests: 1'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    // Check if we got a valid response
    if ($response === false || $httpCode !== 200) {
        return null;
    }
    
    // Check if the final URL looks like a video file
    $videoExtensions = ['.mp4', '.avi', '.mkv', '.mov', '.wmv', '.flv', '.webm', '.m4v'];
    $isVideoUrl = false;
    
    foreach ($videoExtensions as $ext) {
        if (stripos($finalUrl, $ext) !== false) {
            $isVideoUrl = true;
            break;
        }
    }
    
    // Also check for common video hosting patterns
    $videoHosts = ['terabox.com', 'terabox.app', 'dubox.com', 'baidupcs.com'];
    $isVideoHost = false;
    
    foreach ($videoHosts as $host) {
        if (stripos($finalUrl, $host) !== false) {
            $isVideoHost = true;
            break;
        }
    }
    
    // If it's a video file or from a video host, use it
    if ($isVideoUrl || $isVideoHost) {
        return $finalUrl;
    }
    
    // If it's not a video URL, try to extract video URL from response headers
    $videoUrl = extractVideoUrlFromHeaders($response);
    if ($videoUrl) {
        return $videoUrl;
    }
    
    // If all else fails, return the final URL anyway (might still work)
    return $finalUrl;
}

/**
 * Extract video URL from response headers
 */
function extractVideoUrlFromHeaders($response) {
    $lines = explode("\n", $response);
    $videoUrl = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Look for Location header
        if (stripos($line, 'Location:') === 0) {
            $url = trim(substr($line, 9));
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $videoUrl = $url;
                break;
            }
        }
        
        // Look for Content-Location header
        if (stripos($line, 'Content-Location:') === 0) {
            $url = trim(substr($line, 16));
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $videoUrl = $url;
                break;
            }
        }
    }
    
    return $videoUrl;
}
?>