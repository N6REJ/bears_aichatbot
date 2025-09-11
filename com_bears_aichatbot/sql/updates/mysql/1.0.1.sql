-- Add pricing/cost columns to usage table if missing and backfill estimated_cost
ALTER TABLE `#__aichatbot_usage`
  ADD COLUMN `price_prompt` DECIMAL(10,6) DEFAULT NULL,
  ADD COLUMN `price_completion` DECIMAL(10,6) DEFAULT NULL,
  ADD COLUMN `currency` VARCHAR(8) DEFAULT NULL,
  ADD COLUMN `estimated_cost` DECIMAL(12,6) DEFAULT 0;

-- Backfill with standard package prices if estimated_cost is NULL or 0
UPDATE `#__aichatbot_usage`
SET `price_prompt` = COALESCE(`price_prompt`, 0.0004),
    `price_completion` = COALESCE(`price_completion`, 0.0006),
    `currency` = COALESCE(`currency`, 'USD'),
    `estimated_cost` = CASE
        WHEN (`prompt_tokens` IS NOT NULL OR `completion_tokens` IS NOT NULL)
        THEN ROUND(((COALESCE(`prompt_tokens`,0) / 1000.0) * COALESCE(`price_prompt`,0.0004))
           + ((COALESCE(`completion_tokens`,0) / 1000.0) * COALESCE(`price_completion`,0.0006)), 6)
        ELSE COALESCE(`estimated_cost`, 0)
    END
WHERE `estimated_cost` IS NULL OR `estimated_cost` = 0;
