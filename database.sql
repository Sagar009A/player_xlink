"-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 07, 2025 at 06:45 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u988479389_te`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`u988479389_te`@`127.0.0.1` PROCEDURE `sp_get_user_videos` (IN `p_user_id` INT, IN `p_limit` INT, IN `p_offset` INT)   BEGIN
    SELECT 
        av.*,
        GROUP_CONCAT(DISTINCT avs.server_url) as all_server_urls,
        COUNT(DISTINCT avs.id) as server_count
    FROM api_videos av
    LEFT JOIN api_video_servers avs ON avs.api_video_id = av.id AND avs.is_active = 1
    WHERE av.user_id = p_user_id
    GROUP BY av.id
    ORDER BY av.created_at DESC
    LIMIT p_limit OFFSET p_offset;
END$$

CREATE DEFINER=`u988479389_te`@`127.0.0.1` PROCEDURE `sp_get_video_analytics` (IN `p_video_id` VARCHAR(100), IN `p_days` INT)   BEGIN
    SELECT 
        view_date,
        views_count,
        unique_views,
        downloads_count,
        watch_time_seconds,
        earnings,
        top_countries,
        top_referrers
    FROM api_video_analytics
    WHERE video_id = p_video_id 
        AND view_date >= DATE_SUB(CURDATE(), INTERVAL p_days DAY)
    ORDER BY view_date DESC;
END$$

CREATE DEFINER=`u988479389_te`@`127.0.0.1` PROCEDURE `sp_refresh_user_video_count` (IN `p_user_id` INT)   BEGIN
    DECLARE v_total_videos INT;
    DECLARE v_total_views BIGINT;
    DECLARE v_total_earnings DECIMAL(12,4);
    DECLARE v_total_storage BIGINT;
    
    SELECT 
        COUNT(*),
        SUM(total_views),
        SUM(total_earnings),
        SUM(video_size)
    INTO 
        v_total_videos,
        v_total_views,
        v_total_earnings,
        v_total_storage
    FROM api_videos
    WHERE user_id = p_user_id AND is_active = 1;
    
    UPDATE api_users 
    SET total_videos = COALESCE(v_total_videos, 0),
        total_storage_used = COALESCE(v_total_storage, 0)
    WHERE user_id = p_user_id;
    
    UPDATE api_user_video_collections
    SET total_videos = COALESCE(v_total_videos, 0),
        total_views = COALESCE(v_total_views, 0),
        total_earnings = COALESCE(v_total_earnings, 0),
        total_storage_bytes = COALESCE(v_total_storage, 0)
    WHERE user_id = p_user_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `api_active_videos_with_servers`
-- (See below for the actual view)
--
CREATE TABLE `api_active_videos_with_servers` (
`id` bigint(20)
,`video_id` varchar(100)
,`user_id` int(11)
,`username` varchar(50)
,`api_user_id` int(11)
,`original_url` text
,`video_title` varchar(255)
,`video_description` text
,`thumbnail_url` text
,`video_server_url` varchar(1000)
,`video_platform` varchar(50)
,`video_quality` varchar(20)
,`video_duration` int(11)
,`video_size` bigint(20)
,`video_format` varchar(20)
,`short_code` varchar(20)
,`custom_alias` varchar(100)
,`link_id` int(11)
,`total_views` int(11)
,`total_downloads` int(11)
,`total_earnings` decimal(10,4)
,`video_expires_at` datetime
,`is_active` tinyint(1)
,`is_public` tinyint(1)
,`is_monetized` tinyint(1)
,`password_protected` varchar(255)
,`tags` longtext
,`category` varchar(50)
,`language` varchar(20)
,`country_restrictions` longtext
,`upload_ip` varchar(45)
,`last_accessed_at` timestamp
,`created_at` timestamp
,`updated_at` timestamp
,`server_url` varchar(1000)
,`server_type` varchar(50)
,`server_quality` varchar(20)
,`is_primary_server` tinyint(1)
);

-- --------------------------------------------------------

--
-- Table structure for table `api_batch_operations`
--

CREATE TABLE `api_batch_operations` (
  `id` bigint(20) NOT NULL,
  `batch_id` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `operation_type` varchar(50) NOT NULL COMMENT 'bulk_upload, bulk_delete, bulk_update',
  `total_items` int(11) DEFAULT 0,
  `processed_items` int(11) DEFAULT 0,
  `successful_items` int(11) DEFAULT 0,
  `failed_items` int(11) DEFAULT 0,
  `status` enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
  `video_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`video_ids`)),
  `error_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`error_log`)),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_rate_limits`
--

CREATE TABLE `api_rate_limits` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `api_key` varchar(64) DEFAULT NULL,
  `endpoint` varchar(255) DEFAULT NULL,
  `request_count` int(11) DEFAULT 0,
  `window_start` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_requests_log`
--

CREATE TABLE `api_requests_log` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `api_user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `api_key` varchar(64) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL COMMENT 'GET, POST, PUT, DELETE',
  `request_path` varchar(500) DEFAULT NULL,
  `request_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_params`)),
  `request_body` text DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `response_message` text DEFAULT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `video_id` varchar(100) DEFAULT NULL COMMENT 'If request related to specific video',
  `action_type` varchar(50) DEFAULT NULL COMMENT 'upload, delete, update, view, etc',
  `success` tinyint(1) DEFAULT 1,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_users`
--

CREATE TABLE `api_users` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `api_key` varchar(64) NOT NULL,
  `api_secret` varchar(128) DEFAULT NULL,
  `total_videos` int(11) DEFAULT 0,
  `total_api_calls` bigint(20) DEFAULT 0,
  `total_storage_used` bigint(20) DEFAULT 0 COMMENT 'in bytes',
  `max_videos_allowed` int(11) DEFAULT 1000,
  `max_storage_allowed` bigint(20) DEFAULT 10737418240 COMMENT '10GB default',
  `api_status` enum('active','suspended','expired','trial') DEFAULT 'active',
  `last_api_call` timestamp NULL DEFAULT NULL,
  `api_tier` enum('free','basic','premium','enterprise') DEFAULT 'free',
  `rate_limit_per_minute` int(11) DEFAULT 60,
  `rate_limit_per_hour` int(11) DEFAULT 1000,
  `ip_whitelist` text DEFAULT NULL,
  `webhook_url` varchar(500) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `api_users`
--

INSERT INTO `api_users` (`id`, `user_id`, `username`, `api_key`, `api_secret`, `total_videos`, `total_api_calls`, `total_storage_used`, `max_videos_allowed`, `max_storage_allowed`, `api_status`, `last_api_call`, `api_tier`, `rate_limit_per_minute`, `rate_limit_per_hour`, `ip_whitelist`, `webhook_url`, `metadata`, `created_at`, `updated_at`) VALUES
(1, 3, 'bhai67', '1bb552c628a975b768fa83b78348af17065aeb5cc74bac53539eff50215bde50', NULL, 0, 0, 0, 1000, 10737418240, 'active', NULL, 'free', 60, 1000, NULL, NULL, NULL, '2025-10-24 07:24:09', '2025-11-01 14:07:35'),
(2, 2, 'admin', '6f9a45a2901e0e83686415cd9365a3889c7a03a847546051aed4afc73f23af77', NULL, 0, 0, 0, 1000, 10737418240, 'active', NULL, 'free', 60, 1000, NULL, NULL, NULL, '2025-10-23 14:23:10', '2025-11-01 14:07:35'),
(3, 1, 'Sagar31', '99863a6aa0e7602bdf5131d9ba02858b8c0ec788947221413701016b2fdeaee9', NULL, 0, 0, 0, 1000, 10737418240, 'active', NULL, 'free', 60, 1000, NULL, NULL, NULL, '2025-10-23 14:13:40', '2025-11-01 14:07:35');

-- --------------------------------------------------------

--
-- Stand-in structure for view `api_user_videos_summary`
-- (See below for the actual view)
--
CREATE TABLE `api_user_videos_summary` (
`user_id` int(11)
,`username` varchar(50)
,`api_key` varchar(64)
,`total_videos` bigint(21)
,`total_views` decimal(32,0)
,`total_downloads` decimal(32,0)
,`total_earnings` decimal(32,4)
,`total_storage_bytes` decimal(41,0)
,`latest_upload` timestamp
,`api_status` enum('active','suspended','expired','trial')
,`api_tier` enum('free','basic','premium','enterprise')
);

-- --------------------------------------------------------

--
-- Table structure for table `api_user_video_collections`
--

CREATE TABLE `api_user_video_collections` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `total_videos` int(11) DEFAULT 0,
  `total_active_videos` int(11) DEFAULT 0,
  `total_views` bigint(20) DEFAULT 0,
  `total_downloads` bigint(20) DEFAULT 0,
  `total_earnings` decimal(12,4) DEFAULT 0.0000,
  `total_storage_bytes` bigint(20) DEFAULT 0,
  `avg_video_duration` int(11) DEFAULT 0,
  `most_viewed_video_id` varchar(100) DEFAULT NULL,
  `latest_upload_at` timestamp NULL DEFAULT NULL,
  `video_ids_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of all video IDs' CHECK (json_valid(`video_ids_json`)),
  `platforms_used` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'List of platforms used' CHECK (json_valid(`platforms_used`)),
  `categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`categories`)),
  `last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_videos`
--

