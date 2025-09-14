<?php
/**
 * Package installer script for pkg_bears_aichatbot
 * Uses Joomla's standard package uninstallation methods
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Table\Extension as ExtensionTable;
use Joomla\Database\DatabaseInterface;

/**
 * Package installer script class
 */
class pkg_pkg_bears_aichatbotInstallerScript
{
    /**
     * Called after installation
     */
    public function install($parent)
    {
        $this->enablePlugins();
        $this->normalizePackageRow();
        return true;
    }

    /**
     * Called after update
     */
    public function update($parent)
    {
        $this->enablePlugins();
        $this->normalizePackageRow();
        return true;
    }

    /**
     * Called after any type of action
     */
    public function postflight($type, $parent)
    {
        if ($type === 'install' || $type === 'update') {
            $this->enablePlugins();
            $this->normalizePackageRow();
        }
    }

    /**
     * Called before uninstallation
     * This is where we clean up our data before Joomla uninstalls the extensions
     */
    public function preflight($type, $parent)
    {
        if ($type === 'uninstall') {
            $this->cleanupBeforeUninstall();
        }
        return true;
    }

    /**
     * Called during uninstallation
     * We need to manually uninstall child extensions since Joomla isn't doing it automatically
     */
    public function uninstall($parent)
    {
        // Manually uninstall child extensions
        $this->uninstallChildExtensions();
        
        // Additional cleanup after extensions are removed
        $this->finalCleanup();
        return true;
    }

    /**
     * Enable the plugins after installation
     */
    private function enablePlugins()
    {
        try {
            $db = Factory::getDbo();
            
            // Enable task plugin
            $this->enablePlugin($db, 'task', 'bears_aichatbot');
            
            // Enable content plugin
            $this->enablePlugin($db, 'content', 'bears_aichatbot');
            
        } catch (\Exception $e) {
            // Log error but don't fail installation
            Factory::getApplication()->enqueueMessage('Could not enable plugins: ' . $e->getMessage(), 'warning');
        }
    }

    /**
     * Enable a specific plugin
     */
    private function enablePlugin($db, $folder, $element)
    {
        try {
            $table = new ExtensionTable($db);
            if ($table->load(['type' => 'plugin', 'element' => $element, 'folder' => $folder])) {
                if ((int) $table->enabled !== 1) {
                    $table->enabled = 1;
                    $table->store();
                }
            }
        } catch (\Exception $e) {
            // Ignore individual plugin errors
        }
    }

