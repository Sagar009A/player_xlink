<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    apiResponse(false, null, 'Unauthorized', 401);
}

$action = $_GET['action'] ?? 'current';

if ($action === 'current') {
    apiResponse(true, [
        'balance' => floatval($user['balance']),
        'total_views' => intval($user['total_views']),
        'total_earnings' => floatval($user['total_earnings'])
    ]);
}

apiResponse(false, null, 'Invalid action', 400);