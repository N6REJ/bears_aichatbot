--
-- Update to version 2025.09.15.15
-- Fix table structure for existing installations
--

-- Since we can't use IF NOT EXISTS in older MySQL, we'll drop and recreate tables
-- This is safe as we're preserving data where possible

-- Recreate usage table with correct structure
DROP TABLE IF EXISTS `#__aichatbot_usage`;
CREATE TABLE `#__aichatbot_usage` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `module_id` INT DEFAULT NULL,
  `collection_id` VARCHAR(191) DEFAULT NULL,
  `model` VARCHAR(191) DEFAULT NULL,
  `endpoint` VARCHAR(255) DEFAULT NULL,
  `prompt_tokens` INT DEFAULT 0,
  `completion_tokens` INT DEFAULT 0,
  `total_tokens` INT DEFAULT 0,
  `retrieved` INT DEFAULT NULL,
  `article_count` INT DEFAULT 0,
  `kunena_count` INT DEFAULT 0,
  `url_count` INT DEFAULT 0,
  `message_len` INT DEFAULT 0,
  `answer_len` INT DEFAULT 0,
  `status_code` INT DEFAULT NULL,
  `duration_ms` INT DEFAULT NULL,
  `request_bytes` INT DEFAULT NULL,
  `response_bytes` INT DEFAULT NULL,
  `outcome` VARCHAR(20) DEFAULT NULL,
  `retrieved_top_score` DECIMAL(6,4) DEFAULT NULL,
  `price_prompt` DECIMAL(10,6) DEFAULT NULL,
  `price_completion` DECIMAL(10,6) DEFAULT NULL,
  `currency` VARCHAR(8) DEFAULT NULL,
  `estimated_cost` DECIMAL(12,6) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_module_id` (`module_id`),
  KEY `idx_outcome` (`outcome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recreate state table with all columns
DROP TABLE IF EXISTS `#__aichatbot_state`;
CREATE TABLE `#__aichatbot_state` (
  `id` INT NOT NULL DEFAULT 1,
  `collection_id` VARCHAR(191) DEFAULT NULL,
  `last_sync` DATETIME DEFAULT NULL,
  `sync_status` VARCHAR(50) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_run_reconcile` DATETIME DEFAULT NULL,
  `last_run_queue` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default state record
INSERT IGNORE INTO `#__aichatbot_state` (`id`) VALUES (1);