    /**
     * Normalize the package row in #__extensions to ensure element and duplicates are correct
     */
    private function normalizePackageRow()
    {
        try {
            $db = Factory::getDbo();

            // Fetch any package rows with either correct or incorrect element name
            $q = $db->getQuery(true)
                ->select($db->quoteName(['extension_id','element','manifest_cache']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('package'))
                ->where($db->quoteName('element') . ' IN (' . $db->quote('pkg_bears_aichatbot') . ',' . $db->quote('pkg_pkg_bears_aichatbot') . ')');
            $db->setQuery($q);
            $rows = (array) $db->loadAssocList();

            if (!$rows) {
                return; // nothing to normalize
            }

            $keepId = 0;
            $toDelete = [];

            // Prefer keeping a row that already has the correct element
            foreach ($rows as $r) {
                if ((string)$r['element'] === 'pkg_bears_aichatbot') {
                    if ($keepId === 0) {
                        $keepId = (int)$r['extension_id'];
                    } else {
                        $toDelete[] = (int)$r['extension_id'];
                    }
                }
            }

            // If no correct row, convert the first wrong one and mark the rest for delete
            if ($keepId === 0) {
                $first = $rows[0];
                $keepId = (int)$first['extension_id'];
                $upd = $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('element') . ' = ' . $db->quote('pkg_bears_aichatbot'))
                    ->where($db->quoteName('extension_id') . ' = ' . $keepId);
                $db->setQuery($upd)->execute();
                // Remaining wrong rows (if any) get deleted below
                for ($i = 1; $i < count($rows); $i++) {
                    $toDelete[] = (int)$rows[$i]['extension_id'];
                }
            } else {
                // Mark all non-keep rows for deletion
                foreach ($rows as $r) {
                    if ((int)$r['extension_id'] !== $keepId) {
                        $toDelete[] = (int)$r['extension_id'];
                    }
                }
            }

            // Delete duplicate/incorrect rows if any
            if (!empty($toDelete)) {
                $del = $db->getQuery(true)
                    ->delete($db->quoteName('#__extensions'))
                    ->where($db->quoteName('extension_id') . ' IN (' . implode(',', array_map('intval', $toDelete)) . ')');
                $db->setQuery($del)->execute();
            }

            // Ensure minimal manifest_cache exists for the kept row
            $minimal = json_encode([
                'name'    => 'pkg_bears_aichatbot',
                'type'    => 'package',
                'version' => '2025.09.14.7'
            ], JSON_UNESCAPED_SLASHES);
            $upd2 = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('manifest_cache') . ' = ' . $db->quote($minimal))
                ->where($db->quoteName('extension_id') . ' = ' . (int)$keepId);
            $db->setQuery($upd2)->execute();
        } catch (\Throwable $e) {
            // ignore normalization errors to avoid breaking install/update
        }
    }

    /**
     * Clean up data before uninstallation
     * This runs before Joomla removes the extensions
     */
    private function cleanupBeforeUninstall()
    {
        try {
            $db = Factory::getDbo();
            
            // Remove scheduler tasks
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('type') . ' LIKE ' . $db->quote('bears_aichatbot.%'));
            $db->setQuery($query);
            $db->execute();
            
            // Remove Bears AI Chatbot database tables (in correct order due to foreign keys)
            $tables = [
                '#__aichatbot_keywords',  // Has foreign key, drop first
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
            Factory::getApplication()->enqueueMessage('Cleanup warning: ' . $e->getMessage(), 'warning');
        }
    }

    /**
     * Manually uninstall child extensions
     * This is needed because Joomla isn't automatically uninstalling them
     */
    private function uninstallChildExtensions()
    {
        try {
            $db = Factory::getDbo();
            $installer = Installer::getInstance();
            
            // Define child extensions to uninstall
            $childExtensions = [
                ['type' => 'plugin', 'element' => 'bears_aichatbotinstaller', 'folder' => 'system'],
                ['type' => 'plugin', 'element' => 'bears_aichatbot', 'folder' => 'content'],
                ['type' => 'plugin', 'element' => 'bears_aichatbot', 'folder' => 'task'],
                ['type' => 'module', 'element' => 'mod_bears_aichatbot'],
                ['type' => 'component', 'element' => 'com_bears_aichatbot']
            ];
            
            foreach ($childExtensions as $ext) {
                try {
                    // Find the extension ID
                    $query = $db->getQuery(true)
                        ->select($db->quoteName('extension_id'))
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('type') . ' = ' . $db->quote($ext['type']))
                        ->where($db->quoteName('element') . ' = ' . $db->quote($ext['element']));
                    
                    if (isset($ext['folder'])) {
                        $query->where($db->quoteName('folder') . ' = ' . $db->quote($ext['folder']));
                    }
                    
                    $db->setQuery($query);
                    $extensionId = $db->loadResult();
                    
                    if ($extensionId) {
                        // Uninstall the extension
                        $result = $installer->uninstall($ext['type'], $extensionId);
                        if ($result) {
                            Factory::getApplication()->enqueueMessage(
                                'Successfully uninstalled: ' . $ext['element'] . 
                                (isset($ext['folder']) ? ' (' . $ext['folder'] . ')' : ''), 
                                'message'
                            );
                        }
                    }
                    
                } catch (\Exception $e) {
                    // Log individual extension uninstall errors but continue
                    Factory::getApplication()->enqueueMessage(
                        'Could not uninstall ' . $ext['element'] . ': ' . $e->getMessage(), 
                        'warning'
                    );
                }
            }
            
        } catch (\Exception $e) {
            // Log error but don't fail package uninstallation
            Factory::getApplication()->enqueueMessage('Child extension uninstall error: ' . $e->getMessage(), 'warning');
        }
    }

    /**
     * Final cleanup after extensions are removed
     */
    private function finalCleanup()
    {
        try {
            $db = Factory::getDbo();
            
            // Clean up orphaned module menu assignments
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__modules_menu'))
                ->where($db->quoteName('moduleid') . ' NOT IN (SELECT ' . $db->quoteName('id') . ' FROM ' . $db->quoteName('#__modules') . ')');
            $db->setQuery($query);
            $db->execute();
            
            // Remove any remaining menu items
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__menu'))
                ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%com_bears_aichatbot%'));
            $db->setQuery($query);
            $db->execute();
            
        } catch (\Exception $e) {
            // Ignore final cleanup errors
        }
    }
}

// Compatibility shims for different Joomla naming conventions
if (!class_exists('pkg_bears_aichatbotInstallerScript')) {
    class pkg_bears_aichatbotInstallerScript extends pkg_pkg_bears_aichatbotInstallerScript {}
}
if (!class_exists('Pkg_bears_aichatbotInstallerScript')) {
    class Pkg_bears_aichatbotInstallerScript extends pkg_pkg_bears_aichatbotInstallerScript {}
}
if (!class_exists('Pkg_Bears_AichatbotInstallerScript')) {
    class Pkg_Bears_AichatbotInstallerScript extends pkg_pkg_bears_aichatbotInstallerScript {}
}
