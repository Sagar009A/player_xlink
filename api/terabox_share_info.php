<?php
/**
 * Terabox Share Info API
 * This is the updated version of your code that uses automatic token fetching
 */

require_once __DIR__ . '/../config/database.php';

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Check URL parameter
if (!isset($_GET['url']) || empty($_GET['url'])) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'No URL provided. Use ?url=YOUR_SHARED_URL'
    ]));
}

$shared_url = $_GET['url'];

/**
 * Get Terabox Token from Database (automatically updated by cron)
 */
function getTeraboxToken() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'terabox_js_token'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result && !empty($result['setting_value'])) {
            return trim($result['setting_value']);
        }
    } catch (Exception $e) {
        error_log("Token fetch error: " . $e->getMessage());
    }
    
    // Fallback: Try external source
    try {
        $ch = curl_init('https://ntmtemp.xyz/token.txt');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $token = trim(curl_exec($ch));
        curl_close($ch);
        
        if ($token) {
            return $token;
        }
    } catch (Exception $e) {
        error_log("External token fetch error: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Get Share Info from Terabox API
 */
function get_share_info($short_url_part, $jsToken) {
    $api_url = "https://www.terabox.com/api/shorturlinfo?" . http_build_query([
        "app_id" => "250528",
        "web" => "1",
        "channel" => "dubox",
        "clienttype" => "0",
        "jsToken" => $jsToken,
        "dp-logid" => "35980000896792010019",
        "shorturl" => $short_url_part,
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
        "Referer: https://www.terabox.app/sharing/link?surl={$short_url_part}",
        "Accept-Encoding: gzip, deflate, br",
        "Accept-Language: en-GB,en-US;q=0.9,en;q=0.8",
        "Connection: keep-alive"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, ""); // auto decode gzip
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpcode == 200 && $response) {
        $data = json_decode($response, true);

        if (!empty($data['list'][0])) {
            return [
                'success' => true,
                'message' => 'Share info fetched successfully',
                'data' => $data['list'][0],
                'full_response' => $data
            ];
        } else {
            return [
                'success' => false,
                'message' => 'No list items found in response',
                'response' => $data
            ];
        }
    } else {
        return [
            'success' => false,
            'message' => "Request failed with status code: {$httpcode}",
            'error' => $error
        ];
    }
}

/**
 * Parse shared URL to extract short code
 */
function parse_shared_url($shared_url) {
    // Pattern 1: /s/XXXXX
    if (preg_match('/\/s\/([^\/\?#]+)/', $shared_url, $matches)) {
        return $matches[1];
    }
    
    // Pattern 2: ?surl=XXXXX
    if (preg_match('/[?&]surl=([^&#]+)/', $shared_url, $matches)) {
        return $matches[1];
    }
    
    return null;
}

// Main execution
try {
    // Parse URL to get short code
    $short_url_part = parse_shared_url($shared_url);
    
    if (!$short_url_part) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid Terabox URL format. URL must contain /s/ or ?surl='
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get token automatically from database (no hardcoding!)
    $jsToken = getTeraboxToken();
    
    if (!$jsToken) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Token not available. Please ensure the cron job is running.',
            'help' => 'Run: php /workspace/cron/fetch_terabox_token.php'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Fetch share info
    $result = get_share_info($short_url_part, $jsToken);
    
    // Return result
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
