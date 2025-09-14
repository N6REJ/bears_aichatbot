<?php
/**
 * Component installer script for com_bears_aichatbot
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;

class Com_Bears_AichatbotInstallerScript extends InstallerScript
{
    /**
     * Called after any type of action
     */
    public function postflight($route, $parent)
    {
        // Create database tables on install/update
        if ($route === 'install' || $route === 'update') {
            $this->createTables();
        }
    }

    /**
     * Called on uninstallation
     */
    public function uninstall($parent)
    {
        // Clean up database tables and related data
        $this->dropTables();
        $this->cleanupRelatedData();
        return true;
    }

    /**
     * Create Bears AI Chatbot database tables
     */
    private function createTables()
    {
        try {
            $db = Factory::getDbo();
            
            // Create tables with IF NOT EXISTS to avoid errors on updates
            $queries = [
                "CREATE TABLE IF NOT EXISTS `#__aichatbot_usage` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_message` text NOT NULL,
                    `bot_response` text,
                    `prompt_tokens` int(11) DEFAULT 0,
                    `completion_tokens` int(11) DEFAULT 0,
                    `total_tokens` int(11) DEFAULT 0,
                    `cost_usd` decimal(10,6) DEFAULT 0.000000,
                    `latency_ms` int(11) DEFAULT 0,
                    `outcome` varchar(20) DEFAULT 'answered',
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `session_id` varchar(100) DEFAULT NULL,
                    `user_ip` varchar(45) DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_created_at` (`created_at`),
                    KEY `idx_outcome` (`outcome`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                "CREATE TABLE IF NOT EXISTS `#__aichatbot_docs` (
                    `content_id` int(11) NOT NULL,
                    `remote_id` varchar(255) NOT NULL,
                    `content_hash` varchar(64) NOT NULL,
                    `last_synced` datetime DEFAULT CURRENT_TIMESTAMP,
                    `state` tinyint(1) DEFAULT 1,
                    PRIMARY KEY (`content_id`),
                    UNIQUE KEY `idx_remote_id` (`remote_id`),
                    KEY `idx_last_synced` (`last_synced`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                "CREATE TABLE IF NOT EXISTS `#__aichatbot_jobs` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `content_id` int(11) NOT NULL,
                    `action` varchar(20) NOT NULL,
                    `status` varchar(20) DEFAULT 'queued',
                    `attempts` int(11) DEFAULT 0,
                    `last_error` text,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_status` (`status`),
                    KEY `idx_content_id` (`content_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                "CREATE TABLE IF NOT EXISTS `#__aichatbot_state` (
                    `id` int(11) NOT NULL DEFAULT 1,
                    `collection_id` varchar(255) DEFAULT NULL,
                    `last_run_reconcile` datetime DEFAULT NULL,
                    `last_run_queue` datetime DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                "CREATE TABLE IF NOT EXISTS `#__aichatbot_collection_stats` (
                    `stat_date` date NOT NULL,
                    `docs_count` int(11) NOT NULL DEFAULT 0,
                    PRIMARY KEY (`stat_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                "CREATE TABLE IF NOT EXISTS `#__aichatbot_keywords` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `usage_id` int(11) NOT NULL,
                    `keyword` varchar(100) NOT NULL,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_usage_id` (`usage_id`),
                    KEY `idx_keyword` (`keyword`),
                    KEY `idx_created_at` (`created_at`),
                    FOREIGN KEY (`usage_id`) REFERENCES `#__aichatbot_usage` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            ];

            foreach ($queries as $query) {
                $db->setQuery($query);
                $db->execute();
            }

            // Insert initial state record
            $db->setQuery("INSERT IGNORE INTO `#__aichatbot_state` (`id`) VALUES (1)");
            $db->execute();

        } catch (\Exception $e) {
            // Log error but don't fail installation
            \Joomla\CMS\Log\Log::add('Bears AI Chatbot table creation error: ' . $e->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'bears_aichatbot');
        }
    }

    /**
     * Drop Bears AI Chatbot database tables
     */
    private function dropTables()
    {
        try {
            $db = Factory::getDbo();
            
            $tables = [
                '#__aichatbot_keywords',
                '#__aichatbot_usage',
                '#__aichatbot_docs', 
                '#__aichatbot_jobs',
                '#__aichatbot_state',
                '#__aichatbot_collection_stats'
            ];
            
            foreach ($tables as $table) {
                try {
                    $db->setQuery("DROP TABLE IF EXISTS " . $db->quoteName($table));
                    $db->execute();
                } catch (\Exception $e) {
                    // Ignore individual table errors
                }
            }
            
        } catch (\Exception $e) {
            // Log error but don't fail uninstallation
            \Joomla\CMS\Log\Log::add('Bears AI Chatbot table cleanup error: ' . $e->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'bears_aichatbot');
        }
    }

    /**
     * Clean up related data (scheduler tasks, menu items, etc.)
     */
    private function cleanupRelatedData()
    {
        try {
            $db = Factory::getDbo();
            
            // Remove scheduler tasks
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('type') . ' LIKE ' . $db->quote('bears_aichatbot.%'));
            $db->setQuery($query);
            $db->execute();
            
            // Remove menu items for this component
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__menu'))
                ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%com_bears_aichatbot%'));
            $db->setQuery($query);
            $db->execute();
            
            // Clean up orphaned module menu assignments
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__modules_menu'))
                ->where($db->quoteName('moduleid') . ' NOT IN (SELECT ' . $db->quoteName('id') . ' FROM ' . $db->quoteName('#__modules') . ')');
            $db->setQuery($query);
            $db->execute();
            
        } catch (\Exception $e) {
            // Log error but don't fail uninstallation
            \Joomla\CMS\Log\Log::add('Bears AI Chatbot related data cleanup error: ' . $e->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'bears_aichatbot');
        }
    }
}
