-- ============================================
-- Migration Script: Update Short Code Length
-- Date: 2025-11-08
-- Description: Updates short_code column length from VARCHAR(20) to VARCHAR(50)
--              to accommodate new 22-26 character codes
-- ============================================

-- Update links table
ALTER TABLE `links` 
MODIFY COLUMN `short_code` VARCHAR(50) NOT NULL;

-- Update api_videos table
ALTER TABLE `api_videos` 
MODIFY COLUMN `short_code` VARCHAR(50) NOT NULL;

-- Update content_reports table (if exists)
ALTER TABLE `content_reports` 
MODIFY COLUMN `short_code` VARCHAR(50) DEFAULT NULL;

-- Success message
SELECT 'Short code length updated successfully!' AS message;
