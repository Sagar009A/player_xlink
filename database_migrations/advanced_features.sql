-- Advanced Features Database Migration
-- Add new tables and columns for advanced analytics

-- 1. Blocked IPs Table (for fraud detection)
CREATE TABLE IF NOT EXISTS `blocked_ips` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `blocked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `unblocked_at` TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY `ip_address` (`ip_address`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Email Notifications Log
CREATE TABLE IF NOT EXISTS `email_notifications_log` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `notification_type` VARCHAR(50) NOT NULL,
    `related_id` INT DEFAULT NULL,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_delivered` TINYINT(1) DEFAULT 1,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`notification_type`),
    INDEX `idx_sent_at` (`sent_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Add email notification preferences to users table
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `email_notifications_enabled` TINYINT(1) DEFAULT 1 AFTER `preferred_currency`,
ADD COLUMN IF NOT EXISTS `daily_summary_enabled` TINYINT(1) DEFAULT 1 AFTER `email_notifications_enabled`,
ADD COLUMN IF NOT EXISTS `weekly_summary_enabled` TINYINT(1) DEFAULT 1 AFTER `daily_summary_enabled`,
ADD COLUMN IF NOT EXISTS `milestone_alerts_enabled` TINYINT(1) DEFAULT 1 AFTER `weekly_summary_enabled`;

-- 4. Add OS column to views_log for better analytics
ALTER TABLE `views_log`
ADD COLUMN IF NOT EXISTS `os` VARCHAR(50) DEFAULT NULL AFTER `browser`;

-- 5. User Activity Log Table
CREATE TABLE IF NOT EXISTS `user_activity_log` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `activity_type` VARCHAR(50) NOT NULL,
    `activity_data` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_activity_type` (`activity_type`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Fraud Detection Alerts Table
CREATE TABLE IF NOT EXISTS `fraud_alerts` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `alert_type` VARCHAR(50) NOT NULL,
    `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    `message` TEXT NOT NULL,
    `alert_data` JSON DEFAULT NULL,
    `is_resolved` TINYINT(1) DEFAULT 0,
    `resolved_at` TIMESTAMP NULL DEFAULT NULL,
    `resolved_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_alert_type` (`alert_type`),
    INDEX `idx_severity` (`severity`),
    INDEX `idx_is_resolved` (`is_resolved`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Video Performance Metrics Table
CREATE TABLE IF NOT EXISTS `video_performance_metrics` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `link_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `total_views` INT DEFAULT 0,
    `unique_viewers` INT DEFAULT 0,
    `avg_watch_time` INT DEFAULT 0,
    `completion_rate` DECIMAL(5,2) DEFAULT 0,
    `engagement_score` INT DEFAULT 0,
    `drop_off_30s` INT DEFAULT 0,
    `drop_off_1min` INT DEFAULT 0,
    `drop_off_2min` INT DEFAULT 0,
    `earnings` DECIMAL(10,4) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_link_date` (`link_id`, `date`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_date` (`date`),
    FOREIGN KEY (`link_id`) REFERENCES `links`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. A/B Testing Results Table (for thumbnail testing)
CREATE TABLE IF NOT EXISTS `ab_test_results` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `link_id` INT NOT NULL,
    `variant` VARCHAR(10) NOT NULL, -- 'A' or 'B'
    `thumbnail_url` VARCHAR(500) DEFAULT NULL,
    `views` INT DEFAULT 0,
    `clicks` INT DEFAULT 0,
    `avg_watch_time` INT DEFAULT 0,
    `conversion_rate` DECIMAL(5,2) DEFAULT 0,
    `start_date` DATE NOT NULL,
    `end_date` DATE DEFAULT NULL,
    `is_winner` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_link_id` (`link_id`),
    INDEX `idx_variant` (`variant`),
    FOREIGN KEY (`link_id`) REFERENCES `links`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. System Notifications Table
CREATE TABLE IF NOT EXISTS `system_notifications` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL, -- NULL for all users
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `action_url` VARCHAR(500) DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_read` (`is_read`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. API Request Analytics Table (detailed tracking)
CREATE TABLE IF NOT EXISTS `api_analytics` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `endpoint` VARCHAR(255) NOT NULL,
    `method` VARCHAR(10) NOT NULL,
    `response_time_ms` INT DEFAULT 0,
    `status_code` INT DEFAULT 200,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `request_size` INT DEFAULT 0,
    `response_size` INT DEFAULT 0,
    `error_message` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_endpoint` (`endpoint`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Update views_log to add fraud score
ALTER TABLE `views_log`
ADD COLUMN IF NOT EXISTS `fraud_score` INT DEFAULT 0 AFTER `earnings`,
ADD COLUMN IF NOT EXISTS `is_suspicious` TINYINT(1) DEFAULT 0 AFTER `fraud_score`,
ADD INDEX `idx_fraud_score` (`fraud_score`),
ADD INDEX `idx_is_suspicious` (`is_suspicious`);

-- 12. Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_views_user_date ON views_log(user_id, viewed_at);
CREATE INDEX IF NOT EXISTS idx_views_link_date ON views_log(link_id, viewed_at);
CREATE INDEX IF NOT EXISTS idx_views_country ON views_log(country_code);
CREATE INDEX IF NOT EXISTS idx_views_device ON views_log(device_type);
CREATE INDEX IF NOT EXISTS idx_views_browser ON views_log(browser);
CREATE INDEX IF NOT EXISTS idx_views_hour ON views_log(hour_of_day);

-- 13. Create materialized view for performance (use event to refresh)
CREATE OR REPLACE VIEW v_user_daily_stats AS
SELECT 
    user_id,
    DATE(viewed_at) as date,
    COUNT(*) as views,
    COUNT(DISTINCT ip_address) as unique_visitors,
    SUM(CASE WHEN is_counted = 1 THEN 1 ELSE 0 END) as counted_views,
    SUM(earnings) as earnings,
    AVG(watch_duration) as avg_watch_time,
    COUNT(DISTINCT country_code) as countries_reached,
    COUNT(DISTINCT device_type) as device_types
FROM views_log
WHERE is_counted = 1
GROUP BY user_id, DATE(viewed_at);

-- 14. Create event for daily performance metrics calculation
DELIMITER $$
CREATE EVENT IF NOT EXISTS calculate_daily_performance
ON SCHEDULE EVERY 1 DAY
STARTS CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 00:30:00')
DO BEGIN
    -- Calculate and store video performance metrics
    INSERT INTO video_performance_metrics 
    (link_id, user_id, date, total_views, unique_viewers, avg_watch_time, earnings)
    SELECT 
        link_id,
        user_id,
        DATE_SUB(CURDATE(), INTERVAL 1 DAY) as date,
        COUNT(*) as total_views,
        COUNT(DISTINCT ip_address) as unique_viewers,
        AVG(watch_duration) as avg_watch_time,
        SUM(earnings) as earnings
    FROM views_log
    WHERE DATE(viewed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    AND is_counted = 1
    GROUP BY link_id, user_id
    ON DUPLICATE KEY UPDATE
        total_views = VALUES(total_views),
        unique_viewers = VALUES(unique_viewers),
        avg_watch_time = VALUES(avg_watch_time),
        earnings = VALUES(earnings);
END$$
DELIMITER ;

-- 15. Add last_activity timestamp to users
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `last_activity_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`;

-- 16. Create stored procedure for fraud detection
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS sp_detect_fraud(IN p_user_id INT)
BEGIN
    DECLARE v_rapid_clicks INT;
    DECLARE v_bot_traffic INT;
    DECLARE v_unusual_spike INT;
    
    -- Detect rapid clicks
    SELECT COUNT(*) INTO v_rapid_clicks
    FROM (
        SELECT ip_address, COUNT(*) as clicks
        FROM views_log
        WHERE user_id = p_user_id
        AND viewed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY ip_address
        HAVING clicks >= 10
    ) AS rapid;
    
    -- Detect bot traffic
    SELECT COUNT(*) INTO v_bot_traffic
    FROM views_log
    WHERE user_id = p_user_id
    AND viewed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    AND (browser = 'Unknown' OR browser = '' OR browser IS NULL);
    
    -- Insert alerts if found
    IF v_rapid_clicks > 0 THEN
        INSERT INTO fraud_alerts (user_id, alert_type, severity, message)
        VALUES (p_user_id, 'rapid_clicks', 'high', 
                CONCAT(v_rapid_clicks, ' IPs detected with rapid clicking pattern'));
    END IF;
    
    IF v_bot_traffic > 20 THEN
        INSERT INTO fraud_alerts (user_id, alert_type, severity, message)
        VALUES (p_user_id, 'bot_traffic', 'medium', 
                CONCAT(v_bot_traffic, ' views from suspicious browsers'));
    END IF;
END$$
DELIMITER ;

-- Done! Advanced features database schema is ready.
