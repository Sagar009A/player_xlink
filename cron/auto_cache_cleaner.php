<?php
/**
 * Auto Cache Cleaner for LinkStreamX
 * 
 * This script automatically cleans old cache files to keep the system running smoothly.
 * It deletes cache files older than the specified retention period.
 * 
 * Schedule: Run every 6 hours via cron
 * Cron command: 0 */6 * * * php /path/to/auto_cache_cleaner.php
 */

require_once __DIR__ . '/../config/database.php';

// Configuration
define('CACHE_DIR', __DIR__ . '/../cache/');
define('CACHE_RETENTION_HOURS', 24); // Keep cache files for 24 hours
define('LOG_FILE', __DIR__ . '/../logs/cache_cleaner.log');

/**
 * Log message to file
 */
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}\n";
    
    // Create logs directory if it doesn't exist
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
    echo $logEntry; // Also output to console
}

/**
 * Clean old cache files
 */
function cleanCache() {
    if (!is_dir(CACHE_DIR)) {
        logMessage("Cache directory does not exist: " . CACHE_DIR);
        return;
    }
    
    $retentionSeconds = CACHE_RETENTION_HOURS * 3600;
    $currentTime = time();
    $deletedCount = 0;
    $totalSize = 0;
    
    logMessage("Starting cache cleanup...");
    logMessage("Cache directory: " . CACHE_DIR);
    logMessage("Retention period: " . CACHE_RETENTION_HOURS . " hours");
    
    // Scan cache directory
    $files = glob(CACHE_DIR . '*.json') ?: [];
    $files = array_merge($files, glob(CACHE_DIR . '*.cache') ?: []);
    $files = array_merge($files, glob(CACHE_DIR . '*.tmp') ?: []);
    
    logMessage("Found " . count($files) . " cache files");
    
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        
        $fileAge = $currentTime - filemtime($file);
        
        // Delete if older than retention period
        if ($fileAge > $retentionSeconds) {
            $fileSize = filesize($file);
            
            if (unlink($file)) {
                $deletedCount++;
                $totalSize += $fileSize;
                logMessage("Deleted: " . basename($file) . " (Age: " . round($fileAge / 3600, 2) . " hours, Size: " . formatBytes($fileSize) . ")");
            } else {
                logMessage("Failed to delete: " . basename($file));
            }
        }
    }
    
    logMessage("Cleanup completed!");
    logMessage("Deleted files: {$deletedCount}");
    logMessage("Freed space: " . formatBytes($totalSize));
    
    return [
        'deleted_count' => $deletedCount,
        'freed_space' => $totalSize
    ];
}

/**
 * Format bytes to human-readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Clean expired video cache from database
 */
function cleanDatabaseCache() {
    global $pdo;
    
    try {
        logMessage("Cleaning expired database cache entries...");
        
        // Delete expired instant links
        $stmt = $pdo->prepare("DELETE FROM instant_links WHERE expires_at < NOW()");
        $stmt->execute();
        $deletedInstantLinks = $stmt->rowCount();
        
        logMessage("Deleted {$deletedInstantLinks} expired instant links");
        
        // Clean old redirect logs (keep last 7 days)
        $stmt = $pdo->prepare("DELETE FROM redirect_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        $deletedLogs = $stmt->rowCount();
        
        logMessage("Deleted {$deletedLogs} old redirect logs");
        
        return [
            'instant_links' => $deletedInstantLinks,
            'redirect_logs' => $deletedLogs
        ];
        
    } catch (PDOException $e) {
        logMessage("Database cleanup error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get cache statistics
 */
function getCacheStats() {
    if (!is_dir(CACHE_DIR)) {
        return null;
    }
    
    $files = glob(CACHE_DIR . '*');
    $totalSize = 0;
    $totalFiles = 0;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $totalFiles++;
            $totalSize += filesize($file);
        }
    }
    
    return [
        'total_files' => $totalFiles,
        'total_size' => $totalSize,
        'total_size_formatted' => formatBytes($totalSize)
    ];
}

// Main execution
try {
    logMessage("=================================================");
    logMessage("Auto Cache Cleaner Started");
    logMessage("=================================================");
    
    // Get initial stats
    $initialStats = getCacheStats();
    if ($initialStats) {
        logMessage("Initial cache: {$initialStats['total_files']} files, {$initialStats['total_size_formatted']}");
    }
    
    // Clean file cache
    $fileCleanup = cleanCache();
    
    // Clean database cache
    $dbCleanup = cleanDatabaseCache();
    
    // Get final stats
    $finalStats = getCacheStats();
    if ($finalStats) {
        logMessage("Final cache: {$finalStats['total_files']} files, {$finalStats['total_size_formatted']}");
    }
    
    logMessage("=================================================");
    logMessage("Auto Cache Cleaner Completed Successfully");
    logMessage("=================================================");
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

exit(0);
