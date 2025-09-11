<?php
/**
 * Package installer script for pkg_bears_aichatbot
 * - Enables the content and task plugins by default using Joomla 5 APIs
 * - Defers Scheduler task creation to Joomla's standard Scheduled Tasks UI
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;

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
        // 1) Enable the plugins (content + task) via Extension table (standard Joomla 5)
        $this->enablePlugin('content', 'bears_aichatbot');
        $this->enablePlugin('task', 'bears_aichatbot');

        // 2) Inform admins about scheduling tasks via the standard UI
        //    Standard behavior is to let admins create and configure Scheduled Tasks from the backend.
        try {
            Factory::getApplication()->enqueueMessage(
                'Bears AI Chatbot installed. Create Scheduled Tasks for the plugin\'s task types via System → Scheduled Tasks if needed.',
                'info'
            );
        } catch (\Throwable $e) {
            // ignore if application is not available in this context
        }
    }

    protected function enablePlugin(string $folder, string $element): void
    {
        try {
            $table = Table::getInstance('Extension');

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
