<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.10.02.1
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
 */
namespace Joomla\Plugin\System\BearsAichatbotinstaller\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class BearsAichatbotinstaller extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = false;

    public static function getSubscribedEvents(): array
    {
        return [
            'onInstallerAfterInstall' => 'onInstallerAfterInstall',
            'onInstallerAfterUpdate'  => 'onInstallerAfterUpdate',
        ];
    }

    public function onInstallerAfterInstall($installer, $eid): void
    {
        $this->postInstallEnableAndSeed();
    }

    public function onInstallerAfterUpdate($installer, $eid): void
    {
        $this->postInstallEnableAndSeed();
    }

    private function postInstallEnableAndSeed(): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            // Enable both content and task plugins
            $q = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('bears_aichatbot'))
                ->where($db->quoteName('folder') . ' IN (' . $db->quote('content') . ', ' . $db->quote('task') . ')');
            $db->setQuery($q)->execute();

            // Seed scheduler tasks if missing
            $this->ensureSchedulerTask($db, 'bears_aichatbot.queue', 'Bears AI Chatbot: Process queue', '0 * * * *');
            $this->ensureSchedulerTask($db, 'bears_aichatbot.reconcile', 'Bears AI Chatbot: Reconcile', '0 0 * * 0');

            // Optionally disable this system plugin after running once to reduce overhead
            try {
                $q2 = $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 0')
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('bears_aichatbotinstaller'));
                $db->setQuery($q2)->execute();
            } catch (\Throwable $ignore) {}
        } catch (\Throwable $e) {}
    }

    private function ensureSchedulerTask(DatabaseInterface $db, string $type, string $title, string $cron): void
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
}
