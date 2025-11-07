<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    apiResponse(false, null, 'Unauthorized', 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['preferred_currency'])) {
        $stmt = $pdo->prepare("UPDATE users SET preferred_currency = ? WHERE id = ?");
        $stmt->execute([$data['preferred_currency'], $user['id']]);
        
        apiResponse(true, ['currency' => $data['preferred_currency']], 'Currency updated');
    }
}

apiResponse(false, null, 'Invalid request', 400);