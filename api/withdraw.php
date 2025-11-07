<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../config/currencies.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$user = getCurrentUser();
if (!$user) {
    apiResponse(false, null, 'Unauthorized', 401);
}

checkRateLimit($user, '/api/withdraw.php');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'request':
        handleWithdrawRequest($user);
        break;
    case 'history':
        handleWithdrawHistory($user);
        break;
    case 'methods':
        handleGetPaymentMethods($user);
        break;
    default:
        apiResponse(false, null, 'Invalid action', 400);
}

function handleWithdrawRequest($user) {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $amount = floatval($data['amount'] ?? 0);
    $paymentMethod = sanitizeInput($data['payment_method'] ?? '');
    $paymentDetails = json_encode($data['payment_details'] ?? []);
    $currency = sanitizeInput($data['currency'] ?? $user['preferred_currency']);
    
    if ($amount <= 0) {
        apiResponse(false, null, 'Invalid amount', 400);
    }
    
    $minWithdrawal = floatval(getSetting('min_withdrawal', 5.00));
    
    // Convert amount to USD for comparison
    $amountUSD = convertCurrency($amount, $currency, 'USD');
    
    if ($amountUSD < $minWithdrawal) {
        apiResponse(false, null, "Minimum withdrawal is " . formatCurrency($minWithdrawal, 'USD'), 400);
    }
    
    if ($user['balance'] < $amountUSD) {
        apiResponse(false, null, 'Insufficient balance', 400);
    }
    
    if (empty($paymentMethod)) {
        apiResponse(false, null, 'Payment method is required', 400);
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Deduct from user balance
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$amountUSD, $user['id']]);
        
        // Create withdrawal request
        $stmt = $pdo->prepare("
            INSERT INTO withdrawals (user_id, amount, currency, amount_usd, payment_method, payment_details, status)
            VALUES (?, ?, ?, ?, ?, ?, 'processing')
        ");
        $stmt->execute([
            $user['id'], $amount, $currency, $amountUSD, $paymentMethod, $paymentDetails
        ]);
        
        $withdrawalId = $pdo->lastInsertId();
        
        $pdo->commit();
        
        apiResponse(true, [
            'withdrawal_id' => $withdrawalId,
            'amount' => formatCurrency($amount, $currency),
            'amount_usd' => formatCurrency($amountUSD, 'USD'),
            'status' => 'processing',
            'message' => 'Withdrawal request submitted successfully'
        ], 'Withdrawal requested', 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        apiResponse(false, null, 'Failed to process withdrawal: ' . $e->getMessage(), 500);
    }
}

function handleWithdrawHistory($user) {
    global $pdo;
    
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $stmt = $pdo->prepare("
        SELECT * FROM withdrawals 
        WHERE user_id = ? 
        ORDER BY requested_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user['id'], $limit, $offset]);
    $withdrawals = $stmt->fetchAll();
    
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawals WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $total = $stmt->fetchColumn();
    
    // Format withdrawals
    foreach ($withdrawals as &$withdrawal) {
        $withdrawal['amount_formatted'] = formatCurrency($withdrawal['amount'], $withdrawal['currency']);
        $withdrawal['payment_details'] = json_decode($withdrawal['payment_details'], true);
    }
    
    apiResponse(true, [
        'withdrawals' => $withdrawals,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleGetPaymentMethods($user) {
    global $country_currency_map;
    
    // Get user's country (from IP or profile)
    $userIP = getClientIP();
    $country = getCountryFromIP($userIP);
    $countryCode = $country['code'];
    
    $methods = [
        'crypto' => [
            'USDT_TRC20' => ['name' => 'USDT (TRC20)', 'countries' => ['*']],
            'USDT_ERC20' => ['name' => 'USDT (ERC20)', 'countries' => ['*']],
            'BTC' => ['name' => 'Bitcoin', 'countries' => ['*']],
            'ETH' => ['name' => 'Ethereum', 'countries' => ['*']],
        ],
        'fiat' => []
    ];
    
    // Country-specific payment methods
    $countryMethods = [
        'IN' => [
            'UPI' => 'UPI (India)',
            'BANK_TRANSFER_IN' => 'Bank Transfer (NEFT/IMPS/RTGS)',
            'PAYTM' => 'Paytm',
            'PHONEPE' => 'PhonePe'
        ],
        'ID' => [
            'BANK_TRANSFER_ID' => 'Bank Transfer (Indonesia)',
            'OVO' => 'OVO',
            'GOPAY' => 'GoPay',
            'DANA' => 'DANA'
        ],
        'US' => [
            'PAYPAL' => 'PayPal',
            'BANK_TRANSFER_US' => 'Bank Transfer (ACH)',
            'VENMO' => 'Venmo',
            'CASHAPP' => 'Cash App'
        ],
        'BR' => [
            'PIX' => 'PIX',
            'BANK_TRANSFER_BR' => 'Bank Transfer (Brazil)'
        ],
        'PK' => [
            'EASYPAISA' => 'EasyPaisa',
            'JAZZCASH' => 'JazzCash',
            'BANK_TRANSFER_PK' => 'Bank Transfer (Pakistan)'
        ]
    ];
    
    if (isset($countryMethods[$countryCode])) {
        $methods['fiat'] = $countryMethods[$countryCode];
    } else {
        $methods['fiat'] = [
            'PAYPAL' => 'PayPal',
            'BANK_TRANSFER' => 'Bank Transfer'
        ];
    }
    
    apiResponse(true, [
        'country' => $countryCode,
        'methods' => $methods,
        'min_withdrawal' => formatCurrency(getSetting('min_withdrawal', 5.00), $user['preferred_currency'])
    ]);
}