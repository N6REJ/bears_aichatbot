-- Manual cleanup SQL for Bears AI Chatbot
-- Run these queries in phpMyAdmin or similar database tool
-- BACKUP YOUR DATABASE FIRST!

-- Remove package entry
DELETE FROM `#__extensions` WHERE `element` = 'pkg_bears_aichatbot' AND `type` = 'package';

-- Remove component
DELETE FROM `#__extensions` WHERE `element` = 'com_bears_aichatbot' AND `type` = 'component';

-- Remove module
DELETE FROM `#__extensions` WHERE `element` = 'mod_bears_aichatbot' AND `type` = 'module';

-- Remove plugins
DELETE FROM `#__extensions` WHERE `element` = 'bears_aichatbot' AND `type` = 'plugin' AND `folder` = 'task';
DELETE FROM `#__extensions` WHERE `element` = 'bears_aichatbot' AND `type` = 'plugin' AND `folder` = 'content';
DELETE FROM `#__extensions` WHERE `element` = 'bears_aichatbotinstaller' AND `type` = 'plugin' AND `folder` = 'system';

-- Remove module instances
DELETE FROM `#__modules` WHERE `module` = 'mod_bears_aichatbot';

-- Remove scheduler tasks
DELETE FROM `#__scheduler_tasks` WHERE `type` LIKE 'bears_aichatbot.%';

-- Remove Bears AI Chatbot tables
DROP TABLE IF EXISTS `#__aichatbot_usage`;
DROP TABLE IF EXISTS `#__aichatbot_docs`;
DROP TABLE IF EXISTS `#__aichatbot_jobs`;
DROP TABLE IF EXISTS `#__aichatbot_state`;
DROP TABLE IF EXISTS `#__aichatbot_collection_stats`;
DROP TABLE IF EXISTS `#__aichatbot_keywords`;

-- Remove menu items (if any)
DELETE FROM `#__menu` WHERE `component_id` IN (
    SELECT `extension_id` FROM `#__extensions` WHERE `element` = 'com_bears_aichatbot'
);

-- Note: Replace #__ with your actual database prefix (usually something like jos_ or joomla_)
