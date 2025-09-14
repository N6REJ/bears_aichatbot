<?php
/**
 * Package installer script for pkg_bears_aichatbot
 * - Enables the content and task plugins by default using Joomla 5 APIs
 * - Defers Scheduler task creation to Joomla's standard Scheduled Tasks UI
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Table\Extension as ExtensionTable;
use Joomla\Database\DatabaseInterface;

/**
 * Primary installer script class keyed to how Joomla may compute the class name
 * for a package with packagename="pkg_bears_aichatbot".
 * Many Joomla versions build the class name as: type prefix + '_' + element + 'InstallerScript'
 * where element for packages equals the <packagename>.
 * Result => 'pkg_' + 'pkg_bears_aichatbot' + 'InstallerScript'
 */
class pkg_pkg_bears_aichatbotInstallerScript
{
    public function postflight($type, $parent)
    {
        // Obtain DB from installer parent to avoid DI container usage
        $db = $parent->getParent()->getDbo();

        // Enable the plugins (content + task) via Extension table (standard Joomla 5)
        $this->enablePlugin($db, 'content', 'bears_aichatbot');
        $this->enablePlugin($db, 'task', 'bears_aichatbot');
    }

    public function uninstall($parent)
    {
        // Clean up Bears AI Chatbot data during uninstallation
        try {
            $db = $parent->getParent()->getDbo();
            
            // Remove scheduler tasks
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('type') . ' LIKE ' . $db->quote('bears_aichatbot.%'));
            $db->setQuery($query);
            $db->execute();
            
            // Remove Bears AI Chatbot database tables
            $tables = [
                '#__aichatbot_usage',
                '#__aichatbot_docs', 
                '#__aichatbot_jobs',
                '#__aichatbot_state',
                '#__aichatbot_collection_stats',
                '#__aichatbot_keywords'
            ];
            
            foreach ($tables as $table) {
                try {
                    $db->setQuery("DROP TABLE IF EXISTS " . $db->quoteName($table));
                    $db->execute();
                } catch (\Throwable $e) {
                    // Ignore individual table errors
                }
            }
            
        } catch (\Throwable $e) {
            // Log error but don't fail uninstallation
            \Joomla\CMS\Log\Log::add('Bears AI Chatbot uninstall cleanup error: ' . $e->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'bears_aichatbot');
        }
        
        return true;
    }

    protected function enablePlugin(DatabaseInterface $db, string $folder, string $element): void
    {
        try {
            $table = new ExtensionTable($db);

            if ($table->load(['type' => 'plugin', 'element' => $element, 'folder' => $folder])) {
                if ((int) $table->enabled !== 1) {
                    $table->enabled = 1;
                    $table->store();
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}

// Compatibility shims for other Joomla naming expectations
if (!class_exists('pkg_bears_aichatbotInstallerScript')) {
    class pkg_bears_aichatbotInstallerScript extends pkg_pkg_bears_aichatbotInstallerScript {}
}
if (!class_exists('Pkg_bears_aichatbotInstallerScript')) {
    class Pkg_bears_aichatbotInstallerScript extends pkg_pkg_bears_aichatbotInstallerScript {}
}
if (!class_exists('Pkg_Bears_AichatbotInstallerScript')) {
    class Pkg_Bears_AichatbotInstallerScript extends pkg_pkg_bears_aichatbotInstallerScript {}
}
