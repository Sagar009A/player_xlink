<?php
require_once __DIR__ . '/../check_admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../config/currencies.php';

header('Content-Type: application/json');

$success = updateCurrencyRates();

if ($success) {
    apiResponse(true, null, 'Currency rates updated successfully');
} else {
    apiResponse(false, null, 'Failed to update currency rates', 500);
}