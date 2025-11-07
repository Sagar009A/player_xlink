<?php
require_once __DIR__ . '/../check_admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$usersUpdated = updateAllStats();

apiResponse(true, [
    'users_updated' => $usersUpdated,
    'timestamp' => date('Y-m-d H:i:s')
], "Successfully updated stats for {$usersUpdated} users");