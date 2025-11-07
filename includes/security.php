<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Generate API Key
function generateApiKey() {
    return bin2hex(random_bytes(API_KEY_LENGTH / 2));
}

// Generate Referral Code
function generateReferralCode($length = 8) {
    return strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, $length));
}

// Hash Password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Verify Password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Generate JWT Token
function generateJWT($userId, $apiKey) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $userId,
        'api_key' => $apiKey,
        'iat' => time(),
        'exp' => time() + (30 * 24 * 60 * 60) // 30 days
    ]);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

// Verify JWT Token
function verifyJWT($token) {
    $tokenParts = explode('.', $token);
    if (count($tokenParts) != 3) {
        return false;
    }
    
    $header = base64_decode($tokenParts[0]);
    $payload = base64_decode($tokenParts[1]);
    $signatureProvided = $tokenParts[2];
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    if ($base64UrlSignature !== $signatureProvided) {
        return false;
    }
    
    $payload = json_decode($payload, true);
    if (!isset($payload['exp']) || $payload['exp'] < time()) {
        return false;
    }
    
    return $payload;
}

// Get Current User from API Key or JWT
function getCurrentUser() {
    global $pdo;
    
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    // Check Bearer Token (JWT)
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
        $payload = verifyJWT($token);
        if ($payload && isset($payload['user_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'approved'");
            $stmt->execute([$payload['user_id']]);
            return $stmt->fetch();
        }
    }
    
    // Check API Key
    $apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? $headers['X-API-Key'] ?? null;
    if ($apiKey) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE api_key = ? AND status = 'approved'");
        $stmt->execute([$apiKey]);
        return $stmt->fetch();
    }
    
    // Check Session
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'approved'");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    
    return null;
}

// Check IP Whitelist
function checkIPWhitelist($user) {
    if (empty($user['ip_whitelist'])) {
        return true; // No whitelist = allow all
    }
    
    $allowedIPs = explode(',', $user['ip_whitelist']);
    $userIP = $_SERVER['REMOTE_ADDR'];
    
    foreach ($allowedIPs as $ip) {
        if (trim($ip) === $userIP) {
            return true;
        }
    }
    
    return false;
}

// Get Client IP
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
               'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
            return $_SERVER[$key];
        }
    }
    
    return '0.0.0.0';
}

// Detect Device Type
function getDeviceType() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (preg_match('/mobile|android|iphone|ipad|phone/i', $userAgent)) {
        return 'Mobile';
    } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
        return 'Tablet';
    }
    return 'Desktop';
}

// Detect Browser
function getBrowser() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
    if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
    if (strpos($userAgent, 'Safari') !== false) return 'Safari';
    if (strpos($userAgent, 'Edge') !== false) return 'Edge';
    if (strpos($userAgent, 'Opera') !== false) return 'Opera';
    
    return 'Other';
}

// Detect OS
function getOS() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (preg_match('/windows|win32/i', $userAgent)) return 'Windows';
    if (preg_match('/macintosh|mac os x/i', $userAgent)) return 'Mac OS';
    if (preg_match('/linux/i', $userAgent)) return 'Linux';
    if (preg_match('/android/i', $userAgent)) return 'Android';
    if (preg_match('/iphone|ipad|ipod/i', $userAgent)) return 'iOS';
    
    return 'Other';
}

// Get Country from IP (Using ipapi.co - free tier)
function getCountryFromIP($ip) {
    static $cache = [];
    
    if (isset($cache[$ip])) {
        return $cache[$ip];
    }
    
    $ch = curl_init("https://ipapi.co/{$ip}/json/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        $result = [
            'code' => $data['country_code'] ?? 'XX',
            'name' => $data['country_name'] ?? 'Unknown'
        ];
        $cache[$ip] = $result;
        return $result;
    }
    
    return ['code' => 'XX', 'name' => 'Unknown'];
}

// Sanitize Input
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// API Response Helper
function apiResponse($success, $data = null, $message = '', $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ]);
    exit;
}