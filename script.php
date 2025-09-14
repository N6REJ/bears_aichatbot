<?php
/**
 * Package installer script for pkg_bears_aichatbot (safe minimal version)
 * - Enables plugins after install/update
 * - Performs light cleanup before/after uninstall
 * - NO database normalization
 * - NO manual child uninstallation to avoid recursion/stack overflow
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Extension as ExtensionTable;

class pkg_pkg_bears_aichatbotInstallerScript
{
    /**
     * Called after installation
     */
    public function install($parent)
    {
        $this->enablePlugins();
        $this->seedSchedulerTasks();
        return true;
    }

    /**
     * Called after update
     */
    public function update($parent)
    {
        $this->enablePlugins();
        $this->seedSchedulerTasks();
        return true;
    }

    /**
     * Called after any type of action
     */
    public function postflight($type, $parent)
    {
        if ($type === 'install' || $type === 'update') {
            $this->enablePlugins();
            $this->seedSchedulerTasks();
        }
    }

    /**
     * Called before uninstallation
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
     */
    public function uninstall($parent)
    {
        // Do not call Joomla\CMS\Installer\Installer here to avoid recursion
        $this->finalCleanup();
        return true;
    }

    /**
     * Enable task/content plugins after install/update
     */
    private function enablePlugins(): void
    {
        try {
            $db = Factory::getDbo();
            $this->enablePlugin($db, 'task', 'bears_aichatbot');
            $this->enablePlugin($db, 'content', 'bears_aichatbot');
            $this->enablePlugin($db, 'system', 'bears_aichatbotinstaller');
        } catch (\Throwable $e) {
            // best-effort only
        }
    }

    private function enablePlugin($db, string $folder, string $element): void
    {
        try {
            $table = new ExtensionTable($db);
            if ($table->load(['type' => 'plugin', 'element' => $element, 'folder' => $folder])) {
                if ((int)$table->enabled !== 1) {
                    $table->enabled = 1;
                    $table->store();
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function seedSchedulerTasks(): void
    {
        try {
            $db = Factory::getDbo();
            $this->ensureSchedulerTask($db, 'bears_aichatbot.queue', 'Bears AI Chatbot: Process queue', '0 * * * *');
            $this->ensureSchedulerTask($db, 'bears_aichatbot.reconcile', 'Bears AI Chatbot: Reconcile', '0 0 * * 0');
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function ensureSchedulerTask($db, string $type, string $title, string $cron): void
    {
        try {
            $q = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('type') . ' = ' . $db->quote($type));
            $db->setQuery($q);
            if ((int)$db->loadResult() > 0) {
                return;
            }

            // Preferred schema: execution_rules JSON
            $executionRules = json_encode(['rule' => 'cron', 'expression' => $cron], JSON_UNESCAPED_SLASHES);
            $ins = $db->getQuery(true)
                ->insert($db->quoteName('#__scheduler_tasks'))
                ->columns([
                    $db->quoteName('type'),
                    $db->quoteName('title'),
                    $db->quoteName('state'),
                    $db->quoteName('execution_rules'),
                    $db->quoteName('params'),
                    $db->quoteName('priority'),
                ])
                ->values(implode(',', [
                    $db->quote($type),
                    $db->quote($title),
                    1,
                    $db->quote($executionRules),
                    $db->quote('{}'),
                    3,
                ]));
            try {
                $db->setQuery($ins)->execute();
                return;
            } catch (\Throwable $e) {}

            // Legacy schema fallback
            try {
                $columns = $db->getTableColumns('#__scheduler_tasks', false);
                if (isset($columns['cron_expression'])) {
                    $ins2 = $db->getQuery(true)
                        ->insert($db->quoteName('#__scheduler_tasks'))
                        ->columns([
                            $db->quoteName('type'),
                            $db->quoteName('title'),
                            $db->quoteName('state'),
                            $db->quoteName('cron_expression'),
                            $db->quoteName('params'),
                            $db->quoteName('priority'),
                        ])
                        ->values(implode(',', [
                            $db->quote($type),
                            $db->quote($title),
                            1,
                            $db->quote($cron),
                            $db->quote('{}'),
                            3,
                        ]));
                    $db->setQuery($ins2)->execute();
                }
            } catch (\Throwable $ignore) {}
        } catch (\Throwable $e) {}
    }

    /**
     * Light cleanup before uninstall
     */
    private function cleanupBeforeUninstall(): void
    {
        try {
            $db = Factory::getDbo();
            // Remove scheduler tasks
            $db->setQuery("DELETE FROM `#__scheduler_tasks` WHERE `type` LIKE 'bears_aichatbot.%'")->execute();
            // Drop known tables (ignore errors)
            $tables = [
                '#__aichatbot_keywords',
                '#__aichatbot_usage',
                '#__aichatbot_docs',
                '#__aichatbot_jobs',
                '#__aichatbot_state',
                '#__aichatbot_collection_stats',
            ];
            foreach ($tables as $t) {
                try {
                    $db->setQuery('DROP TABLE IF EXISTS ' . $db->quoteName($t))->execute();
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Final cleanup after uninstall
     */
    private function finalCleanup(): void
    {
        try {
            $db = Factory::getDbo();
            // Clean orphaned module menu links
            $db->setQuery(
                'DELETE FROM ' . $db->quoteName('#__modules_menu') .
                ' WHERE ' . $db->quoteName('moduleid') . ' NOT IN (SELECT ' . $db->quoteName('id') . ' FROM ' . $db->quoteName('#__modules') . ')'
            )->execute();
            // Remove stray admin menu items
            $db->setQuery(
                'DELETE FROM ' . $db->quoteName('#__menu') .
                ' WHERE ' . $db->quoteName('link') . " LIKE '%com_bears_aichatbot%'"
            )->execute();
        } catch (\Throwable $e) {
            // ignore
        }
    }
}

// Compatibility shims for naming variations
if (!class_exists('pkg_bears_aichatbotInstallerScript')) {
    class pkg_bears_aichatbotInstallerScript extends pkg_pkg_bears_aichatbotInstallerScript {}
}
if (!class_exists('Pkg_bears_aichatbotInstallerScript')) {
    class Pkg_bears_aichatbotInstallerScript extends pkg_pkg_bears_aichatbotInstallerScript {}
}
if (!class_exists('Pkg_Bears_AichatbotInstallerScript')) {
    class Pkg_Bears_AichatbotInstallerScript extends pkg_pkg_bears_aichatbotInstallerScript {}
}
