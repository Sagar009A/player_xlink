<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

resetTodayViews();
echo "Daily views reset completed at " . date('Y-m-d H:i:s') . "\n";