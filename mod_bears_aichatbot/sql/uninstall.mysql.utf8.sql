--
-- SQL uninstallation script for mod_bears_aichatbot
-- Removes tables created by the module
--

DROP TABLE IF EXISTS `#__aichatbot_usage`;
DROP TABLE IF EXISTS `#__aichatbot_keywords`;
DROP TABLE IF EXISTS `#__aichatbot_state`;
