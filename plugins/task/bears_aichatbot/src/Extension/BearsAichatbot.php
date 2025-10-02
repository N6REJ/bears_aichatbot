<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.10.02.3
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
 */
namespace Joomla\Plugin\Task\BearsAichatbot\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Factory;

/**
 * Task plugin for Bears AI Chatbot
 */
final class BearsAichatbot extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var string[]
     */
    protected const TASKS_MAP = [
        'bears_aichatbot.queue'     => [
            'langConstPrefix' => 'PLG_TASK_BEARS_AICHATBOT_QUEUE',
            'method'          => 'processQueueTask',
        ],
        'bears_aichatbot.reconcile' => [
            'langConstPrefix' => 'PLG_TASK_BEARS_AICHATBOT_RECONCILE',
            'method'          => 'reconcileTask',
        ],
    ];

    /**
     * @var boolean
     */
    protected $autoloadLanguage = true;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
        ];
    }

    /**
     * Process queued upsert/delete jobs.
     *
     * @param   ExecuteTaskEvent  $event  The event
     *
     * @return  integer  The exit code
     */
    private function processQueueTask(ExecuteTaskEvent $event): int
    {
        $this->loadCredentialsFromModule();
        $this->ensureCollectionExists();
        $this->loadCollectionFromState();

        $startedAt = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $batch = (int) $this->params->get('batch_size', 50);
        $maxAttempts = (int) $this->params->get('max_attempts', 5);
        $processed = 0; 
        $failed = 0;

        // Fetch jobs
        $q = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__aichatbot_jobs'))
            ->where($db->quoteName('status') . ' IN (' . $db->quote('queued') . ',' . $db->quote('error') . ')')
            ->where($db->quoteName('attempts') . ' < ' . (int)$maxAttempts)
            ->order($db->quoteName('id') . ' ASC');
        $db->setQuery($q, 0, $batch);
        $jobs = (array) $db->loadAssocList();

        foreach ($jobs as $job) {
            $id = (int) $job['id'];
            $contentId = (int) $job['content_id'];
            $action = (string) $job['action'];
            $ok = false; 
            $err = '';
            
            try {
                if ($action === 'upsert') {
                    $ok = $this->handleUpsert($db, $contentId);
                    if (!$ok) { 
                        $err = 'Upsert returned false'; 
                    }
                } elseif ($action === 'delete') {
                    $ok = $this->handleDelete($db, $contentId);
                    if (!$ok) { 
                        $err = 'Delete returned false'; 
                    }
                } else {
                    $ok = true; // ignore unknown
                }
            } catch (\Throwable $e) {
                $ok = false; 
                $err = $e->getMessage();
            }

            $upd = $db->getQuery(true)
                ->update($db->quoteName('#__aichatbot_jobs'))
                ->set($db->quoteName('attempts') . ' = ' . ((int)$job['attempts'] + 1))
                ->set($db->quoteName('status')   . ' = ' . $db->quote($ok ? 'done' : 'error'))
                ->set($db->quoteName('last_error') . ' = ' . ($ok ? 'NULL' : $db->quote(mb_substr($err, 0, 500))))
                ->where($db->quoteName('id') . ' = ' . (int)$id);
            $db->setQuery($upd)->execute();

            $processed += (int) $ok; 
            $failed += (int) (!$ok);
        }

        // Mark last successful queue run
        try {
            $upd = $db->getQuery(true)
                ->update($db->quoteName('#__aichatbot_state'))
                ->set($db->quoteName('last_run_queue') . ' = ' . $db->quote($startedAt))
                ->where($db->quoteName('id') . ' = 1');
            $db->setQuery($upd)->execute();
        } catch (\Throwable $ignore) {}

        $this->snapshotCollectionCount();

        $this->logTask(sprintf('Queue processed: %d ok, %d failed', $processed, $failed));
        
        return Status::OK;
    }

    /**
     * Reconcile mapping with current Joomla articles.
     *
     * @param   ExecuteTaskEvent  $event  The event
     *
     * @return  integer  The exit code
     */
    private function reconcileTask(ExecuteTaskEvent $event): int
    {
        $this->loadCredentialsFromModule();
        $this->ensureCollectionExists();
        $this->loadCollectionFromState();

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $processed = 0; 
        $deleted = 0;
        $startedAt = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $catIds = $this->params->get('selected_categories', []);
        if (is_string($catIds)) { 
            $catIds = array_filter(array_map('intval', explode(',', $catIds))); 
        } elseif (!is_array($catIds)) { 
            $catIds = []; 
        }

        // Expand category tree
        $allCatIds = $this->expandCategoryTree($db, $catIds);

        // Get last run time
        $lastRun = $this->getLastRunTime($db, 'reconcile');

        // Fetch currently published articles
        $articles = $this->fetchArticles($db, $allCatIds, $lastRun);

        // Map existing docs
        $docs = $this->getExistingDocs($db);

        // Process articles
        foreach ($articles as $id => $a) {
            $hash = $this->computeHash($a['title'], $a['introtext'], $a['fulltext'], (int)$a['state']);
            $row  = $docs[(int)$id] ?? null;
            if (!$row || $row['content_hash'] !== $hash || (int)$row['state'] !== 1) {
                $ok = $this->handleUpsert($db, (int)$id, $a, $hash);
                if ($ok) { 
                    $processed++; 
                }
            }
        }

        // Handle deletions
        $currentInScopeIds = $this->getCurrentInScopeIds($db, $allCatIds);
        foreach ($docs as $contentId => $row) {
            $shouldExist = $currentInScopeIds !== null 
                ? in_array((int)$contentId, $currentInScopeIds, true) 
                : isset($articles[$contentId]);
            if (!$shouldExist) {
                $ok = $this->handleDelete($db, (int)$contentId);
                if ($ok) { 
                    $deleted++; 
                }
            }
        }

        // Mark last successful run
        try {
            $upd = $db->getQuery(true)
                ->update($db->quoteName('#__aichatbot_state'))
                ->set($db->quoteName('last_run_reconcile') . ' = ' . $db->quote($startedAt))
                ->where($db->quoteName('id') . ' = 1');
            $db->setQuery($upd)->execute();
        } catch (\Throwable $ignore) {}

        $this->snapshotCollectionCount();

        $this->logTask(sprintf('Reconcile: %d upserts, %d deletes', $processed, $deleted));
        
        return Status::OK;
    }

    private function expandCategoryTree($db, $catIds)
    {
        $allCatIds = [];
        if (!empty($catIds)) {
            try {
                $cq = $db->getQuery(true)
                    ->select($db->quoteName(['id','lft','rgt']))
                    ->from($db->quoteName('#__categories'))
                    ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                    ->where($db->quoteName('published') . ' = 1')
                    ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $catIds)) . ')');
                $db->setQuery($cq);
                $ranges = (array) $db->loadAssocList();
                if ($ranges) {
                    $ors = [];
                    foreach ($ranges as $r) {
                        $ors[] = '(' . $db->quoteName('lft') . ' >= ' . (int)$r['lft'] . ' AND ' . $db->quoteName('rgt') . ' <= ' . (int)$r['rgt'] . ')';
                    }
                    $dcq = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__categories'))
                        ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                        ->where($db->quoteName('published') . ' = 1')
                        ->where(implode(' OR ', $ors));
                    $db->setQuery($dcq);
                    $allCatIds = array_map('intval', (array) $db->loadColumn());
                }
            } catch (\Throwable $e) { 
                $allCatIds = $catIds; 
            }
        }
        return empty($allCatIds) ? $catIds : $allCatIds;
    }

    private function getLastRunTime($db, $type)
    {
        $lastRun = '';
        try {
            $field = $type === 'reconcile' ? 'last_run_reconcile' : 'last_run_queue';
            $qState = $db->getQuery(true)
                ->select($db->quoteName($field))
                ->from($db->quoteName('#__aichatbot_state'))
                ->where($db->quoteName('id') . ' = 1')
                ->setLimit(1);
            $db->setQuery($qState);
            $lastRun = (string)($db->loadResult() ?? '');
        } catch (\Throwable $ignore) {}
        return $lastRun;
    }

    private function fetchArticles($db, $allCatIds, $lastRun)
    {
        $q = $db->getQuery(true)
            ->select($db->quoteName(['id','title','introtext','fulltext','state','modified','created']))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1');
        if (!empty($allCatIds)) {
            $q->where($db->quoteName('catid') . ' IN (' . implode(',', array_map('intval', $allCatIds)) . ')');
        }
        if ($lastRun !== '') {
            $q->where('(' . $db->quoteName('modified') . ' >= ' . $db->quote($lastRun) . ' OR ' . $db->quoteName('created') . ' >= ' . $db->quote($lastRun) . ')');
        }
        $db->setQuery($q);
        return (array) $db->loadAssocList('id');
    }

    private function getExistingDocs($db)
    {
        $dq = $db->getQuery(true)
            ->select($db->quoteName(['content_id','content_hash','remote_id','state']))
            ->from($db->quoteName('#__aichatbot_docs'));
        $db->setQuery($dq);
        $docs = [];
        foreach ((array)$db->loadAssocList() as $row) { 
            $docs[(int)$row['content_id']] = $row; 
        }
        return $docs;
    }

    private function getCurrentInScopeIds($db, $allCatIds)
    {
        $currentInScopeIds = null;
        try {
            $qIds = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('state') . ' = 1');
            if (!empty($allCatIds)) {
                $qIds->where($db->quoteName('catid') . ' IN (' . implode(',', array_map('intval', $allCatIds)) . ')');
            }
            $db->setQuery($qIds);
            $currentInScopeIds = array_map('intval', (array)$db->loadColumn());
        } catch (\Throwable $ignore) {}
        return $currentInScopeIds;
    }

    protected function handleUpsert(DatabaseInterface $db, int $contentId, ?array $article = null, ?string $hash = null): bool
    {
        if ($article === null) {
            $q = $db->getQuery(true)
                ->select($db->quoteName(['id','title','introtext','fulltext','state']))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . (int)$contentId)
                ->setLimit(1);
            $db->setQuery($q);
            $row = $db->loadAssoc();
            if (!$row) { 
                return false; 
            }
            $article = $row;
        }
        
        if ((int)$article['state'] !== 1) {
            return $this->handleDelete($db, $contentId);
        }

        $title = (string)$article['title'];
        $intro = (string)$article['introtext'];
        $full  = (string)$article['fulltext'];
        if ($hash === null) { 
            $hash = $this->computeHash($title, $intro, $full, 1); 
        }

        // Get or create remote id
        $dq = $db->getQuery(true)
            ->select($db->quoteName(['remote_id']))
            ->from($db->quoteName('#__aichatbot_docs'))
            ->where($db->quoteName('content_id') . ' = ' . (int)$contentId)
            ->setLimit(1);
        $db->setQuery($dq);
        $remoteId = (string) ($db->loadResult() ?? '');
        if ($remoteId === '') { 
            $remoteId = 'article-' . $contentId; 
        }

        $text = $this->normalize($title) . "\n\n" . $this->normalize($intro) . "\n\n" . $this->normalize($full);

        // Call external API
        $ok = $this->apiUpsert($remoteId, $text, [ 'content_id' => $contentId, 'title' => $title ]);
        if (!$ok) { 
            return false; 
        }

        // Update mapping
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        $db->transactionStart();
        try {
            $chk = $db->getQuery(true)
                ->select($db->quoteName('content_id'))
                ->from($db->quoteName('#__aichatbot_docs'))
                ->where($db->quoteName('content_id') . ' = ' . (int)$contentId)
                ->setLimit(1);
            $db->setQuery($chk);
            $exists = (int) ($db->loadResult() ?? 0) > 0;

            if (!$exists) {
                $ins = $db->getQuery(true)
                    ->insert($db->quoteName('#__aichatbot_docs'))
                    ->columns($db->quoteName(['content_id','remote_id','content_hash','last_synced','state']))
                    ->values(implode(',', [ (int)$contentId, $db->quote($remoteId), $db->quote($hash), $db->quote($now), 1 ]));
                $db->setQuery($ins)->execute();
            } else {
                $upd = $db->getQuery(true)
                    ->update($db->quoteName('#__aichatbot_docs'))
                    ->set($db->quoteName('remote_id') . ' = ' . $db->quote($remoteId))
                    ->set($db->quoteName('content_hash') . ' = ' . $db->quote($hash))
                    ->set($db->quoteName('last_synced') . ' = ' . $db->quote($now))
                    ->set($db->quoteName('state') . ' = 1')
                    ->where($db->quoteName('content_id') . ' = ' . (int)$contentId);
                $db->setQuery($upd)->execute();
            }
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            throw $e;
        }
        return true;
    }

    protected function handleDelete(DatabaseInterface $db, int $contentId): bool
    {
        $dq = $db->getQuery(true)
            ->select($db->quoteName('remote_id'))
            ->from($db->quoteName('#__aichatbot_docs'))
            ->where($db->quoteName('content_id') . ' = ' . (int)$contentId)
            ->setLimit(1);
        $db->setQuery($dq);
        $remoteId = (string) ($db->loadResult() ?? 'article-' . $contentId);

        $ok = $this->apiDelete($remoteId);
        if (!$ok) { 
            return false; 
        }

        $del = $db->getQuery(true)
            ->delete($db->quoteName('#__aichatbot_docs'))
            ->where($db->quoteName('content_id') . ' = ' . (int)$contentId);
        $db->setQuery($del)->execute();
        return true;
    }

    protected function computeHash(string $title, string $intro, string $full, int $state): string
    {
        $norm = $this->normalize($title) . "\n" . $this->normalize($intro) . "\n" . $this->normalize($full) . "\n" . (string)$state;
        return hash('sha256', $norm);
    }

    protected function normalize(string $html): string
    {
        $txt = strip_tags($html);
        $txt = preg_replace('/\s+/', ' ', $txt);
        return trim((string)$txt);
    }

    protected function loadCredentialsFromModule(): void
    {
        try {
            $token = trim((string)$this->params->get('ionos_token', ''));
            $tokenId = trim((string)$this->params->get('ionos_token_id', ''));
            $endpoint = (string)$this->params->get('ionos_endpoint', '');
            if ($token !== '' && $endpoint !== '') {
                return;
            }
            
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $q = $db->getQuery(true)
                ->select($db->quoteName(['id','params','published']))
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_bears_aichatbot'))
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('id') . ' ASC')
                ->setLimit(10);
            $db->setQuery($q);
            $mods = (array)$db->loadAssocList();
            
            foreach ($mods as $m) {
                $reg = new \Joomla\Registry\Registry((string)($m['params'] ?? ''));
                $mtoken = trim((string)$reg->get('ionos_token', ''));
                $mtokenId = trim((string)$reg->get('ionos_token_id', ''));
                if ($mtoken !== '') {
                    $apiBase = 'https://api.ionos.com/cloudapi/v6';
                    $this->params->set('ionos_token', $mtoken);
                    $this->params->set('ionos_token_id', $mtokenId);
                    $this->params->set('ionos_endpoint', $apiBase);
                    return;
                }
            }
        } catch (\Throwable $e) {}
    }

    protected function loadCollectionFromState(): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $q = $db->getQuery(true)
                ->select($db->quoteName('collection_id'))
                ->from($db->quoteName('#__aichatbot_state'))
                ->where($db->quoteName('id') . ' = 1')
                ->setLimit(1);
            $db->setQuery($q);
            $cid = (string)($db->loadResult() ?? '');
            if ($cid !== '') {
                $this->params->set('collection_id', $cid);
            }
        } catch (\Throwable $e) {}
    }

    protected function ensureCollectionExists(): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $qState = $db->getQuery(true)
                ->select($db->quoteName('collection_id'))
                ->from($db->quoteName('#__aichatbot_state'))
                ->where($db->quoteName('id') . ' = 1')
                ->setLimit(1);
            $db->setQuery($qState);
            $existing = (string)($db->loadResult() ?? '');

            $token = trim((string)$this->params->get('ionos_token', ''));
            $tokenId = trim((string)$this->params->get('ionos_token_id', ''));
            $base = 'https://api.ionos.com/cloudapi/v6';
            if ($existing !== '' || $token === '') {
                return;
            }

            $site = Factory::getApplication()->get('sitename') ?: 'Joomla Site';
            $root = \Joomla\CMS\Uri\Uri::root();
            $host = parse_url($root, PHP_URL_HOST) ?: 'localhost';
            $name = 'bears-aichatbot-' . preg_replace('/[^a-z0-9-]/i', '-', $host) . '-' . date('YmdHis');
            $payload = [ 'name' => $name, 'description' => 'Auto-created by Bears AI Chatbot for ' . $site . ' (' . $root . ')' ];
            $headers = [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json', 'Content-Type' => 'application/json' ];
            if ($tokenId !== '') { 
                $headers['X-IONOS-Token-Id'] = $tokenId; 
            }
            
            $http = \Joomla\CMS\Http\HttpFactory::getHttp();
            $resp = $http->post($base . '/ai/modelhub/document-collections', json_encode($payload), $headers, 30);
            if ($resp->code >= 200 && $resp->code < 300) {
                $data = json_decode((string)$resp->body, true);
                $newId = (string)($data['id'] ?? $data['collection_id'] ?? '');
                if ($newId !== '') {
                    $upd = $db->getQuery(true)
                        ->update($db->quoteName('#__aichatbot_state'))
                        ->set($db->quoteName('collection_id') . ' = ' . $db->quote($newId))
                        ->where($db->quoteName('id') . ' = 1');
                    $db->setQuery($upd)->execute();
                    
                    try {
                        \Joomla\CMS\Factory::getApplication()->enqueueMessage('AI Chatbot: Created IONOS document collection (ID: ' . $newId . ').', 'message');
                    } catch (\Throwable $ignore) {}
                }
            }
        } catch (\Throwable $e) {}
    }

    protected function apiUpsert(string $remoteId, string $text, array $metadata): bool
    {
        $tokenId = trim((string)$this->params->get('ionos_token_id', ''));
        $token   = trim((string)$this->params->get('ionos_token', ''));
        $base = 'https://api.ionos.com/cloudapi/v6';
        $collectionId = trim((string)$this->params->get('collection_id', ''));
        if ($token === '' || $collectionId === '') {
            throw new \RuntimeException('Missing IONOS credentials or collection_id');
        }
        
        $url = $base . '/ai/modelhub/document-collections/' . rawurlencode($collectionId) . '/documents';
        $payload = [
            'id'       => (string)$remoteId,
            'text'     => (string)$text,
            'metadata' => (object)$metadata,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];
        if ($tokenId !== '') {
            $headers['X-IONOS-Token-Id'] = $tokenId;
        }

        $http = \Joomla\CMS\Http\HttpFactory::getHttp();
        $resp = $http->post($url, json_encode($payload), $headers, 30);
        if ($resp->code >= 200 && $resp->code < 300) {
            return true;
        }
        
        if ($resp->code === 409) {
            $urlPut = $base . '/ai/modelhub/document-collections/' . rawurlencode($collectionId) . '/documents/' . rawurlencode($remoteId);
            $resp2 = $http->put($urlPut, json_encode($payload), $headers, 30);
            if ($resp2->code >= 200 && $resp2->code < 300) {
                return true;
            }
            throw new \RuntimeException('IONOS upsert conflict and put failed: HTTP ' . $resp2->code . ' ' . mb_substr((string)$resp2->body, 0, 500));
        }
        throw new \RuntimeException('IONOS upsert failed: HTTP ' . $resp->code . ' ' . mb_substr((string)$resp->body, 0, 500));
    }

    protected function apiDelete(string $remoteId): bool
    {
        $tokenId = trim((string)$this->params->get('ionos_token_id', ''));
        $token   = trim((string)$this->params->get('ionos_token', ''));
        $base = 'https://api.ionos.com/cloudapi/v6';
        $collectionId = trim((string)$this->params->get('collection_id', ''));
        if ($token === '' || $collectionId === '') {
            throw new \RuntimeException('Missing IONOS credentials or collection_id');
        }
        
        $url = $base . '/ai/modelhub/document-collections/' . rawurlencode($collectionId) . '/documents/' . rawurlencode($remoteId);
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ];
        if ($tokenId !== '') {
            $headers['X-IONOS-Token-Id'] = $tokenId;
        }
        
        $http = \Joomla\CMS\Http\HttpFactory::getHttp();
        $resp = $http->delete($url, [], $headers, 30);
        if ($resp->code >= 200 && $resp->code < 300) {
            return true;
        }
        if ($resp->code === 404) {
            return true;
        }
        throw new \RuntimeException('IONOS delete failed: HTTP ' . $resp->code . ' ' . mb_substr((string)$resp->body, 0, 500));
    }

    protected function snapshotCollectionCount(): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
            $qCount = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__aichatbot_docs'));
            $db->setQuery($qCount);
            $count = (int)$db->loadResult();

            $ddl = "CREATE TABLE IF NOT EXISTS `#__aichatbot_collection_stats` (
  `stat_date` DATE NOT NULL,
  `docs_count` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $db->setQuery($ddl)->execute();

            // Try MySQL-specific ON DUPLICATE KEY UPDATE first
            try {
                $sql = sprintf(
                    "INSERT INTO `#__aichatbot_collection_stats` (`stat_date`, `docs_count`) VALUES (%s, %d) ON DUPLICATE KEY UPDATE `docs_count` = VALUES(`docs_count`)",
                    $db->quote($today),
                    $count
                );
                $db->setQuery($sql)->execute();
            } catch (\Throwable $e) {
                // Fallback to update/insert
                $upd = $db->getQuery(true)
                    ->update($db->quoteName('#__aichatbot_collection_stats'))
                    ->set($db->quoteName('docs_count') . ' = ' . (int)$count)
                    ->where($db->quoteName('stat_date') . ' = ' . $db->quote($today));
                $db->setQuery($upd)->execute();
                
                if ($db->getAffectedRows() === 0) {
                    $ins = $db->getQuery(true)
                        ->insert($db->quoteName('#__aichatbot_collection_stats'))
                        ->columns([$db->quoteName('stat_date'), $db->quoteName('docs_count')])
                        ->values($db->quote($today) . ',' . (int)$count);
                    $db->setQuery($ins)->execute();
                }
            }
        } catch (\Throwable $ignore) {}
    }
}
