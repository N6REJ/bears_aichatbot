-- Installer SQL for com_bears_aichatbot
-- Ensure analytics table exists (module also lazily creates it)
CREATE TABLE IF NOT EXISTS `#__aichatbot_usage` (
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
  -- latency & size
  `duration_ms` INT DEFAULT NULL,
  `request_bytes` INT DEFAULT NULL,
  `response_bytes` INT DEFAULT NULL,
  -- outcome classification and retrieval score
  `outcome` VARCHAR(20) DEFAULT NULL,
  `retrieved_top_score` DECIMAL(6,4) DEFAULT NULL,
  -- pricing/cost fields
  `price_prompt` DECIMAL(10,6) DEFAULT NULL,
  `price_completion` DECIMAL(10,6) DEFAULT NULL,
  `currency` VARCHAR(8) DEFAULT NULL,
  `estimated_cost` DECIMAL(12,6) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_module_id` (`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Daily snapshot of collection size (number of docs) for time-series chart
CREATE TABLE IF NOT EXISTS `#__aichatbot_collection_stats` (
  `stat_date` DATE NOT NULL,
  `docs_count` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
