--
-- Update from 1.0.0 to 1.0.1
-- Fix table structure for existing installations
--

-- Fix aichatbot_usage table structure
ALTER TABLE `#__aichatbot_usage` 
ADD COLUMN IF NOT EXISTS `module_id` INT DEFAULT NULL AFTER `created_at`,
ADD COLUMN IF NOT EXISTS `collection_id` VARCHAR(191) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `model` VARCHAR(191) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `endpoint` VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `retrieved` INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `article_count` INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS `kunena_count` INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS `url_count` INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS `message_len` INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS `answer_len` INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS `status_code` INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `duration_ms` INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `request_bytes` INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `response_bytes` INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `retrieved_top_score` DECIMAL(6,4) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `price_prompt` DECIMAL(10,6) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `price_completion` DECIMAL(10,6) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `currency` VARCHAR(8) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `estimated_cost` DECIMAL(12,6) DEFAULT 0;

-- Add missing indexes
ALTER TABLE `#__aichatbot_usage` ADD INDEX IF NOT EXISTS `idx_module_id` (`module_id`);

-- Drop old columns that shouldn't exist
ALTER TABLE `#__aichatbot_usage` 
DROP COLUMN IF EXISTS `user_message`,
DROP COLUMN IF EXISTS `bot_response`,
DROP COLUMN IF EXISTS `cost_usd`,
DROP COLUMN IF EXISTS `latency_ms`,
DROP COLUMN IF EXISTS `session_id`,
DROP COLUMN IF EXISTS `user_ip`;

-- Recreate keywords table with correct structure
DROP TABLE IF EXISTS `#__aichatbot_keywords`;
CREATE TABLE `#__aichatbot_keywords` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `keyword` VARCHAR(100) NOT NULL,
    `usage_count` INT DEFAULT 1,
    `first_used` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `avg_tokens` DECIMAL(8,2) DEFAULT 0,
    `total_tokens` INT DEFAULT 0,
    `success_rate` DECIMAL(5,2) DEFAULT 0,
    `answered_count` INT DEFAULT 0,
    `refused_count` INT DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_keyword` (`keyword`),
    KEY `idx_usage_count` (`usage_count`),
    KEY `idx_last_used` (`last_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- Fix state table
ALTER TABLE `#__aichatbot_state`
ADD COLUMN IF NOT EXISTS `last_sync` DATETIME DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `sync_status` VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
