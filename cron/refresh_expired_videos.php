<?php
/**
 * Refresh Expired and Old Video Links
 * 
 * This cron job should run every 20-30 minutes to:
 * 1. Delete expired streamable links
 * 2. Fetch fresh streamable URLs
 * 3. Update database with new links
 * 
 * Add to crontab:
 * */20 * * * * /usr/bin/php /path/to/workspace/cron/refresh_expired_videos.php >> /path/to/workspace/logs/refresh_videos.log 2>&1
 * 
 * Or for 30 minute intervals:
 * */30 * * * * /usr/bin/php /path/to/workspace/cron/refresh_expired_videos.php >> /path/to/workspace/logs/refresh_videos.log 2>&1
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/extractors.php';

// Load AbstractExtractor FIRST - critical for class inheritance
if (file_exists(__DIR__ . '/../extractors/AbstractExtractor.php')) {
    require_once __DIR__ . '/../extractors/AbstractExtractor.php';
}

require_once __DIR__ . '/../services/ExtractorManager.php';

// Get configuration
$config = getExtractorConfig();
$autoRefreshConfig = $config['auto_refresh'] ?? [
    'enabled' => true,
    'interval' => 1800,
    'min_interval' => 1200,
    'refresh_before_expiry' => 600,
    'batch_size' => 50
];

$logPrefix = "[" . date('Y-m-d H:i:s') . "] ";

echo $logPrefix . "Starting video link refresh process...\n";

if (!$autoRefreshConfig['enabled']) {
    echo $logPrefix . "Auto-refresh is disabled in configuration. Exiting.\n";
    exit(0);
}

// Get links that need refreshing
// Categories:
// 1. Links with expired streamable URLs
// 2. Links nearing expiry (within refresh_before_expiry window)
// 3. Links older than the refresh interval
$refreshWindow = $autoRefreshConfig['refresh_before_expiry'];
$batchSize = $autoRefreshConfig['batch_size'];

