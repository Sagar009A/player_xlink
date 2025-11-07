<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        handleRegister();
        break;
    case 'login':
        handleLogin();
        break;
    case 'verify':
        handleVerify();
        break;
    case 'refresh':
        handleRefreshToken();
        break;
    case 'rotate_key':
        handleRotateApiKey();
        break;
    default:
        apiResponse(false, null, 'Invalid action', 400);
}

function handleRegister() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['username', 'email', 'password', 'telegram_id', 'traffic_source', 'traffic_category'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            apiResponse(false, null, "Field '{$field}' is required", 400);
        }
    }
    
    $username = sanitizeInput($data['username']);
    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    $password = $data['password'];
    $telegramId = sanitizeInput($data['telegram_id']);
    $trafficSource = sanitizeInput($data['traffic_source']);
    $trafficCategory = sanitizeInput($data['traffic_category']);
    
    if (!$email) {
        apiResponse(false, null, 'Invalid email address', 400);
    }
    
    if (strlen($password) < 6) {
        apiResponse(false, null, 'Password must be at least 6 characters', 400);
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        apiResponse(false, null, 'Email or username already exists', 409);
    }
    
    // Create user
    $hashedPassword = hashPassword($password);
    $apiKey = generateApiKey();
    $referralCode = generateReferralCode();
    
    // Check if referred by someone
    $referredBy = null;
    if (!empty($data['referral_code'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->execute([sanitizeInput($data['referral_code'])]);
        $referrer = $stmt->fetch();
        if ($referrer) {
            $referredBy = $referrer['id'];
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, telegram_id, traffic_source, 
                               traffic_category, api_key, referral_code, referred_by, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $username, $email, $hashedPassword, $telegramId, 
            $trafficSource, $trafficCategory, $apiKey, $referralCode, $referredBy
        ]);
        
        $userId = $pdo->lastInsertId();
        
        apiResponse(true, [
            'user_id' => $userId,
            'status' => 'pending',
            'message' => 'Registration successful. Your account is pending admin approval.'
        ], 'Registration successful', 201);
        
    } catch (Exception $e) {
        apiResponse(false, null, 'Registration failed: ' . $e->getMessage(), 500);
    }
}

function handleLogin() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $data['password'] ?? '';
    
    if (!$email || !$password) {
        apiResponse(false, null, 'Email and password are required', 400);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !verifyPassword($password, $user['password'])) {
        apiResponse(false, null, 'Invalid credentials', 401);
    }
    
    if ($user['status'] === 'pending') {
        apiResponse(false, null, 'Your account is pending approval', 403);
    }
    
    if ($user['status'] === 'rejected') {
        apiResponse(false, null, 'Your account has been rejected', 403);
    }
    
    if ($user['status'] === 'blocked') {
        apiResponse(false, null, 'Your account has been blocked', 403);
    }
    
    // Generate JWT token
    $token = generateJWT($user['id'], $user['api_key']);
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    
    apiResponse(true, [
        'token' => $token,
        'api_key' => $user['api_key'],
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'balance' => formatCurrency($user['balance'], $user['preferred_currency']),
            'total_views' => $user['total_views'],
            'referral_code' => $user['referral_code']
        ]
    ], 'Login successful');
}

function handleVerify() {
    $user = getCurrentUser();
    
    if (!$user) {
        apiResponse(false, null, 'Unauthorized', 401);
    }
    
    apiResponse(true, [
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'balance' => formatCurrency($user['balance'], $user['preferred_currency']),
            'total_views' => $user['total_views'],
            'api_key' => $user['api_key'],
            'referral_code' => $user['referral_code']
        ]
    ], 'Token valid');
}

function handleRefreshToken() {
    $user = getCurrentUser();
    
    if (!$user) {
        apiResponse(false, null, 'Unauthorized', 401);
    }
    
    $newToken = generateJWT($user['id'], $user['api_key']);
    
    apiResponse(true, ['token' => $newToken], 'Token refreshed');
}

function handleRotateApiKey() {
    global $pdo;
    
    $user = getCurrentUser();
    
    if (!$user) {
        apiResponse(false, null, 'Unauthorized', 401);
    }
    
    $newApiKey = generateApiKey();
    
    $stmt = $pdo->prepare("UPDATE users SET api_key = ? WHERE id = ?");
    $stmt->execute([$newApiKey, $user['id']]);
    
    apiResponse(true, [
        'api_key' => $newApiKey,
        'message' => 'API key rotated successfully. Please update your integrations.'
    ], 'API key rotated');
}