CREATE TABLE `api_videos` (
  `id` bigint(20) NOT NULL,
  `video_id` varchar(100) NOT NULL COMMENT 'Unique video identifier',
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `api_user_id` int(11) NOT NULL,
  `original_url` text NOT NULL,
  `video_title` varchar(255) DEFAULT NULL,
  `video_description` text DEFAULT NULL,
  `thumbnail_url` text DEFAULT NULL,
  `video_server_url` varchar(1000) DEFAULT NULL COMMENT 'Direct server link',
  `video_platform` varchar(50) DEFAULT NULL COMMENT 'terabox, streamtape, etc',
  `video_quality` varchar(20) DEFAULT NULL COMMENT '360p, 720p, 1080p',
  `video_duration` int(11) DEFAULT NULL COMMENT 'in seconds',
  `video_size` bigint(20) DEFAULT NULL COMMENT 'in bytes',
  `video_format` varchar(20) DEFAULT NULL COMMENT 'mp4, mkv, etc',
  `short_code` varchar(20) NOT NULL,
  `custom_alias` varchar(100) DEFAULT NULL,
  `link_id` int(11) DEFAULT NULL COMMENT 'Reference to links table',
  `total_views` int(11) DEFAULT 0,
  `total_downloads` int(11) DEFAULT 0,
  `total_earnings` decimal(10,4) DEFAULT 0.0000,
  `video_expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_public` tinyint(1) DEFAULT 1,
  `is_monetized` tinyint(1) DEFAULT 1,
  `password_protected` varchar(255) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `category` varchar(50) DEFAULT NULL,
  `language` varchar(20) DEFAULT 'en',
  `country_restrictions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`country_restrictions`)),
  `upload_ip` varchar(45) DEFAULT NULL,
  `last_accessed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `api_videos`
--
DELIMITER $$
CREATE TRIGGER `after_video_delete` AFTER DELETE ON `api_videos` FOR EACH ROW BEGIN
    UPDATE api_users 
    SET total_videos = GREATEST(total_videos - 1, 0),
        total_storage_used = GREATEST(total_storage_used - COALESCE(OLD.video_size, 0), 0)
    WHERE user_id = OLD.user_id;
    
    UPDATE api_user_video_collections
    SET total_videos = GREATEST(total_videos - 1, 0),
        total_active_videos = GREATEST(total_active_videos - IF(OLD.is_active, 1, 0), 0)
    WHERE user_id = OLD.user_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_video_insert` AFTER INSERT ON `api_videos` FOR EACH ROW BEGIN
    UPDATE api_users 
    SET total_videos = total_videos + 1,
        total_storage_used = total_storage_used + COALESCE(NEW.video_size, 0)
    WHERE user_id = NEW.user_id;
    
    -- Also update collection
    INSERT INTO api_user_video_collections (user_id, username, total_videos, total_active_videos, latest_upload_at)
    VALUES (NEW.user_id, NEW.username, 1, IF(NEW.is_active, 1, 0), NOW())
    ON DUPLICATE KEY UPDATE 
        total_videos = total_videos + 1,
        total_active_videos = total_active_videos + IF(NEW.is_active, 1, 0),
        latest_upload_at = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_video_view_update` AFTER UPDATE ON `api_videos` FOR EACH ROW BEGIN
    IF NEW.total_views > OLD.total_views THEN
        -- Insert or update daily analytics
        INSERT INTO api_video_analytics (
            video_id, api_video_id, user_id, view_date, views_count, unique_views
        )
        VALUES (
            NEW.video_id, NEW.id, NEW.user_id, CURDATE(), 
            NEW.total_views - OLD.total_views, 1
        )
        ON DUPLICATE KEY UPDATE 
            views_count = views_count + (NEW.total_views - OLD.total_views),
            unique_views = unique_views + 1;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `api_video_analytics`
--

CREATE TABLE `api_video_analytics` (
  `id` bigint(20) NOT NULL,
  `video_id` varchar(100) NOT NULL,
  `api_video_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `view_date` date NOT NULL,
  `views_count` int(11) DEFAULT 0,
  `unique_views` int(11) DEFAULT 0,
  `downloads_count` int(11) DEFAULT 0,
  `watch_time_seconds` bigint(20) DEFAULT 0,
  `avg_watch_percentage` decimal(5,2) DEFAULT 0.00,
  `earnings` decimal(10,4) DEFAULT 0.0000,
  `top_countries` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`top_countries`)),
  `top_referrers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`top_referrers`)),
  `device_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`device_breakdown`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_video_servers`
--