$stmt = $pdo->prepare("
    SELECT 
        l.*,
        TIMESTAMPDIFF(SECOND, NOW(), l.video_expires_at) as seconds_until_expiry
    FROM links l
    WHERE l.is_active = 1
    AND l.direct_video_url IS NOT NULL
    AND (
        -- Case 1: Already expired
        (l.video_expires_at IS NOT NULL AND l.video_expires_at < NOW())
        OR
        -- Case 2: Expiring soon (within refresh window)
        (l.video_expires_at IS NOT NULL AND l.video_expires_at < DATE_ADD(NOW(), INTERVAL :refresh_window SECOND))
        OR
        -- Case 3: Old links (last refreshed more than interval ago)
        (l.last_checked_at IS NULL OR l.last_checked_at < DATE_SUB(NOW(), INTERVAL :refresh_interval SECOND))
    )
    ORDER BY 
        CASE
            WHEN l.video_expires_at < NOW() THEN 1  -- Expired first
            WHEN l.video_expires_at < DATE_ADD(NOW(), INTERVAL :refresh_window SECOND) THEN 2  -- Expiring soon
            ELSE 3  -- Old links
        END,
        l.video_expires_at ASC
    LIMIT :batch_size
");

$stmt->bindValue(':refresh_window', $refreshWindow, PDO::PARAM_INT);
$stmt->bindValue(':refresh_interval', $autoRefreshConfig['interval'], PDO::PARAM_INT);
$stmt->bindValue(':batch_size', $batchSize, PDO::PARAM_INT);
$stmt->execute();

$linksToRefresh = $stmt->fetchAll();
$totalLinks = count($linksToRefresh);

echo $logPrefix . "Found $totalLinks links to refresh\n";

if ($totalLinks === 0) {
    echo $logPrefix . "No links need refreshing. Exiting.\n";
    exit(0);
}

$manager = new ExtractorManager();
$refreshed = 0;
$failed = 0;
$skipped = 0;

foreach ($linksToRefresh as $link) {
    $linkId = $link['id'];
    $shortCode = $link['short_code'];
    $originalUrl = $link['original_url'];
    $secondsUntilExpiry = $link['seconds_until_expiry'] ?? 0;
    
    $statusMsg = "";
    if ($secondsUntilExpiry < 0) {
        $statusMsg = "(EXPIRED)";
    } elseif ($secondsUntilExpiry < $refreshWindow) {
        $statusMsg = "(EXPIRING IN " . gmdate('i:s', $secondsUntilExpiry) . ")";
    } else {
        $statusMsg = "(OLD LINK)";
    }
    
    echo $logPrefix . "Processing link: $shortCode $statusMsg\n";
    
    try {
        // Extract fresh video URL
        $result = $manager->extract($originalUrl, ['refresh' => true, 'skip_cache' => true]);
        
        if ($result['success']) {
            $data = $result['data'];
            $newDirectUrl = $data['direct_link'] ?? null;
            
            if (!$newDirectUrl) {
                echo $logPrefix . "  ? No direct link in extraction result\n";
                $failed++;
                
                // Update last_checked_at even on failure
                $stmt = $pdo->prepare("UPDATE links SET last_checked_at = NOW() WHERE id = ?");
                $stmt->execute([$linkId]);
                continue;
            }
            
            // Calculate new expiry
            $newExpiresAt = null;
            if (isset($data['expires_at']) && $data['expires_at'] > 0) {
                $newExpiresAt = date('Y-m-d H:i:s', $data['expires_at']);
            } elseif (isset($data['expires_in']) && $data['expires_in'] > 0) {
                $newExpiresAt = date('Y-m-d H:i:s', time() + $data['expires_in']);
            }
            
            // Update database with fresh link
            $stmt = $pdo->prepare("
                UPDATE links 
                SET 
                    direct_video_url = ?,
                    video_expires_at = ?,
                    last_checked_at = NOW(),
                    video_title = ?,
                    video_thumbnail = ?,
                    video_size = ?,
                    video_quality = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $newDirectUrl,
                $newExpiresAt,
                $data['title'] ?? $data['filename'] ?? null,
                $data['thumbnail'] ?? null,
                $data['size'] ?? 0,
                $data['quality'] ?? 'Unknown',
                $linkId
            ]);
            
            $refreshed++;
            $expiryInfo = $newExpiresAt ? " (expires: $newExpiresAt)" : " (no expiry)";
            echo $logPrefix . "  ? Refreshed successfully" . $expiryInfo . "\n";
            
        } else {
            // Extraction failed
            $errorMsg = $result['message'] ?? $result['error'] ?? 'Unknown error';
            echo $logPrefix . "  ? Failed: $errorMsg\n";
            
            // Update last_checked_at
            $stmt = $pdo->prepare("UPDATE links SET last_checked_at = NOW() WHERE id = ?");
            $stmt->execute([$linkId]);
            
            $failed++;
            
            // If link is permanently broken (not just rate limit), optionally disable it
            $permanentErrors = ['invalid_link', 'file_not_found', 'access_denied', 'invalid_url'];
            $errorCode = $result['error'] ?? '';
            
            if (in_array($errorCode, $permanentErrors)) {
                echo $logPrefix . "  ? Permanent error detected. Link may need manual review.\n";
                // Optionally: Mark as inactive or flag for review
                // $stmt = $pdo->prepare("UPDATE links SET is_active = 0, error_message = ? WHERE id = ?");
                // $stmt->execute([$errorMsg, $linkId]);
            }
        }
        
        // Small delay to avoid rate limiting
        usleep(500000); // 0.5 seconds
        
    } catch (Exception $e) {
        $failed++;
        $errorMsg = $e->getMessage();
        echo $logPrefix . "  ? Exception: $errorMsg\n";
        
        // Update last_checked_at even on exception
        try {
            $stmt = $pdo->prepare("UPDATE links SET last_checked_at = NOW() WHERE id = ?");
            $stmt->execute([$linkId]);
        } catch (Exception $innerE) {
            echo $logPrefix . "  ? Failed to update last_checked_at: " . $innerE->getMessage() . "\n";
        }
    }
}

// Summary
echo "\n" . $logPrefix . "=== REFRESH SUMMARY ===\n";
echo $logPrefix . "Total processed: $totalLinks\n";
echo $logPrefix . "Successfully refreshed: $refreshed\n";
echo $logPrefix . "Failed: $failed\n";
echo $logPrefix . "Success rate: " . ($totalLinks > 0 ? round(($refreshed / $totalLinks) * 100, 2) : 0) . "%\n";
echo $logPrefix . "======================\n";

// Cleanup: Delete very old expired links (optional)
// Uncomment if you want to auto-delete links that have been expired for more than 24 hours
/*
$cleanupStmt = $pdo->prepare("
    DELETE FROM links 
    WHERE video_expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND is_active = 1
");
$cleanupStmt->execute();
$deletedCount = $cleanupStmt->rowCount();
if ($deletedCount > 0) {
    echo $logPrefix . "Cleaned up $deletedCount very old expired links\n";
}
*/

echo $logPrefix . "Refresh process completed.\n";
