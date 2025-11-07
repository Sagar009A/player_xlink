<?php
// Application Configuration
date_default_timezone_set('UTC');

define('SITE_URL', 'https://teraboxurll.in');
define('SITE_NAME', 'LinkStreamX');

// App Configuration for Deep Linking
define('APP_SCHEME', 'ymg.pricetracker'); // Deep link scheme: teraboxurll://
define('APP_PACKAGE_ANDROID', 'com.ymg.pricetracker'); // Replace with your actual Android package
define('APP_ID_IOS', 'id123456789'); // Replace with your actual iOS App ID
define('PLAY_STORE_URL', 'https://play.google.com/store/apps/details?id=com.ymg.pricetracker');
define('APP_STORE_URL', 'https://apps.apple.com/app/teraboxurll/id123456789');
define('SITE_TAGLINE', 'Turn Every View Into Value');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('THUMBNAIL_PATH', UPLOAD_PATH . '/thumbnails');
define('QR_CODE_PATH', UPLOAD_PATH . '/qrcodes');

// Security
define('JWT_SECRET', '2d8e802b55860eb7fe847474a18b8bd1efec5f60c700f4da801551620a263ea9');
define('API_KEY_LENGTH', 64);
define('ENCRYPTION_METHOD', 'AES-256-CBC');

// API Settings
define('API_RATE_LIMIT', 100); // requests per minute
define('API_RATE_WINDOW', 60); // seconds

// Currency API (Free tier - exchangerate-api.com)
define('CURRENCY_API_KEY', '0ece9cd755e74481df3d1992');
define('CURRENCY_API_URL', 'https://v6.exchangerate-api.com/v6/');

// Create directories if they don't exist
$dirs = [UPLOAD_PATH, THUMBNAIL_PATH, QR_CODE_PATH];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Helper function to get settings
function getSetting($key, $default = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Helper function to update settings
function updateSetting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        return $stmt->execute([$key, $value, $value]);
    } catch (Exception $e) {
        return false;
    }
}