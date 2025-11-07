-- Telegram Bot Users Table
-- This table stores bot users and their API keys

CREATE TABLE IF NOT EXISTS `bot_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telegram_user_id` bigint(20) NOT NULL,
  `telegram_username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'Reference to main users table',
  `api_key` varchar(255) DEFAULT NULL COMMENT 'User API key from main site',
  `is_active` tinyint(1) DEFAULT 1,
  `registration_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_user_id` (`telegram_user_id`),
  KEY `user_id` (`user_id`),
  KEY `api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bot sessions for pagination
CREATE TABLE IF NOT EXISTS `bot_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telegram_user_id` bigint(20) NOT NULL,
  `session_key` varchar(100) NOT NULL,
  `session_data` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `telegram_user_id` (`telegram_user_id`),
  KEY `session_key` (`session_key`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bot command logs for analytics
CREATE TABLE IF NOT EXISTS `bot_command_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telegram_user_id` bigint(20) NOT NULL,
  `command` varchar(100) NOT NULL,
  `parameters` text DEFAULT NULL,
  `response_status` varchar(50) DEFAULT NULL,
  `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `telegram_user_id` (`telegram_user_id`),
  KEY `command` (`command`),
  KEY `executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for better performance
ALTER TABLE `shortened_links` ADD INDEX IF NOT EXISTS `idx_user_created` (`user_id`, `created_at` DESC);
ALTER TABLE `link_stats` ADD INDEX IF NOT EXISTS `idx_link_stats` (`link_id`, `timestamp` DESC);
