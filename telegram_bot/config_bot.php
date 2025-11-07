<?php
/**
 * Telegram Bot Configuration File
 * 
 * INSTRUCTIONS:
 * 1. Replace YOUR_BOT_TOKEN_HERE with your actual bot token from @BotFather
 * 2. Update database credentials
 * 3. Set your website URL
 * 4. Save and upload to server
 */

// ==========================================
// TELEGRAM BOT SETTINGS
// ==========================================

// Get bot token from @BotFather on Telegram
define('TELEGRAM_BOT_TOKEN', '8304958380:AAEkWl8M1PP0ujA4vSEjo4NllZyVMF137Ss');

// Your bot username (with @)
define('BOT_USERNAME', '@LinkStreamXBot');

// Bot display name
define('BOT_NAME', 'LinkStreamX');

// Webhook URL (must be HTTPS)
// Example: https://yourdomain.com/telegram_bot/webhook.php
define('WEBHOOK_URL', 'https://teraboxurll.in/telegram_bot/webhook.php');

// Telegram API URL (Don't change this)
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/');

// ==========================================
// WEBSITE SETTINGS
// ==========================================

// Your website URL (without trailing slash)
define('SITE_URL', 'https://teraboxurll.in');

// Your website name
define('SITE_NAME', 'LinkStreamX');

// Site API URL
define('SITE_API_URL', SITE_URL . '/api/');

// ==========================================
// BOT SETTINGS
// ==========================================

// Number of links to show per page
define('LINKS_PER_PAGE', 15);

// Session expiry time (in minutes)
define('SESSION_EXPIRY_MINUTES', 30);

// ==========================================
// DATABASE CONFIGURATION
// ==========================================

// Database host
define('DB_HOST', 'localhost');

// Database name (your existing database)
define('DB_NAME', 'u988479389_te');

// Database username
define('DB_USER', 'u988479389_te');

// Database password
define('DB_PASS', 'Sagar31#@');

// Database charset
define('DB_CHARSET', 'utf8mb4');

// Supported domains for link conversion
define('SUPPORTED_DOMAINS', [
    'terabox.com',
    'teraboxapp.com',
    '1024terabox.com',
    'terabox.app',
    'terasharefile.com',
    'teraboxurl.com',
    '4funbox.com',
    'mirrobox.com',
    'momerybox.com',
    'teraboxlink.com',
    // Add more domains as needed
]);

// Template configuration
define('TEMPLATE_HEADER', "👇 𝙁𝙐𝙇𝙇 𝙑𝙄𝘿𝙀𝙊 𝙇𝙄𝙉𝙆 👇 Ad Free");
define('TEMPLATE_FOOTER', "HOW TO Watch Video👇\nhttps://t.me/+h-NgAiLJT5U4YTdh");


// ==========================================
// LOGGING CONFIGURATION
// ==========================================

// Log file path
define('BOT_LOG_FILE', __DIR__ . '/logs/bot.log');

// Log level: DEBUG, INFO, ERROR
define('BOT_LOG_LEVEL', 'INFO');

// Enable/disable logging
define('BOT_LOGGING_ENABLED', true);

// ==========================================
// TIMEZONE
// ==========================================

// Set your timezone
date_default_timezone_set('Asia/Kolkata');

// ==========================================
// DATABASE CONNECTION FUNCTION
// ==========================================

/**
 * Get database connection
 * Creates a singleton PDO instance
 */
function getBotDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            botLog("Database connected successfully", 'INFO');
            
        } catch (PDOException $e) {
            botLog("Database connection failed: " . $e->getMessage(), 'ERROR');
            
            // In production, show generic error
            if (defined('BOT_DEBUG') && BOT_DEBUG) {
                die("Database Error: " . $e->getMessage());
            } else {
                die("Database connection failed. Please contact administrator.");
            }
        }
    }
    
    return $pdo;
}

// ==========================================
// LOGGING FUNCTION
// ==========================================

/**
 * Log messages to file
 * 
 * @param string $message Message to log
 * @param string $level Log level (DEBUG, INFO, ERROR)
 */
function botLog($message, $level = 'INFO') {
    // Check if logging is enabled
    if (!defined('BOT_LOGGING_ENABLED') || !BOT_LOGGING_ENABLED) {
        return;
    }
    
    // Check log level
    if (!defined('BOT_LOG_LEVEL')) return;
    
    $levels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'ERROR' => 2
    ];
    
    $currentLevel = $levels[BOT_LOG_LEVEL] ?? 1;
    $messageLevel = $levels[$level] ?? 1;
    
    // Only log if message level is >= current level
    if ($messageLevel < $currentLevel) {
        return;
    }
    
    // Get log file path
    $logFile = BOT_LOG_FILE ?? __DIR__ . '/bot.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Format log message
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    
    // Write to log file
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

/**
 * Format number with decimals
 */
function formatNumber($number, $decimals = 2) {
    return number_format($number, $decimals);
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = '$') {
    return $currency . formatNumber($amount, 2);
}

/**
 * Format date
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

/**
 * Truncate string
 */
function truncateString($string, $length = 50, $append = '...') {
    if (strlen($string) <= $length) {
        return $string;
    }
    return substr($string, 0, $length) . $append;
}

/**
 * Escape HTML for Telegram
 */
function escapeHtml($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// ==========================================
// INITIALIZATION
// ==========================================

// Log configuration loaded
botLog("Configuration loaded", 'INFO');

// Check if bot token is configured
if (TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE') {
    botLog("Bot token not configured!", 'ERROR');
}

// ==========================================
// DEBUG MODE (Disable in production)
// ==========================================

// Enable this for development/testing only
define('BOT_DEBUG', false);

if (BOT_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    botLog("DEBUG MODE ENABLED - Disable in production!", 'ERROR');
}
