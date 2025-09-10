-- Create mapping table for synced documents
CREATE TABLE IF NOT EXISTS `#__aichatbot_docs` (
  `content_id` INT NOT NULL,
  `remote_id` VARCHAR(191) DEFAULT NULL,
  `content_hash` VARCHAR(64) NOT NULL DEFAULT '',
  `last_synced` DATETIME DEFAULT NULL,
  `state` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`content_id`),
  UNIQUE KEY `idx_remote_id` (`remote_id`),
  KEY `idx_last_synced` (`last_synced`),
  KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create job queue table for background processing
CREATE TABLE IF NOT EXISTS `#__aichatbot_jobs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `content_id` INT DEFAULT NULL,
  `action` VARCHAR(20) NOT NULL,
  `attempts` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'queued',
  `last_error` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_content_action` (`content_id`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
