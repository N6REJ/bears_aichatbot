<?php
/**
 * Package installer script for Bears AI Chatbot
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class Pkg_Bears_AichatbotInstallerScript
{
    public function install($parent)
    {
        $this->postInstall();
    }

    public function update($parent)
    {
        $this->postInstall();
    }

    protected function postInstall(): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            // Enable task and content plugins
            $this->enablePlugin($db, 'bears_aichatbot', 'task');
            $this->enablePlugin($db, 'bears_aichatbot', 'content');

            // Ensure scheduler tasks exist: reconcile daily @ midnight, queue manual
            $this->ensureSchedulerTasks($db);

            // Try to create IONOS document collection once if credentials already configured
            $this->ensureDocumentCollection($db);
        } catch (\Throwable $e) {
            // Swallow errors to not break install; in practice, consider logging
        }
    }

    protected function enablePlugin(DatabaseInterface $db, string $element, string $folder): void
    {
        // Set enabled = 1 for the plugin
        $q = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('element') . ' = ' . $db->quote($element))
            ->where($db->quoteName('folder') . ' = ' . $db->quote($folder));
        $db->setQuery($q)->execute();
    }

    protected function ensureSchedulerTasks(DatabaseInterface $db): void
    {
        // Table and columns are from Joomla 5 Scheduler (#__scheduler_tasks)
        // We'll create two tasks if not exist by (type):
        // 1) aichatbot.reconcile -> daily at midnight
        // 2) aichatbot.queue -> manual (disabled schedule)

        // Upsert reconcile (daily @ midnight using cron expression 0 0 * * *)
        $this->upsertTask($db,
            'aichatbot.reconcile',
            'AI Chatbot: Reconcile collection (daily)',
            json_encode(['type' => 'cron', 'expression' => '0 0 * * *']) ,
            1
        );

        // Upsert queue (manual)
        $this->upsertTask($db,
            'aichatbot.queue',
            'AI Chatbot: Process queue (manual)',
            json_encode(['type' => 'manual']),
            1
        );
    }

    protected function upsertTask(DatabaseInterface $db, string $type, string $title, string $rulesJson, int $enabled = 1): void
    {
        // Check existing by type
        $sel = $db->getQuery(true)
            ->select($db->quoteName(['id','title','execution_rules','state']))
            ->from($db->quoteName('#__scheduler_tasks'))
            ->where($db->quoteName('type') . ' = ' . $db->quote($type))
            ->setLimit(1);
        $db->setQuery($sel);
        $row = $db->loadAssoc();
        $existingId = (int) ($row['id'] ?? 0);

        if ($existingId > 0) {
            // Respect admin customizations: only fill fields that are empty/null
            $sets = [];
            $currentTitle = (string)($row['title'] ?? '');
            $currentRules = (string)($row['execution_rules'] ?? '');
            $currentState = $row['state'] ?? null; // int or null

            if ($currentTitle === '') {
                $sets[] = $db->quoteName('title') . ' = ' . $db->quote($title);
            }
            if ($currentRules === '' || $currentRules === null) {
                $sets[] = $db->quoteName('execution_rules') . ' = ' . $db->quote($rulesJson);
            }
            // Only set state if it is NULL (not set yet). Do not flip enabled/disabled chosen by admin
            if ($currentState === null) {
                $sets[] = $db->quoteName('state') . ' = ' . (int)$enabled;
            }

            if (!empty($sets)) {
                $upd = $db->getQuery(true)
                    ->update($db->quoteName('#__scheduler_tasks'))
                    ->set(implode(', ', $sets))
                    ->where($db->quoteName('id') . ' = ' . (int)$existingId);
                $db->setQuery($upd)->execute();
            }
        } else {
            // Insert new task with our defaults
            $ins = $db->getQuery(true)
                ->insert($db->quoteName('#__scheduler_tasks'))
                ->columns($db->quoteName(['title','type','execution_rules','state','last_execution']))
                ->values(implode(',', [
                    $db->quote($title),
                    $db->quote($type),
                    $db->quote($rulesJson),
                    (int)$enabled,
                    $db->quote(null)
                ]));
            $db->setQuery($ins)->execute();
        }
    }

    protected function ensureDocumentCollection(DatabaseInterface $db): void
    {
        try {
            // Load task plugin params
            $q = $db->getQuery(true)
                ->select($db->quoteName(['extension_id','params']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('bears_aichatbot'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
                ->setLimit(1);
            $db->setQuery($q);
            $ext = $db->loadAssoc();
            if (!$ext) { return; }
            $extId = (int) $ext['extension_id'];
            $paramsRaw = (string) ($ext['params'] ?? '');
            $params = new \Joomla\Registry\Registry($paramsRaw);

            $token = trim((string) $params->get('ionos_token', ''));
            $tokenId = trim((string) $params->get('ionos_token_id', ''));
            $base = trim((string) $params->get('ionos_endpoint', 'https://api.inference.ionos.com/v1'));
            $collectionId = trim((string) $params->get('collection_id', ''));
            if ($collectionId !== '' || $token === '') {
                return; // either already set or no credentials yet
            }
            if ($base === '') { $base = 'https://api.inference.ionos.com/v1'; }
            $base = rtrim($base, '/');

            // Prepare collection create payload
            $site = \Joomla\CMS\Factory::getApplication()->get('sitename') ?: 'Joomla Site';
            $root = \Joomla\CMS\Uri\Uri::root();
            $host = parse_url($root, PHP_URL_HOST) ?: 'localhost';
            $name = 'bears-aichatbot-' . preg_replace('/[^a-z0-9-]/i', '-', $host) . '-' . date('YmdHis');
            $payload = [
                'name' => $name,
                'description' => 'Auto-created by Bears AI Chatbot installer for ' . $site . ' (' . $root . ')'
            ];
            $headers = [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];
            if ($tokenId !== '') { $headers['X-IONOS-Token-Id'] = $tokenId; }

            $http = \Joomla\CMS\Http\HttpFactory::getHttp();
            $url = $base . '/document-collections';
            $resp = $http->post($url, json_encode($payload), $headers, 30);
            if ($resp->code >= 200 && $resp->code < 300) {
                $data = json_decode((string)$resp->body, true);
                $newId = (string) ($data['id'] ?? $data['collection_id'] ?? '');
                if ($newId !== '') {
                    // Save back to plugin params
                    $params->set('collection_id', $newId);
                    $upd = $db->getQuery(true)
                        ->update($db->quoteName('#__extensions'))
                        ->set($db->quoteName('params') . ' = ' . $db->quote((string)$params))
                        ->where($db->quoteName('extension_id') . ' = ' . (int)$extId);
                    $db->setQuery($upd)->execute();
                }
            }
        } catch (\Throwable $e) {
            // Silent on install
        }
    }
}
