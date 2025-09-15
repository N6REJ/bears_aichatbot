<?php
/**
 * Component installer script for com_bears_aichatbot
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Log\Log;

class Com_Bears_AichatbotInstallerScript extends InstallerScript
{
    /**
     * Minimum PHP version required
     * @var string
     */
    protected $minimumPhp = '8.1.0';

    /**
     * Minimum Joomla version required
     * @var string
     */
    protected $minimumJoomla = '5.0.0';

    /**
     * Method to run before installation/update/uninstallation
     */
    public function preflight($type, $parent)
    {
        // Check minimum requirements
        if (!parent::preflight($type, $parent)) {
            return false;
        }

        return true;
    }

    /**
     * Method to run after installation/update
     */
    public function postflight($type, $parent)
    {
        // Add logging category
        Log::addLogger(
            ['text_file' => 'bears_aichatbot.php'],
            Log::ALL,
            ['bears_aichatbot']
        );

        if ($type === 'install') {
            Log::add('Bears AI Chatbot component installed successfully', Log::INFO, 'bears_aichatbot');
        } elseif ($type === 'update') {
            Log::add('Bears AI Chatbot component updated successfully', Log::INFO, 'bears_aichatbot');
        }

        return true;
    }

    /**
     * Method called on uninstallation
     */
    public function uninstall($parent)
    {
        $this->cleanupRelatedData();
        return true;
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
