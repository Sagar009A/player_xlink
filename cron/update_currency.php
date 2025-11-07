<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/currencies.php';

$success = updateCurrencyRates();
echo ($success ? "✓" : "✗") . " Currency rates update at " . date('Y-m-d H:i:s') . "\n";