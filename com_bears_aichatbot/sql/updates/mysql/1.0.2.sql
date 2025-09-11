-- Add latency, payload size, outcome, and retrieval top score columns
ALTER TABLE `#__aichatbot_usage`
  ADD COLUMN `duration_ms` INT DEFAULT NULL,
  ADD COLUMN `request_bytes` INT DEFAULT NULL,
  ADD COLUMN `response_bytes` INT DEFAULT NULL,
  ADD COLUMN `outcome` VARCHAR(20) DEFAULT NULL,
  ADD COLUMN `retrieved_top_score` DECIMAL(6,4) DEFAULT NULL;

-- Initialize outcome to 'error' for rows with status_code >= 400 where outcome is null
UPDATE `#__aichatbot_usage`
SET `outcome` = 'error'
WHERE (`status_code` IS NOT NULL AND `status_code` >= 400) AND (`outcome` IS NULL OR `outcome` = '');
