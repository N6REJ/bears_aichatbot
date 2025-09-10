<?php
/**
 * Package installer script for pkg_bears_aichatbot
 * - Enables the content and task plugins by default
 * - Creates Scheduler tasks to run daily at midnight
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class Pkg_Bears_AichatbotInstallerScript
{
    public function postflight($type, $parent)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // 1) Enable the plugins (content + task)
        try {
            $q = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('bears_aichatbot'))
                ->where($db->quoteName('folder') . ' IN (' . $db->quote('content') . ', ' . $db->quote('task') . ')');
            $db->setQuery($q)->execute();
        } catch (\Throwable $e) {
            // ignore
        }

        // 2) Ensure Scheduler tasks exist, set to daily at midnight (cron: 0 0 * * *)
        $this->ensureSchedulerTask($db, 'bears_aichatbot.queue', 'Bears AI Chatbot: Process queue', '0 0 * * *');
        $this->ensureSchedulerTask($db, 'bears_aichatbot.reconcile', 'Bears AI Chatbot: Reconcile', '0 0 * * *');
    }

    protected function ensureSchedulerTask(DatabaseInterface $db, string $type, string $title, string $cron): void
    {
        try {
            // Check if a task of this type already exists
            $q = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('type') . ' = ' . $db->quote($type));
            $db->setQuery($q);
            $exists = (int) $db->loadResult() > 0;
            if ($exists) {
                return;
            }

            // Preferred (Joomla 4.3+/5): execution_rules JSON column with rule "cron"
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
                    1, // enabled
                    $db->quote($executionRules),
                    $db->quote('{}'),
                    3, // normal priority
                ]));
            try {
                $db->setQuery($ins)->execute();
                return;
            } catch (\Throwable $e) {
                // fall through to alternate schema
            }

            // Alternate legacy schema: if cron_expression column exists, use it
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
            } catch (\Throwable $ignore) {
                // ignore
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
