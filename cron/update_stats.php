<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$count = updateAllStats();
echo "Stats updated for {$count} users at " . date('Y-m-d H:i:s') . "\n";