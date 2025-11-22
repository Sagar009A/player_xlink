<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u988479389_te');
define('DB_USER', 'u988479389_te');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Function to get database connection
function getDBConnection() {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    return new PDO($dsn, DB_USER, DB_PASS, $options);
}

// PDO Connection (for backward compatibility)
try {
    $pdo = getDBConnection();
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed', 'message' => $e->getMessage()]));
}