CREATE TABLE `api_video_servers` (
  `id` bigint(20) NOT NULL,
  `video_id` varchar(100) NOT NULL,
  `api_video_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `server_name` varchar(50) NOT NULL COMMENT 'primary, backup1, backup2, etc',
  `server_url` varchar(1000) NOT NULL,
  `server_type` varchar(50) DEFAULT NULL COMMENT 'terabox, streamtape, filemoon, etc',
  `quality` varchar(20) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `speed_test_mbps` decimal(10,2) DEFAULT NULL,
  `last_checked_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_webhooks`
--

CREATE TABLE `api_webhooks` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL COMMENT 'video.uploaded, video.viewed, video.deleted',
  `webhook_url` varchar(500) NOT NULL,
  `video_id` varchar(100) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `status` enum('pending','sent','failed','retrying') DEFAULT 'pending',
  `retry_count` int(11) DEFAULT 0,
  `response_code` int(11) DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bot_command_logs`
--

CREATE TABLE `bot_command_logs` (
  `id` int(11) NOT NULL,
  `telegram_user_id` bigint(20) NOT NULL,
  `command` varchar(100) NOT NULL,
  `parameters` text DEFAULT NULL,
  `response_status` varchar(50) DEFAULT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `bot_command_logs`
--

INSERT INTO `bot_command_logs` (`id`, `telegram_user_id`, `command`, `parameters`, `response_status`, `executed_at`) VALUES
(1, 7956174350, '/start', NULL, 'success', '2025-11-07 17:58:41'),
(2, 7956174350, '/setapi', '{\"status\":\"success\"}', 'success', '2025-11-07 17:58:50'),
(3, 7956174350, '/stats', NULL, 'success', '2025-11-07 17:59:01'),
(4, 7956174350, '/start', NULL, 'success', '2025-11-07 18:24:01');

-- --------------------------------------------------------

--
-- Table structure for table `bot_sessions`
--

CREATE TABLE `bot_sessions` (
  `id` int(11) NOT NULL,
  `telegram_user_id` bigint(20) NOT NULL,
  `session_key` varchar(100) NOT NULL,
  `session_data` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bot_users`
--

CREATE TABLE `bot_users` (
  `id` int(11) NOT NULL,
  `telegram_user_id` bigint(20) NOT NULL,
  `telegram_username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `bot_users`
--

INSERT INTO `bot_users` (`id`, `telegram_user_id`, `telegram_username`, `first_name`, `last_name`, `user_id`, `api_key`, `is_active`, `registration_date`, `last_activity`) VALUES
(1, 7956174350, 'WinWheelPlus_Support', 'WinWheel', 'Plus', 3, '1bb552c628a975b768fa83b78348af17065aeb5cc74bac53539eff50215bde50', 1, '2025-11-07 17:58:41', '2025-11-07 18:24:01');

-- --------------------------------------------------------

--
-- Table structure for table `content_reports`
--

CREATE TABLE `content_reports` (
  `id` int(11) NOT NULL,
  `link_id` int(11) NOT NULL,
  `short_code` varchar(20) DEFAULT NULL,
  `reason` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `reporter_ip` varchar(45) DEFAULT NULL,
  `reporter_user_agent` text DEFAULT NULL,
  `status` enum('pending','reviewed','resolved','dismissed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cpm_rates`
--

CREATE TABLE `cpm_rates` (
  `id` int(11) NOT NULL,
  `rate_name` varchar(100) DEFAULT NULL,
  `traffic_source` varchar(100) DEFAULT NULL,
  `country_tier` enum('tier1','tier2','tier3','default') DEFAULT 'default',
  `countries` text DEFAULT NULL,
  `rate_per_1000` decimal(10,4) DEFAULT 1.0000,
  `bonus_multiplier` decimal(5,2) DEFAULT 1.00,
  `is_active` tinyint(1) DEFAULT 1,
  `priority` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cpm_rates`
--

INSERT INTO `cpm_rates` (`id`, `rate_name`, `traffic_source`, `country_tier`, `countries`, `rate_per_1000`, `bonus_multiplier`, `is_active`, `priority`, `created_at`) VALUES
(1, 'Tier 1 Countries', NULL, 'tier1', 'US,CA,GB,AU,DE,FR,NL,SE,NO,DK,FI,CH,AT,BE,IE,NZ', 2.5000, 1.50, 1, 10, '2025-10-23 11:53:05'),
(2, 'Tier 2 Countries', NULL, 'tier2', 'ES,IT,PT,GR,PL,CZ,BR,AR,MX,CL,TR,RU,UA,SA,AE,IL', 1.5000, 1.20, 1, 9, '2025-10-23 11:53:05'),
(3, 'Tier 3 Countries', NULL, 'tier3', 'IN,PK,BD,ID,PH,VN,TH,MY,EG,NG,KE,ZA', 0.8000, 1.00, 1, 8, '2025-10-23 11:53:05'),
(4, 'Default Rate', NULL, 'default', '*', 1.0000, 1.00, 1, 1, '2025-10-23 11:53:05');

-- --------------------------------------------------------

--
-- Table structure for table `currency_rates`
--

CREATE TABLE `currency_rates` (
  `id` int(11) NOT NULL,
  `currency_code` varchar(10) NOT NULL,
  `rate_to_usd` decimal(15,6) NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `currency_rates`
--

INSERT INTO `currency_rates` (`id`, `currency_code`, `rate_to_usd`, `updated_at`) VALUES
(1, 'USD', 1.000000, '2025-11-07 17:47:47'),
(2, 'EUR', 0.866600, '2025-11-07 17:47:47'),
(3, 'GBP', 0.762000, '2025-11-07 17:47:47'),
(4, 'INR', 88.676400, '2025-11-07 17:47:47'),
(5, 'IDR', 16676.986400, '2025-11-07 17:47:47'),
(6, 'BRL', 5.351100, '2025-11-07 17:47:47'),
(7, 'AUD', 1.541300, '2025-11-07 17:47:47'),
(8, 'CAD', 1.410900, '2025-11-07 17:47:47'),
(10, 'AED', 3.672500, '2025-11-07 17:47:47'),
(11, 'AFN', 66.329100, '2025-11-07 17:47:47'),
(12, 'ALL', 83.878300, '2025-11-07 17:47:47'),
(13, 'AMD', 382.591200, '2025-11-07 17:47:47'),
(14, 'ANG', 1.790000, '2025-11-07 17:47:47'),
(15, 'AOA', 920.113500, '2025-11-07 17:47:47'),
(16, 'ARS', 1447.500000, '2025-11-07 17:47:47'),
(18, 'AWG', 1.790000, '2025-11-07 17:47:47'),
(19, 'AZN', 1.698900, '2025-11-07 17:47:47'),
(20, 'BAM', 1.695100, '2025-11-07 17:47:47'),
(21, 'BBD', 2.000000, '2025-11-07 17:47:47'),
(22, 'BDT', 121.980300, '2025-11-07 17:47:47'),
(23, 'BGN', 1.695200, '2025-11-07 17:47:47'),
(24, 'BHD', 0.376000, '2025-11-07 17:47:47'),
(25, 'BIF', 2951.674800, '2025-11-07 17:47:47'),
(26, 'BMD', 1.000000, '2025-11-07 17:47:47'),
(27, 'BND', 1.304600, '2025-11-07 17:47:47'),
(28, 'BOB', 6.917700, '2025-11-07 17:47:47'),
(30, 'BSD', 1.000000, '2025-11-07 17:47:47'),
(31, 'BTN', 88.677700, '2025-11-07 17:47:47'),
(32, 'BWP', 13.821500, '2025-11-07 17:47:47'),
(33, 'BYN', 3.257400, '2025-11-07 17:47:47'),
(34, 'BZD', 2.000000, '2025-11-07 17:47:47'),
(36, 'CDF', 2244.538800, '2025-11-07 17:47:47'),
(37, 'CHF', 0.807000, '2025-11-07 17:47:47'),
(38, 'CLF', 0.023880, '2025-11-07 17:47:47'),
(39, 'CLP', 943.816400, '2025-11-07 17:47:47'),
(40, 'CNH', 7.121500, '2025-11-07 17:47:47'),
(41, 'CNY', 7.120200, '2025-11-07 17:47:47'),
(42, 'COP', 3832.972000, '2025-11-07 17:47:47'),
(43, 'CRC', 501.885400, '2025-11-07 17:47:47'),
(44, 'CUP', 24.000000, '2025-11-07 17:47:47'),
(45, 'CVE', 95.565500, '2025-11-07 17:47:47'),
(46, 'CZK', 21.097100, '2025-11-07 17:47:47'),
(47, 'DJF', 177.721000, '2025-11-07 17:47:47'),
(48, 'DKK', 6.465800, '2025-11-07 17:47:47'),
(49, 'DOP', 64.239300, '2025-11-07 17:47:47'),
(50, 'DZD', 130.628700, '2025-11-07 17:47:47'),
(51, 'EGP', 47.338400, '2025-11-07 17:47:47'),
(52, 'ERN', 15.000000, '2025-11-07 17:47:47'),
(53, 'ETB', 152.232700, '2025-11-07 17:47:47'),
(55, 'FJD', 2.280300, '2025-11-07 17:47:47'),
(56, 'FKP', 0.762300, '2025-11-07 17:47:47'),
(57, 'FOK', 6.466100, '2025-11-07 17:47:47'),
(59, 'GEL', 2.707300, '2025-11-07 17:47:47'),
(60, 'GGP', 0.762300, '2025-11-07 17:47:47'),
(61, 'GHS', 11.119100, '2025-11-07 17:47:47'),
(62, 'GIP', 0.762300, '2025-11-07 17:47:47'),
(63, 'GMD', 73.394400, '2025-11-07 17:47:47'),
(64, 'GNF', 8702.226400, '2025-11-07 17:47:47'),
(65, 'GTQ', 7.657000, '2025-11-07 17:47:47'),
(66, 'GYD', 209.196900, '2025-11-07 17:47:47'),
(67, 'HKD', 7.774800, '2025-11-07 17:47:47'),
(68, 'HNL', 26.290700, '2025-11-07 17:47:47'),
(69, 'HRK', 6.530100, '2025-11-07 17:47:47'),
(70, 'HTG', 130.930900, '2025-11-07 17:47:47'),
(71, 'HUF', 334.597400, '2025-11-07 17:47:47'),
(73, 'ILS', 3.262200, '2025-11-07 17:47:47'),
(74, 'IMP', 0.762300, '2025-11-07 17:47:47'),
(76, 'IQD', 1308.787100, '2025-11-07 17:47:47'),
(77, 'IRR', 42415.872600, '2025-11-07 17:47:47'),
(78, 'ISK', 126.864300, '2025-11-07 17:47:47'),
(79, 'JEP', 0.762300, '2025-11-07 17:47:47'),
(80, 'JMD', 160.626000, '2025-11-07 17:47:47'),
(81, 'JOD', 0.709000, '2025-11-07 17:47:47'),
(82, 'JPY', 153.228200, '2025-11-07 17:47:47'),
(83, 'KES', 129.091600, '2025-11-07 17:47:47'),
(84, 'KGS', 87.410400, '2025-11-07 17:47:47'),
(85, 'KHR', 4020.710100, '2025-11-07 17:47:47'),
(86, 'KID', 1.540500, '2025-11-07 17:47:47'),
(87, 'KMF', 426.383300, '2025-11-07 17:47:47'),
(88, 'KRW', 1446.483600, '2025-11-07 17:47:47'),
(89, 'KWD', 0.306900, '2025-11-07 17:47:47'),
(90, 'KYD', 0.833300, '2025-11-07 17:47:47'),
(91, 'KZT', 525.842600, '2025-11-07 17:47:47'),
(92, 'LAK', 21712.907200, '2025-11-07 17:47:47'),
(93, 'LBP', 89500.000000, '2025-11-07 17:47:47'),
(94, 'LKR', 304.522400, '2025-11-07 17:47:47'),
(95, 'LRD', 182.840100, '2025-11-07 17:47:47'),
(96, 'LSL', 17.369700, '2025-11-07 17:47:47'),
(97, 'LYD', 5.467800, '2025-11-07 17:47:47'),
(98, 'MAD', 9.286200, '2025-11-07 17:47:47'),
(99, 'MDL', 17.095600, '2025-11-07 17:47:47'),
(100, 'MGA', 4504.179600, '2025-11-07 17:47:47'),
(101, 'MKD', 53.563600, '2025-11-07 17:47:47'),
(102, 'MMK', 2096.979900, '2025-11-07 17:47:47'),
(103, 'MNT', 3615.694100, '2025-11-07 17:47:47'),
(104, 'MOP', 8.008900, '2025-11-07 17:47:47'),
(105, 'MRU', 39.837400, '2025-11-07 17:47:47'),
(106, 'MUR', 45.961400, '2025-11-07 17:47:47'),
(107, 'MVR', 15.431700, '2025-11-07 17:47:47'),
(108, 'MWK', 1742.935300, '2025-11-07 17:47:47'),
(109, 'MXN', 18.584000, '2025-11-07 17:47:47'),
(110, 'MYR', 4.183700, '2025-11-07 17:47:47'),
(111, 'MZN', 63.704400, '2025-11-07 17:47:47'),
(112, 'NAD', 17.369700, '2025-11-07 17:47:47'),
(113, 'NGN', 1436.828700, '2025-11-07 17:47:47'),
(114, 'NIO', 36.756800, '2025-11-07 17:47:47'),
(115, 'NOK', 10.195300, '2025-11-07 17:47:47'),
(116, 'NPR', 141.884300, '2025-11-07 17:47:47'),
(117, 'NZD', 1.772300, '2025-11-07 17:47:47'),
(118, 'OMR', 0.384500, '2025-11-07 17:47:47'),
(119, 'PAB', 1.000000, '2025-11-07 17:47:47'),
(120, 'PEN', 3.374400, '2025-11-07 17:47:47'),
(121, 'PGK', 4.264000, '2025-11-07 17:47:47'),
(122, 'PHP', 58.928700, '2025-11-07 17:47:47'),
(123, 'PKR', 282.680400, '2025-11-07 17:47:47'),
(124, 'PLN', 3.684700, '2025-11-07 17:47:47'),
(125, 'PYG', 7085.457700, '2025-11-07 17:47:47'),
(126, 'QAR', 3.640000, '2025-11-07 17:47:47'),
(127, 'RON', 4.411100, '2025-11-07 17:47:47'),
(128, 'RSD', 101.698500, '2025-11-07 17:47:47'),
(129, 'RUB', 81.235600, '2025-11-07 17:47:47'),
(130, 'RWF', 1456.861000, '2025-11-07 17:47:47'),
(131, 'SAR', 3.750000, '2025-11-07 17:47:47'),
(132, 'SBD', 8.231900, '2025-11-07 17:47:47'),
(133, 'SCR', 13.856900, '2025-11-07 17:47:47'),
(134, 'SDG', 511.761200, '2025-11-07 17:47:47'),
(135, 'SEK', 9.565600, '2025-11-07 17:47:47'),
(136, 'SGD', 1.304300, '2025-11-07 17:47:47'),
(137, 'SHP', 0.762300, '2025-11-07 17:47:47'),
(138, 'SLE', 23.199400, '2025-11-07 17:47:47'),
(139, 'SLL', 23199.379500, '2025-11-07 17:47:47'),
(140, 'SOS', 571.816600, '2025-11-07 17:47:47'),
(141, 'SRD', 39.082800, '2025-11-07 17:47:47'),
(142, 'SSP', 4682.456100, '2025-11-07 17:47:47'),
(143, 'STN', 21.233900, '2025-11-07 17:47:47'),
(144, 'SYP', 11013.358400, '2025-11-07 17:47:47'),
(145, 'SZL', 17.369700, '2025-11-07 17:47:47'),
(146, 'THB', 32.396000, '2025-11-07 17:47:47'),
(147, 'TJS', 9.281200, '2025-11-07 17:47:47'),
(148, 'TMT', 3.499700, '2025-11-07 17:47:47'),
(149, 'TND', 2.951400, '2025-11-07 17:47:47'),
(150, 'TOP', 2.367700, '2025-11-07 17:47:47'),
(151, 'TRY', 42.183400, '2025-11-07 17:47:47'),
(152, 'TTD', 6.754300, '2025-11-07 17:47:47'),
(153, 'TVD', 1.540500, '2025-11-07 17:47:47'),
(154, 'TWD', 30.941000, '2025-11-07 17:47:47'),
(155, 'TZS', 2455.251100, '2025-11-07 17:47:47'),
(156, 'UAH', 42.054700, '2025-11-07 17:47:47'),
(157, 'UGX', 3468.030000, '2025-11-07 17:47:47'),
(158, 'UYU', 39.782400, '2025-11-07 17:47:47'),
(159, 'UZS', 11954.293400, '2025-11-07 17:47:47'),
(160, 'VES', 228.479600, '2025-11-07 17:47:47'),
(161, 'VND', 26163.170700, '2025-11-07 17:47:47'),
(162, 'VUV', 121.287800, '2025-11-07 17:47:47'),
(163, 'WST', 2.787900, '2025-11-07 17:47:47'),
(164, 'XAF', 568.511100, '2025-11-07 17:47:47'),
(165, 'XCD', 2.700000, '2025-11-07 17:47:47'),
(166, 'XCG', 1.790000, '2025-11-07 17:47:47'),
(167, 'XDR', 0.738500, '2025-11-07 17:47:47'),
(168, 'XOF', 568.511100, '2025-11-07 17:47:47'),
(169, 'XPF', 103.423800, '2025-11-07 17:47:47'),
(170, 'YER', 238.709600, '2025-11-07 17:47:47'),
(171, 'ZAR', 17.372700, '2025-11-07 17:47:47'),
(172, 'ZMW', 22.548800, '2025-11-07 17:47:47'),
(173, 'ZWL', 26.381300, '2025-11-07 17:47:47');

-- --------------------------------------------------------

--
-- Table structure for table `extraction_queue`
--

CREATE TABLE `extraction_queue` (
  `id` int(11) NOT NULL,
  `link_id` int(11) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `last_attempt` timestamp NULL DEFAULT NULL,
  `status` enum('pending','processing','failed','success') DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `folders`
--

CREATE TABLE `folders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `links`
--

CREATE TABLE `links` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `original_url` text NOT NULL,
  `short_code` varchar(20) NOT NULL,
  `custom_alias` varchar(100) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `thumbnail_url` text DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `auto_fetch_thumbnail` tinyint(1) DEFAULT 1,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `today_views` int(11) DEFAULT 0,
  `earnings` decimal(10,2) DEFAULT 0.00,
  `traffic_source` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_view_at` timestamp NULL DEFAULT NULL,
  `direct_video_url` varchar(1000) DEFAULT NULL,
  `video_platform` varchar(50) DEFAULT NULL,
  `video_expires_at` datetime DEFAULT NULL,
  `video_quality` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `links`
--

INSERT INTO `links` (`id`, `user_id`, `folder_id`, `original_url`, `short_code`, `custom_alias`, `title`, `description`, `thumbnail_url`, `thumbnail_path`, `auto_fetch_thumbnail`, `qr_code_path`, `views`, `today_views`, `earnings`, `traffic_source`, `is_active`, `created_at`, `last_view_at`, `direct_video_url`, `video_platform`, `video_expires_at`, `video_quality`) VALUES
(1, 3, NULL, 'https://youtu.be/elO6FX9ICcg?si=pEyz0UTvaDyPCTxD', 'O3PcLeoa', NULL, 'YouTube Video', '', 'https://img.youtube.com/vi/elO6FX9ICcg/maxresdefault.jpg', NULL, 1, NULL, 2, 0, 0.00, NULL, 1, '2025-10-24 09:36:18', NULL, NULL, NULL, NULL, NULL),
(2, 3, NULL, 'https://youtu.be/V7d0GZ0owRI?si=WstKpPkbzFJ1YpJ7', 'z2Cn2WRB', NULL, 'YouTube Video', '', 'https://img.youtube.com/vi/V7d0GZ0owRI/maxresdefault.jpg', NULL, 1, NULL, 0, 0, 0.00, NULL, 1, '2025-10-24 11:50:13', NULL, NULL, NULL, NULL, NULL),
(3, 3, NULL, 'https://teraboxurl.com/s/1TIZoUbQaiogVBF03otnqDQ', 'B6PYM5ua', NULL, 'ERTYUIOVBNMDFGHJKLEDFGHJ (9).ts', '', 'https://data.terabox.com/thumbnail/30fb302f28bedbe385a0f95d5ea2fbae?fid=4401296353443-250528-523049678866042&time=1761422400&rt=pr&sign=FDTAER-DCb740ccc5511e5e8fedcff06b081203-IYmeJThAjydTl2o1GumtDSat0kM%3D&expires=10y&chkv=0&chkbd=0&chkpc=&dp-logid=9221817653680727145&dp-callid=0&size=c850_u580&quality=100&vuk=4401296353443&ft=video', NULL, 1, NULL, 2, 0, 0.00, NULL, 1, '2025-10-25 20:40:26', NULL, NULL, NULL, NULL, NULL),
(4, 3, NULL, 'https://1024terabox.com/s/1QLMLeVmP-KvPvEpV5V02YA', '2ZZaBLKi', NULL, '_Girl_Anika_en_3_AdobeExpress.mp4', '', 'https://dm-data.terabox.app/thumbnail/28586504555568e0dfd835518ecff6f6?fid=81365513450790-250528-226236015407430&time=1761465600&rt=pr&sign=FDTAER-DCb740ccc5511e5e8fedcff06b081203-aypfB44dYPOm2bYsfcJqPrh9z0w%3D&expires=10y&chkv=0&chkbd=0&chkpc=&dp-logid=9862040285639034&dp-callid=0&size=c850_u580&quality=100&vuk=81365513450790&ft=video', NULL, 1, NULL, 1, 0, 0.00, NULL, 1, '2025-10-26 08:29:17', NULL, NULL, NULL, NULL, NULL),
(5, 3, NULL, 'https://1024terabox.com/s/1QLMLeVmP-KvPvEpV5V02YA', '8yLqx9ll', NULL, '_Girl_Anika_en_3_AdobeExpress.mp4', '', 'https://dm-data.terabox.app/thumbnail/28586504555568e0dfd835518ecff6f6?fid=81365513450790-250528-226236015407430&time=1761465600&rt=pr&sign=FDTAER-DCb740ccc5511e5e8fedcff06b081203-aypfB44dYPOm2bYsfcJqPrh9z0w%3D&expires=10y&chkv=0&chkbd=0&chkpc=&dp-logid=10008780737928782&dp-callid=0&size=c850_u580&quality=100&vuk=81365513450790&ft=video', NULL, 1, NULL, 1, 0, 0.00, NULL, 1, '2025-10-26 08:38:17', NULL, NULL, NULL, NULL, NULL),
(6, 3, NULL, 'https://1024terabox.com/s/1oo6wUKrymaOgVUqTp3G9_A', 'xO7oxv7S', NULL, '2024-05-02-16-31-54(3).mp4', '', 'https://dm-data.terabox.app/thumbnail/4fd7f42978cd0fe4902abcef4278ffac?fid=81365513450790-250528-463521637729970&time=1761465600&rt=pr&sign=FDTAER-DCb740ccc5511e5e8fedcff06b081203-oNy8HamU%2B3cetiRgMncSnWcXrIU%3D&expires=10y&chkv=0&chkbd=0&chkpc=&dp-logid=10027613340712113&dp-callid=0&size=c850_u580&quality=100&vuk=81365513450790&ft=video', NULL, 1, NULL, 2, 0, 0.00, NULL, 1, '2025-10-26 08:39:25', NULL, NULL, NULL, NULL, NULL),
(7, 3, NULL, 'https://1024terabox.com/s/1yQMI_F-5EAPsJTw87VHlBA', 'mjT65uSW', NULL, 'Xzonesx2685.mp4', '', 'https://data.terabox.app/thumbnail/20548349a70038e7f9271be66e7aa497?fid=4400105493460-250528-58349081599412&time=1762077600&rt=pr&sign=FDTAER-DCb740ccc5511e5e8fedcff06b081203-f0yjvYGVMA%2FB8LF%2B7g2ZeJ%2B9sis%3D&expires=10y&chkv=0&chkbd=0&chkpc=&dp-logid=174278075912485571&dp-callid=0&size=c850_u580&quality=100&vuk=4400105493460&ft=video', NULL, 1, NULL, 1, 0, 0.00, NULL, 1, '2025-11-02 10:37:50', NULL, NULL, NULL, NULL, NULL),
(8, 3, NULL, 'http://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4', 'wU5DGYiY', NULL, 'Sintel.mp4', '', '', NULL, 1, NULL, 14, 0, 0.00, NULL, 1, '2025-11-03 08:33:47', NULL, 'http://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4', 'DirectVideo', NULL, 'Unknown'),
(9, 3, NULL, 'https://teraboxurl.com/s/1sYlhQRHA8g1Wfq2u4XlSIg', 'TTeaTqim', NULL, '2023-06-03-17-26-27.mp4', '', 'https://data.terabox.app/thumbnail/d0d84f7d15bc4d6a92c5f6af1fb15893?fid=4402219559035-250528-631888069236569&time=1762416000&rt=pr&sign=FDTAER-DCb740ccc5511e5e8fedcff06b081203-Cqf6S5yp3qZ1%2BivbA6LuFOfAWQY%3D&expires=10y&chkv=0&chkbd=0&chkpc=&dp-logid=264548540163030421&dp-callid=0&size=c850_u580&quality=100&vuk=4402219559035&ft=video', NULL, 1, NULL, 3, 0, 0.00, NULL, 1, '2025-11-06 08:02:07', NULL, NULL, NULL, NULL, NULL),
(10, 2, NULL, 'https://teraboxurl.com/s/1r2W5Bx0T8tTAJ9o58B7OPg', 'f5ahnnRU', NULL, '2023-02-23-22-57-47.mp4', '', 'https://data.terabox.app/thumbnail/bd5cb2181d96aba96e065549d4663e18?fid=4402219559035-250528-153459565040122&time=1762419600&rt=pr&sign=FDTAER-DCb740ccc5511e5e8fedcff06b081203-s2gO5pE5oTRB3UCr%2BsoLWGTNllc%3D&expires=10y&chkv=0&chkbd=0&chkpc=&dp-logid=265905567554109862&dp-callid=0&size=c850_u580&quality=100&vuk=4402219559035&ft=video', NULL, 1, NULL, 7, 0, 0.00, NULL, 1, '2025-11-06 09:26:23', NULL, NULL, NULL, NULL, NULL),
(11, 2, NULL, 'https://teraboxurl.com/s/1__Gi0RMuOgY1GtFejpaTig', 'YMQu95Nr', NULL, 'Insta Srch ( Fun.Bross ) Post 402.mp4', '', 'https://data.terabox.app/thumbnail/8444932bcaa51e0e7ba8e665dc46ac9e?fid=4401146149342-250528-56233796500500&time=1762423200&rt=pr&sign=FDTAER-DCb740ccc5511e5e8fedcff06b081203-j2P97PiSrDlkn7fMikf%2BxVxnQ%2Fg%3D&expires=10y&chkv=0&chkbd=0&chkpc=&dp-logid=267311850850467181&dp-callid=0&size=c850_u580&quality=100&vuk=4401146149342&ft=video', NULL, 1, NULL, 2, 0, 0.00, NULL, 1, '2025-11-06 10:53:49', NULL, NULL, NULL, NULL, NULL),
(12, 4, NULL, 'https://1024terabox.com/s/16xFACaMh5s1RkAklx5vJcg', 'JtT5PhPW', NULL, '2024-02-28-22-11-39(1)(480P version).mov', '', 'https://data.terabox.app/thumbnail/cec52b1b7705fe282bbdb6b868041f40?fid=4401127846727-250528-687817851192437&time=1762426800&rt=pr&sign=FDTAER-DCb740ccc5511e5e8fedcff06b081203-i%2BIoMWul3Yd9NUiyhyNjrBtXl44%3D&expires=10y&chkv=0&chkbd=0&chkpc=&dp-logid=267760316966785799&dp-callid=0&size=c850_u580&quality=100&vuk=4401127846727&ft=video', NULL, 1, NULL, 1, 0, 0.00, NULL, 1, '2025-11-06 11:21:54', NULL, NULL, NULL, NULL, NULL),
(13, 3, NULL, 'https://1024terabox.com/s/16y9PvRU-Kx5LEb83Yh6iAg', '2kgS2qJ6', NULL, 'TheaShwr.mp4', '', 'https://dm-data.terabox.app/thumbnail/4c877d9b70d03cafa0b94eb271869ffa?fid=4398125562143-250528-551626453049888&time=1762538400&rt=pr&sign=FDTAER-DCb740ccc5511e5e8fedcff06b081203-wK%2FvnE2aRIC225g1%2FmxAia%2Bc2m0%3D&expires=10y&chkv=0&chkbd=0&chkpc=&dp-logid=297937927253607424&dp-callid=0&size=c850_u580&quality=100&vuk=4398125562143&ft=video', NULL, 1, NULL, 1, 0, 0.00, NULL, 1, '2025-11-07 18:35:17', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `link_stats`
--

CREATE TABLE `link_stats` (
  `id` int(11) NOT NULL,
  `link_id` int(11) NOT NULL,
  `views` int(11) DEFAULT 0,
  `earnings` decimal(10,2) DEFAULT 0.00,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_gateways`
--

CREATE TABLE `payment_gateways` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `min_amount` decimal(10,2) DEFAULT 5.00,
  `max_amount` decimal(10,2) DEFAULT 10000.00,
  `processing_time` varchar(100) DEFAULT '1-3 business days',
  `required_fields` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `icon` varchar(50) DEFAULT 'fa-wallet',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_earnings`
--

CREATE TABLE `referral_earnings` (
  `id` int(11) NOT NULL,
  `referrer_id` int(11) NOT NULL,
  `referred_user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `commission_percent` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` varchar(50) DEFAULT 'text',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `updated_at`) VALUES
(1, 'default_cpm_rate', '1', 'decimal', '2025-10-23 14:41:49'),
(2, 'referral_commission', '10', 'integer', '2025-10-23 11:53:05'),
(3, 'min_withdrawal', '5', 'decimal', '2025-10-23 14:41:49'),
(4, 'stats_update_interval', '4', 'integer', '2025-10-23 11:53:05'),
(5, 'app_login_enabled', '1', 'boolean', '2025-10-23 11:53:05'),
(6, 'video_organization_enabled', '1', 'boolean', '2025-10-23 11:53:05'),
(7, 'auto_fetch_thumbnail', '0', 'boolean', '2025-10-24 07:45:02'),
(8, 'daily_view_limit_per_ip', '50', 'integer', '2025-10-23 11:53:05'),
(9, 'api_rate_limit', '100', 'integer', '2025-10-23 11:53:05'),
(10, 'dark_mode_enabled', '1', 'boolean', '2025-10-23 11:53:05'),
(11, 'site_name', 'LinkStreamX', 'text', '2025-10-23 11:53:05'),
(12, 'site_tagline', 'Turn Every View Into Value', 'text', '2025-10-23 11:53:05'),
(13, 'currency_update_interval', '48', 'integer', '2025-10-23 14:41:49'),
(40, 'terabox_js_token', '564037D37FF7C391108D94A9E2964894B9336A8A350DCA3988A1899F6C91152B25A6656B45C63F7D2B70AF0F76930912E72B4F67F731002CDE47A39C503F2F8D', 'text', '2025-11-07 18:30:02'),
(41, 'terabox_token_last_update', '1762540202', 'text', '2025-11-07 18:30:02'),
(48, 'cpm_rate', '0.50', 'text', '2025-11-02 09:38:41'),
(49, 'currency_code', 'USD', 'text', '2025-11-02 09:38:41'),
(50, 'admin_email', 'admin@teraboxurll.in', 'text', '2025-11-02 09:38:41'),
(51, 'default_redirect_delay', '10', 'text', '2025-11-02 09:38:41'),
(52, 'enable_registration', '1', 'text', '2025-11-02 09:38:41'),
(53, 'enable_email_verification', '0', 'text', '2025-11-02 09:38:41'),
(54, 'ads_enabled', '1', 'text', '2025-11-02 09:38:41'),
(55, 'maintenance_mode', '0', 'text', '2025-11-02 09:38:41'),
(64, 'last_stats_update', '2025-11-07 18:45:01', 'text', '2025-11-07 18:45:01'),
(290, 'last_view_reset', '2025-11-07', 'text', '2025-11-07 00:00:02');

-- --------------------------------------------------------

--
-- Table structure for table `shortened_links`
--

CREATE TABLE `shortened_links` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `short_code` varchar(50) NOT NULL,
  `original_url` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `password` varchar(255) NOT NULL,
  `telegram_id` varchar(50) DEFAULT NULL,
  `traffic_source` varchar(100) DEFAULT NULL,
  `traffic_category` varchar(50) DEFAULT NULL,
  `api_key` varchar(64) DEFAULT NULL,
  `referral_code` varchar(20) DEFAULT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected','blocked') DEFAULT 'pending',
  `balance` decimal(10,2) DEFAULT 0.00,
  `total_views` int(11) DEFAULT 0,
  `total_earnings` decimal(10,2) DEFAULT 0.00,
  `daily_view_limit` int(11) DEFAULT 50,
  `preferred_currency` varchar(10) DEFAULT 'USD',
  `ip_whitelist` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `role`, `password`, `telegram_id`, `traffic_source`, `traffic_category`, `api_key`, `referral_code`, `referred_by`, `status`, `balance`, `total_views`, `total_earnings`, `daily_view_limit`, `preferred_currency`, `ip_whitelist`, `created_at`, `updated_at`) VALUES
(1, 'Sagar31', 'sagarpubgnew@gmail.com', 'user', '$2y$12$sZPw1aUZd88ICXrrsj2QducY1A/nNDiaDnDeoxjNtPAwsk52vEbQi', '@hajjdjd', 'Telegram', '1K-10K', '99863a6aa0e7602bdf5131d9ba02858b8c0ec788947221413701016b2fdeaee9', '7JNCTG5V', NULL, 'approved', 0.00, 0, 0.00, 50, 'USD', NULL, '2025-10-23 14:13:40', '2025-10-23 14:36:30'),
(2, 'admin', 'admin@linkstreamx.com', 'admin', '$2y$12$I6LxyQPOsUmjKRydAIqgaeZTzXFHSTPB8L4LJiW0NgApfj.3Mkmpa', 'admin_telegram', 'Direct', '1M+', '6f9a45a2901e0e83686415cd9365a3889c7a03a847546051aed4afc73f23af77', 'ADMIN001', NULL, 'approved', 0.00, 9, 0.00, 50, 'USD', NULL, '2025-10-23 14:23:10', '2025-11-06 12:20:02'),
(3, 'bhai67', 'clasherdel10@gmail.com', 'user', '$2y$12$QdlJ8B25o4c6.lpzcQs3Gu/rAfWqv6b0hSIJGfcDpoGj8eWrZyNjK', '@hajjdjdg', 'Facebook', '10K-100K', '1bb552c628a975b768fa83b78348af17065aeb5cc74bac53539eff50215bde50', 'NRY6CEGD', NULL, 'approved', 0.00, 27, 0.00, 50, 'USD', NULL, '2025-10-24 07:24:09', '2025-11-07 18:40:01'),
(4, 'admin111', 'sonukumarsaw179@gmail.com', 'user', '$2y$12$1kPPYVrgB1VuiR2SpFzVOeh1kyZio1ncVoTRp8Lgrwc2JCjYEqJjq', '7261565490', 'Telegram', '1K-10K', '10a614e61739d2d80e33b632deeab207275346ab9943880d4fb1e201f66b7a80', 'X2FLMH4D', NULL, 'approved', 0.00, 1, 0.00, 50, 'USD', NULL, '2025-11-05 13:31:09', '2025-11-06 11:25:01');

-- --------------------------------------------------------

--
-- Table structure for table `views_log`
--

CREATE TABLE `views_log` (
  `id` bigint(20) NOT NULL,
  `link_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `country_code` varchar(5) DEFAULT NULL,
  `country_name` varchar(100) DEFAULT NULL,
  `device_type` varchar(20) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `referrer` text DEFAULT NULL,
  `watch_duration` int(11) DEFAULT 0,
  `is_counted` tinyint(1) DEFAULT 1,
  `earnings` decimal(10,4) DEFAULT 0.0000,
  `viewed_at` timestamp NULL DEFAULT current_timestamp(),
  `hour_of_day` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `views_log`
--

INSERT INTO `views_log` (`id`, `link_id`, `user_id`, `ip_address`, `country_code`, `country_name`, `device_type`, `browser`, `os`, `referrer`, `watch_duration`, `is_counted`, `earnings`, `viewed_at`, `hour_of_day`) VALUES
(1, 1, 3, '152.58.96.85', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 0, 1, 0.0005, '2025-10-24 10:13:25', NULL),
(2, 1, 3, '152.58.96.85', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 5, 0, 0.0000, '2025-10-24 10:17:01', NULL),
(3, 1, 3, '2401:4900:a5f3:dbc5:6023:2f0b:f071:a8a3', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 5, 1, 0.0010, '2025-10-25 07:37:26', NULL),
(4, 1, 3, '2401:4900:a5f3:dbc5:6023:2f0b:f071:a8a3', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 5, 0, 0.0000, '2025-10-25 07:46:12', NULL),
(5, 1, 3, '2401:4900:a5f3:dbc5:6023:2f0b:f071:a8a3', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 5, 0, 0.0000, '2025-10-25 08:41:34', NULL),
(6, 3, 3, '2401:4900:86aa:f225:49bc:fca:e3fe:d9d3', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 5, 1, 0.0010, '2025-10-25 20:40:36', NULL),
(7, 3, 3, '2401:4900:86aa:f225:49bc:fca:e3fe:d9d3', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 5, 0, 0.0000, '2025-10-25 20:41:16', NULL),
(8, 3, 3, '2401:4900:86aa:f225:49bc:fca:e3fe:d9d3', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 5, 0, 0.0000, '2025-10-25 20:51:38', NULL),
(9, 3, 3, '2401:4900:86aa:f225:49bc:fca:e3fe:d9d3', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 6, 0, 0.0000, '2025-10-25 20:51:56', NULL),
(10, 3, 3, '152.59.87.192', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 53, 1, 0.0010, '2025-10-26 04:42:13', NULL),
(11, 3, 3, '152.59.87.192', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 5, 0, 0.0000, '2025-10-26 04:49:49', NULL),
(12, 3, 3, '152.59.87.192', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 6, 0, 0.0000, '2025-10-26 04:57:09', NULL),
(13, 4, 3, '152.59.120.74', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 535, 1, 0.0010, '2025-10-26 08:29:30', NULL),
(14, 5, 3, '152.59.120.74', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 66, 1, 0.0010, '2025-10-26 08:38:26', NULL),
(15, 6, 3, '152.59.120.74', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 7, 1, 0.0010, '2025-10-26 08:39:33', NULL),
(16, 6, 3, '152.59.120.74', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-10-26 08:41:46', NULL),
(17, 6, 3, '106.219.85.52', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 0, 1, 0.0010, '2025-11-01 13:16:44', NULL),
(18, 6, 3, '106.219.85.52', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 56, 0, 0.0000, '2025-11-01 13:17:40', NULL),
(19, 6, 3, '106.219.85.52', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 1, 0, 0.0000, '2025-11-01 13:17:41', NULL),
(20, 6, 3, '106.219.85.52', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 80, 0, 0.0000, '2025-11-01 13:17:42', NULL),
(21, 6, 3, '106.219.85.52', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-01 13:19:03', NULL),
(22, 6, 3, '106.219.85.52', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 4, 0, 0.0000, '2025-11-01 13:19:07', NULL),
(23, 6, 3, '106.219.85.52', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-01 16:02:26', NULL),
(24, 7, 3, '152.59.88.162', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 33, 1, 0.0010, '2025-11-02 10:37:55', NULL),
(25, 7, 3, '152.59.88.162', 'XX', 'Unknown', 'mobile', 'Unknown', NULL, '', 101, 0, 0.0000, '2025-11-02 10:38:50', NULL),
(26, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 0, 1, 0.0010, '2025-11-03 08:33:52', NULL),
(27, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 0, 0, 0.0000, '2025-11-03 08:33:52', NULL),
(28, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 9, 0, 0.0000, '2025-11-03 08:34:02', NULL),
(29, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 0, 0, 0.0000, '2025-11-03 08:34:02', NULL),
(30, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 39, 0, 0.0000, '2025-11-03 08:34:04', NULL),
(31, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 0, 0, 0.0000, '2025-11-03 08:34:04', NULL),
(32, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 6, 0, 0.0000, '2025-11-03 08:45:11', NULL),
(33, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 0, 0, 0.0000, '2025-11-03 08:45:11', NULL),
(34, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 16, 0, 0.0000, '2025-11-03 08:45:32', NULL),
(35, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 0, 0, 0.0000, '2025-11-03 08:45:32', NULL),
(36, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-03 08:46:42', NULL),
(37, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 2, 0, 0.0000, '2025-11-03 08:46:43', NULL),
(38, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 9, 0, 0.0000, '2025-11-03 09:07:56', NULL),
(39, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 0, 0, 0.0000, '2025-11-03 09:07:56', NULL),
(40, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-03 09:17:14', NULL),
(41, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 0, 0, 0.0000, '2025-11-03 09:17:15', NULL),
(42, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 3, 0, 0.0000, '2025-11-03 09:17:40', NULL),
(43, 8, 3, '2401:4900:bbb2:e03b:b0de:4bed:59a0:a4e0', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 0, 0, 0.0000, '2025-11-03 09:17:40', NULL),
(44, 8, 3, '2401:4900:bbb2:e03b:a533:b60f:5f18:891e', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 0, 1, 0.0010, '2025-11-03 09:43:23', NULL),
(45, 8, 3, '2401:4900:bbb2:e03b:a533:b60f:5f18:891e', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 12, 0, 0.0000, '2025-11-03 09:43:26', NULL),
(46, 8, 3, '2401:4900:bbb2:e03b:a533:b60f:5f18:891e', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-03 10:04:01', NULL),
(47, 8, 3, '2401:4900:bbb2:e03b:a533:b60f:5f18:891e', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 29, 0, 0.0000, '2025-11-03 10:04:02', NULL),
(48, 8, 3, '2401:4900:bbb2:e03b:a533:b60f:5f18:891e', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-03 10:04:32', NULL),
(49, 8, 3, '2401:4900:bbb2:e03b:a533:b60f:5f18:891e', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 173, 0, 0.0000, '2025-11-03 10:04:35', NULL),
(50, 8, 3, '2401:4900:bbb2:e03b:a533:b60f:5f18:891e', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 13, 0, 0.0000, '2025-11-03 10:07:29', NULL),
(51, 8, 3, '2401:4900:bbb2:e03b:a533:b60f:5f18:891e', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-03 10:07:43', NULL),
(52, 8, 3, '2401:4900:bbb2:e03b:a533:b60f:5f18:891e', 'XX', 'Unknown', 'desktop', 'Unknown', NULL, '', 0, 0, 0.0000, '2025-11-03 10:08:37', NULL),
(53, 8, 3, '2401:4900:bbb2:e03b:a533:b60f:5f18:891e', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-03 10:22:08', NULL),
(54, 8, 3, '2401:4900:bbb2:e03b:a533:b60f:5f18:891e', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 5, 0, 0.0000, '2025-11-03 10:22:09', NULL),
(55, 8, 3, '2401:4900:bbb2:e03b:a533:b60f:5f18:891e', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 5, 0, 0.0000, '2025-11-03 10:22:15', NULL),
(56, 8, 3, '2401:4900:bbb2:e03b:6d54:cf97:36a:e3f8', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 1, 1, 0.0010, '2025-11-03 10:38:22', NULL),
(57, 8, 3, '2401:4900:bbb2:e03b:6d54:cf97:36a:e3f8', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 213, 0, 0.0000, '2025-11-03 10:38:24', NULL),
(58, 8, 3, '2401:4900:bbb2:e03b:6d54:cf97:36a:e3f8', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-03 10:41:57', NULL),
(59, 8, 3, '2401:4900:bbb2:e03b:6d54:cf97:36a:e3f8', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 18, 0, 0.0000, '2025-11-03 10:42:15', NULL),
(60, 8, 3, '2401:4900:bbb2:e03b:6d54:cf97:36a:e3f8', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-03 10:56:17', NULL),
(61, 8, 3, '2401:4900:bbb2:e03b:6d54:cf97:36a:e3f8', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 39, 0, 0.0000, '2025-11-03 10:56:18', NULL),
(62, 8, 3, '106.219.87.176', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 0, 1, 0.0010, '2025-11-03 11:02:37', NULL),
(63, 8, 3, '106.219.87.176', 'XX', 'Unknown', 'desktop', 'Unknown', NULL, '', 0, 0, 0.0000, '2025-11-03 11:03:27', NULL),
(64, 8, 3, '106.219.87.176', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 27, 0, 0.0000, '2025-11-03 11:03:38', NULL),
(65, 8, 3, '106.219.87.176', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-03 11:06:21', NULL),
(66, 8, 3, '106.219.87.176', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 1061, 0, 0.0000, '2025-11-03 11:06:36', NULL),
(67, 8, 3, '2401:4900:bbb2:e03b:6d54:cf97:36a:e3f8', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-03 11:16:59', NULL),
(68, 8, 3, '2401:4900:bbb2:e03b:e1a4:afec:9d97:f1d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 1, 0.0010, '2025-11-03 12:16:05', NULL),
(69, 8, 3, '2401:4900:bbb2:e03b:e1a4:afec:9d97:f1d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-03 12:16:06', NULL),
(70, 8, 3, '2401:4900:bbb2:e03b:e1a4:afec:9d97:f1d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 263, 0, 0.0000, '2025-11-03 12:16:08', NULL),
(71, 8, 3, '2401:4900:bbb2:e03b:e1a4:afec:9d97:f1d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 465, 0, 0.0000, '2025-11-03 12:20:32', NULL),
(72, 8, 3, '2401:4900:bbb2:e03b:e1a4:afec:9d97:f1d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 6, 0, 0.0000, '2025-11-03 12:30:18', NULL),
(73, 8, 3, '2401:4900:bbb2:e03b:e1a4:afec:9d97:f1d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 8, 0, 0.0000, '2025-11-03 12:55:20', NULL),
(74, 8, 3, '2401:4900:bbb2:e03b:e1a4:afec:9d97:f1d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 7, 0, 0.0000, '2025-11-03 12:56:08', NULL),
(75, 8, 3, '2401:4900:bbb2:e03b:e1a4:afec:9d97:f1d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 17, 0, 0.0000, '2025-11-03 12:57:14', NULL),
(76, 8, 3, '2401:4900:bbb2:e03b:e1a4:afec:9d97:f1d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 84, 0, 0.0000, '2025-11-03 12:58:38', NULL),
(77, 8, 3, '2401:4900:bbb2:e03b:e1a4:afec:9d97:f1d9', 'XX', 'Unknown', 'mobile', 'Unknown', NULL, '', 0, 0, 0.0000, '2025-11-03 12:58:58', NULL),
(78, 8, 3, '2401:4900:bbb2:e03b:5403:95e6:73d8:d6d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 1, 0.0010, '2025-11-03 13:41:14', NULL),
(79, 8, 3, '2401:4900:bbb2:e03b:5403:95e6:73d8:d6d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-03 13:41:15', NULL),
(80, 8, 3, '2401:4900:bbb2:e03b:5403:95e6:73d8:d6d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 229, 0, 0.0000, '2025-11-03 13:41:17', NULL),
(81, 8, 3, '2401:4900:bbb2:e03b:5403:95e6:73d8:d6d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 121, 0, 0.0000, '2025-11-03 13:45:07', NULL),
(82, 8, 3, '2401:4900:bbb2:e03b:5403:95e6:73d8:d6d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 6, 0, 0.0000, '2025-11-03 14:15:44', NULL),
(83, 8, 3, '2401:4900:bbb2:e03b:5403:95e6:73d8:d6d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 8, 0, 0.0000, '2025-11-03 14:16:09', NULL),
(84, 8, 3, '2401:4900:bbb2:e03b:5403:95e6:73d8:d6d9', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 6, 0, 0.0000, '2025-11-03 14:16:36', NULL),
(85, 8, 3, '152.59.121.222', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 8, 1, 0.0010, '2025-11-03 16:01:43', NULL),
(86, 8, 3, '152.59.121.222', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 5, 0, 0.0000, '2025-11-03 16:06:56', NULL),
(87, 8, 3, '152.59.121.222', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 380, 0, 0.0000, '2025-11-03 16:07:14', NULL),
(88, 8, 3, '152.59.121.222', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 99, 0, 0.0000, '2025-11-03 16:25:34', NULL),
(89, 8, 3, '2401:4900:bbb2:e03b:ec19:e1f3:7277:f902', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 87, 1, 0.0010, '2025-11-03 18:17:25', NULL),
(90, 8, 3, '2409:40e5:1125:8c8f:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 1, 0.0010, '2025-11-05 13:49:29', NULL),
(91, 8, 3, '2409:40e5:1125:8c8f:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 19, 0, 0.0000, '2025-11-05 13:49:32', NULL),
(92, 8, 3, '203.192.219.229', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://www.google.com/', 0, 1, 0.0010, '2025-11-05 13:49:36', NULL),
(93, 8, 3, '2409:40e5:1125:8c8f:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-05 13:50:05', NULL),
(94, 8, 3, '2409:40e5:1125:8c8f:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 5, 0, 0.0000, '2025-11-05 13:50:07', NULL),
(95, 8, 3, '192.178.8.64', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 1, 0.0010, '2025-11-05 13:50:12', NULL),
(96, 8, 3, '64.233.172.76', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 1, 0.0010, '2025-11-05 13:50:13', NULL),
(97, 8, 3, '192.178.8.66', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 1, 0.0010, '2025-11-05 13:50:14', NULL),
(98, 8, 3, '203.192.219.229', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 0, 0, 0.0000, '2025-11-05 13:50:40', NULL),
(99, 8, 3, '2409:40e5:1125:8c8f:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-05 13:50:46', NULL),
(100, 8, 3, '2409:40e5:1125:8c8f:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 0, 0, 0.0000, '2025-11-05 13:50:48', NULL),
(101, 8, 3, '2409:40e5:1125:8c8f:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-05 13:52:49', NULL),
(102, 8, 3, '2409:40e5:1125:8c8f:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 5, 0, 0.0000, '2025-11-05 13:52:50', NULL),
(103, 8, 3, '2409:40e5:11b7:7544:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 1, 0.0010, '2025-11-06 03:04:41', NULL),
(104, 8, 3, '2409:40e5:11b7:7544:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, 'https://teraboxurll.in/wU5DGYiY', 0, 0, 0.0000, '2025-11-06 03:04:42', NULL),
(105, 9, 3, '152.59.121.93', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 4, 1, 0.0010, '2025-11-06 08:02:13', NULL),
(106, 9, 3, '152.59.121.93', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 6, 0, 0.0000, '2025-11-06 08:05:44', NULL),
(107, 9, 3, '152.59.121.93', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 12, 0, 0.0000, '2025-11-06 08:05:57', NULL),
(108, 9, 3, '152.59.121.93', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 9, 0, 0.0000, '2025-11-06 08:06:34', NULL),
(109, 9, 3, '152.59.121.93', 'XX', 'Unknown', 'desktop', 'Unknown', NULL, '', 0, 0, 0.0000, '2025-11-06 08:06:57', NULL),
(110, 9, 3, '152.59.121.93', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-06 08:19:52', NULL),
(111, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'desktop', 'Safari', NULL, '', 0, 1, 0.0010, '2025-11-06 09:11:38', NULL),
(112, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'desktop', 'Unknown', NULL, '', 0, 0, 0.0000, '2025-11-06 09:12:29', NULL),
(113, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 205, 0, 0.0000, '2025-11-06 09:13:00', NULL),
(114, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 20, 0, 0.0000, '2025-11-06 09:16:26', NULL),
(115, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 88, 0, 0.0000, '2025-11-06 09:16:47', NULL),
(116, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 1, 0, 0.0000, '2025-11-06 09:18:16', NULL),
(117, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 99, 0, 0.0000, '2025-11-06 09:18:17', NULL),
(118, 9, 3, '152.59.121.93', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 77, 0, 0.0000, '2025-11-06 09:18:27', NULL),
(119, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-06 09:19:57', NULL),
(120, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 2, 0, 0.0000, '2025-11-06 09:19:58', NULL),
(121, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 67, 0, 0.0000, '2025-11-06 09:21:05', NULL),
(122, 10, 2, '152.59.121.93', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 1, 1, 0.0010, '2025-11-06 09:26:27', NULL),
(123, 10, 2, '2401:4900:bb69:e08e:fc09:ba1c:1b38:e8e0', 'XX', 'Unknown', 'desktop', 'Unknown', NULL, '', 0, 1, 0.0010, '2025-11-06 09:30:23', NULL),
(124, 10, 2, '106.219.85.164', 'XX', 'Unknown', 'desktop', 'Unknown', NULL, '', 0, 1, 0.0010, '2025-11-06 09:33:39', NULL),
(125, 10, 2, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 5, 0, 0.0000, '2025-11-06 09:33:49', NULL),
(126, 10, 2, '2401:4900:bb69:e08e:690f:c643:7af8:ef49', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 1, 0.0010, '2025-11-06 09:57:51', NULL),
(127, 10, 2, '2401:4900:bb69:e08e:690f:c643:7af8:ef49', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 5, 0, 0.0000, '2025-11-06 09:59:30', NULL),
(128, 10, 2, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 17, 0, 0.0000, '2025-11-06 10:00:43', NULL),
(129, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 4235, 0, 0.0000, '2025-11-06 10:01:10', NULL),
(130, 10, 2, '2401:4900:bb69:e08e:690f:c643:7af8:ef49', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 21, 0, 0.0000, '2025-11-06 10:26:50', NULL),
(131, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-06 10:27:28', NULL),
(132, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 30, 0, 0.0000, '2025-11-06 10:27:29', NULL),
(133, 9, 3, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 2, 0, 0.0000, '2025-11-06 10:27:59', NULL),
(134, 10, 2, '2401:4900:bb69:e08e:690f:c643:7af8:ef49', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 88, 0, 0.0000, '2025-11-06 10:28:19', NULL),
(135, 10, 2, '2401:4900:bb69:e08e:690f:c643:7af8:ef49', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 5, 0, 0.0000, '2025-11-06 10:29:49', NULL),
(136, 10, 2, '106.219.85.164', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 2158, 0, 0.0000, '2025-11-06 10:30:44', NULL),
(137, 10, 2, '2401:4900:bb69:e08e:bc30:c48f:3abb:4a70', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 2, 1, 0.0010, '2025-11-06 10:35:17', NULL),
(138, 9, 3, '2401:4900:bb69:e08e:bc30:c48f:3abb:4a70', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 1, 1, 0.0010, '2025-11-06 10:36:14', NULL),
(139, 9, 3, '2401:4900:bb69:e08e:bc30:c48f:3abb:4a70', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-06 10:44:43', NULL),
(140, 10, 2, '2401:4900:bb69:e08e:bc30:c48f:3abb:4a70', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-06 10:47:42', NULL),
(141, 10, 2, '2401:4900:bb69:e08e:bc30:c48f:3abb:4a70', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 1, 0, 0.0000, '2025-11-06 10:48:15', NULL),
(142, 10, 2, '2401:4900:bb69:e08e:bc30:c48f:3abb:4a70', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 4, 0, 0.0000, '2025-11-06 10:53:15', NULL),
(143, 11, 2, '2401:4900:bb69:e08e:bc30:c48f:3abb:4a70', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 1, 1, 0.0010, '2025-11-06 10:53:54', NULL),
(144, 12, 4, '2409:40e5:11bc:69e7:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 2, 1, 0.0010, '2025-11-06 11:21:58', NULL),
(145, 12, 4, '2409:40e5:11bc:69e7:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 1, 0, 0.0000, '2025-11-06 11:23:23', NULL),
(146, 12, 4, '2409:40e5:11bc:69e7:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-06 11:23:25', NULL),
(147, 12, 4, '2409:40e5:11bc:69e7:c10c:6cdd:cb62:7612', 'XX', 'Unknown', 'desktop', 'Chrome', NULL, '', 2, 0, 0.0000, '2025-11-06 11:35:26', NULL),
(148, 10, 2, '106.219.87.211', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 1, 0.0010, '2025-11-06 11:49:38', NULL),
(149, 10, 2, '2401:4900:bb69:e08e:b8f5:87ba:6b59:82c0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 365, 1, 0.0010, '2025-11-06 12:16:22', NULL),
(150, 11, 2, '2401:4900:bb69:e08e:b8f5:87ba:6b59:82c0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 1, 1, 0.0010, '2025-11-06 12:17:37', NULL),
(151, 10, 2, '2401:4900:bb69:e08e:b8f5:87ba:6b59:82c0', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-06 12:24:10', NULL),
(152, 10, 2, '152.59.121.93', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 0, 0, 0.0000, '2025-11-06 13:03:00', NULL),
(153, 10, 2, '106.219.87.211', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 2, 0, 0.0000, '2025-11-06 15:46:09', NULL),
(154, 13, 3, '152.59.89.182', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 281, 1, 0.0010, '2025-11-07 18:35:27', NULL),
(155, 13, 3, '152.59.89.182', 'XX', 'Unknown', 'mobile', 'Chrome', NULL, '', 71, 0, 0.0000, '2025-11-07 18:40:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `amount_usd` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_details` text DEFAULT NULL,
  `status` enum('processing','accepted','paid','rejected') DEFAULT 'processing',
  `admin_note` text DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `api_batch_operations`
--
ALTER TABLE `api_batch_operations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_batch_id` (`batch_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `api_rate_limits`
--
ALTER TABLE `api_rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_endpoint` (`user_id`,`endpoint`,`window_start`),
  ADD KEY `idx_api_key` (`api_key`);

--
-- Indexes for table `api_requests_log`
--
ALTER TABLE `api_requests_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_api_user_id` (`api_user_id`),
  ADD KEY `idx_endpoint` (`endpoint`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_video_id` (`video_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_requests_user_endpoint` (`user_id`,`endpoint`,`created_at`);

--
-- Indexes for table `api_users`
--
ALTER TABLE `api_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_id` (`user_id`),
  ADD UNIQUE KEY `unique_api_key` (`api_key`),
  ADD KEY `idx_api_status` (`api_status`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `api_user_video_collections`
--
ALTER TABLE `api_user_video_collections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_id` (`user_id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_total_videos` (`total_videos`);

--
-- Indexes for table `api_videos`
--
ALTER TABLE `api_videos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_video_id` (`video_id`),
  ADD UNIQUE KEY `unique_short_code` (`short_code`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_api_user_id` (`api_user_id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_platform` (`video_platform`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_custom_alias` (`custom_alias`),
  ADD KEY `idx_user_videos_active` (`user_id`,`is_active`,`created_at`);

--
-- Indexes for table `api_video_analytics`
--
ALTER TABLE `api_video_analytics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_video_date` (`api_video_id`,`view_date`),
  ADD KEY `idx_video_id` (`video_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_view_date` (`view_date`),
  ADD KEY `idx_video_analytics_user_date` (`user_id`,`view_date`);

--
-- Indexes for table `api_video_servers`
--
ALTER TABLE `api_video_servers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_video_id` (`video_id`),
  ADD KEY `idx_api_video_id` (`api_video_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_primary` (`is_primary`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `api_webhooks`
--
ALTER TABLE `api_webhooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `bot_command_logs`
--
ALTER TABLE `bot_command_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bot_sessions`
--
ALTER TABLE `bot_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bot_users`
--
ALTER TABLE `bot_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telegram_user_id` (`telegram_user_id`);

--
-- Indexes for table `content_reports`
--
ALTER TABLE `content_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_link_id` (`link_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `cpm_rates`
--
ALTER TABLE `cpm_rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_source_tier` (`traffic_source`,`country_tier`);

--
-- Indexes for table `currency_rates`
--
ALTER TABLE `currency_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `currency_code` (`currency_code`),
  ADD KEY `idx_currency` (`currency_code`);

--
-- Indexes for table `extraction_queue`
--
ALTER TABLE `extraction_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `folders`
--
ALTER TABLE `folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `links`
--
ALTER TABLE `links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `short_code` (`short_code`),
  ADD KEY `idx_short_code` (`short_code`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_custom_alias` (`custom_alias`),
  ADD KEY `idx_video_platform` (`video_platform`),
  ADD KEY `idx_video_expires` (`video_expires_at`);

--
-- Indexes for table `link_stats`
--
ALTER TABLE `link_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `link_id` (`link_id`);

--
-- Indexes for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referral_earnings`
--
ALTER TABLE `referral_earnings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `referred_user_id` (`referred_user_id`),
  ADD KEY `idx_referrer` (`referrer_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_key` (`setting_key`);

--
-- Indexes for table `shortened_links`
--
ALTER TABLE `shortened_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `short_code` (`short_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `api_key` (`api_key`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `referred_by` (`referred_by`),
  ADD KEY `idx_api_key` (`api_key`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_referral` (`referral_code`);

--
-- Indexes for table `views_log`
--
ALTER TABLE `views_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_link_user` (`link_id`,`user_id`),
  ADD KEY `idx_ip_date` (`ip_address`,`viewed_at`),
  ADD KEY `idx_country` (`country_code`),
  ADD KEY `idx_hour` (`hour_of_day`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_status` (`user_id`,`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `api_batch_operations`
--
ALTER TABLE `api_batch_operations`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_rate_limits`
--
ALTER TABLE `api_rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_requests_log`
--
ALTER TABLE `api_requests_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_users`
--
ALTER TABLE `api_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `api_user_video_collections`
--
ALTER TABLE `api_user_video_collections`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_videos`
--
ALTER TABLE `api_videos`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_video_analytics`
--
ALTER TABLE `api_video_analytics`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_video_servers`
--
ALTER TABLE `api_video_servers`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_webhooks`
--
ALTER TABLE `api_webhooks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bot_command_logs`
--
ALTER TABLE `bot_command_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bot_sessions`
--
ALTER TABLE `bot_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bot_users`
--
ALTER TABLE `bot_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `content_reports`
--
ALTER TABLE `content_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cpm_rates`
--
ALTER TABLE `cpm_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `currency_rates`
--
ALTER TABLE `currency_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=999;

--
-- AUTO_INCREMENT for table `extraction_queue`
--
ALTER TABLE `extraction_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `links`
--
ALTER TABLE `links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `link_stats`
--
ALTER TABLE `link_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_earnings`
--
ALTER TABLE `referral_earnings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1412;

--
-- AUTO_INCREMENT for table `shortened_links`
--
ALTER TABLE `shortened_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `views_log`
--
ALTER TABLE `views_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=156;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `api_active_videos_with_servers`
--
DROP TABLE IF EXISTS `api_active_videos_with_servers`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u988479389_te`@`127.0.0.1` SQL SECURITY DEFINER VIEW `api_active_videos_with_servers`  AS SELECT `av`.`id` AS `id`, `av`.`video_id` AS `video_id`, `av`.`user_id` AS `user_id`, `av`.`username` AS `username`, `av`.`api_user_id` AS `api_user_id`, `av`.`original_url` AS `original_url`, `av`.`video_title` AS `video_title`, `av`.`video_description` AS `video_description`, `av`.`thumbnail_url` AS `thumbnail_url`, `av`.`video_server_url` AS `video_server_url`, `av`.`video_platform` AS `video_platform`, `av`.`video_quality` AS `video_quality`, `av`.`video_duration` AS `video_duration`, `av`.`video_size` AS `video_size`, `av`.`video_format` AS `video_format`, `av`.`short_code` AS `short_code`, `av`.`custom_alias` AS `custom_alias`, `av`.`link_id` AS `link_id`, `av`.`total_views` AS `total_views`, `av`.`total_downloads` AS `total_downloads`, `av`.`total_earnings` AS `total_earnings`, `av`.`video_expires_at` AS `video_expires_at`, `av`.`is_active` AS `is_active`, `av`.`is_public` AS `is_public`, `av`.`is_monetized` AS `is_monetized`, `av`.`password_protected` AS `password_protected`, `av`.`tags` AS `tags`, `av`.`category` AS `category`, `av`.`language` AS `language`, `av`.`country_restrictions` AS `country_restrictions`, `av`.`upload_ip` AS `upload_ip`, `av`.`last_accessed_at` AS `last_accessed_at`, `av`.`created_at` AS `created_at`, `av`.`updated_at` AS `updated_at`, `avs`.`server_url` AS `server_url`, `avs`.`server_type` AS `server_type`, `avs`.`quality` AS `server_quality`, `avs`.`is_primary` AS `is_primary_server` FROM (`api_videos` `av` left join `api_video_servers` `avs` on(`avs`.`api_video_id` = `av`.`id` and `avs`.`is_active` = 1)) WHERE `av`.`is_active` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `api_user_videos_summary`
--
DROP TABLE IF EXISTS `api_user_videos_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u988479389_te`@`127.0.0.1` SQL SECURITY DEFINER VIEW `api_user_videos_summary`  AS SELECT `u`.`id` AS `user_id`, `u`.`username` AS `username`, `au`.`api_key` AS `api_key`, count(`av`.`id`) AS `total_videos`, sum(`av`.`total_views`) AS `total_views`, sum(`av`.`total_downloads`) AS `total_downloads`, sum(`av`.`total_earnings`) AS `total_earnings`, sum(`av`.`video_size`) AS `total_storage_bytes`, max(`av`.`created_at`) AS `latest_upload`, `au`.`api_status` AS `api_status`, `au`.`api_tier` AS `api_tier` FROM ((`users` `u` join `api_users` `au` on(`au`.`user_id` = `u`.`id`)) left join `api_videos` `av` on(`av`.`user_id` = `u`.`id`)) GROUP BY `u`.`id`, `u`.`username`, `au`.`api_key`, `au`.`api_status`, `au`.`api_tier` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `api_batch_operations`
--
ALTER TABLE `api_batch_operations`
  ADD CONSTRAINT `api_batch_operations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `api_rate_limits`
--
ALTER TABLE `api_rate_limits`
  ADD CONSTRAINT `api_rate_limits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `api_requests_log`
--
ALTER TABLE `api_requests_log`
  ADD CONSTRAINT `api_requests_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `api_requests_log_ibfk_2` FOREIGN KEY (`api_user_id`) REFERENCES `api_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `api_users`
--
ALTER TABLE `api_users`
  ADD CONSTRAINT `api_users_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `api_user_video_collections`
--
ALTER TABLE `api_user_video_collections`
  ADD CONSTRAINT `api_user_video_collections_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `api_videos`
--
ALTER TABLE `api_videos`
  ADD CONSTRAINT `api_videos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `api_videos_ibfk_2` FOREIGN KEY (`api_user_id`) REFERENCES `api_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `api_video_analytics`
--
ALTER TABLE `api_video_analytics`
  ADD CONSTRAINT `api_video_analytics_ibfk_1` FOREIGN KEY (`api_video_id`) REFERENCES `api_videos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `api_video_analytics_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `api_video_servers`
--
ALTER TABLE `api_video_servers`
  ADD CONSTRAINT `api_video_servers_ibfk_1` FOREIGN KEY (`api_video_id`) REFERENCES `api_videos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `api_video_servers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `api_webhooks`
--
ALTER TABLE `api_webhooks`
  ADD CONSTRAINT `api_webhooks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `folders`
--
ALTER TABLE `folders`
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `links`
--
ALTER TABLE `links`
  ADD CONSTRAINT `links_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referral_earnings`
--
ALTER TABLE `referral_earnings`
  ADD CONSTRAINT `referral_earnings_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `referral_earnings_ibfk_2` FOREIGN KEY (`referred_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `views_log`
--
ALTER TABLE `views_log`
  ADD CONSTRAINT `views_log_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `views_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;"
