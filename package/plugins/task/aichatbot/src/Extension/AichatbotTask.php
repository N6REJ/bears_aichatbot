<?php
/**
 * Joomla 5 Task plugin: processes AI Chatbot document collection sync.
 */

defined('_JEXEC') or die;

namespace PlgTaskAichatbot\Extension;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Scheduler\Task\TaskOption;
use Joomla\CMS\Scheduler\Task\TaskStatus;
use Joomla\CMS\Scheduler\TaskInterface;
use Joomla\CMS\Scheduler\TaskResult;
use Joomla\Database\DatabaseInterface;
use Joomla\Utilities\ArrayHelper;

class AichatbotTask extends CMSPlugin
{
    /** @var CMSApplicationInterface */
    protected $app;

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onRegisterTasks' => 'onRegisterTasks',
            'onExecuteTask'   => 'onExecuteTask',
        ];
    }

    /**
     * Register scheduler tasks exposed by this plugin
     */
    public function onRegisterTasks(): array
    {
        return [
            TaskOption::create('aichatbot.queue', 'Process AI Chatbot job queue', '\\PlgTaskAichatbot\\Extension\\AichatbotTask'),
            TaskOption::create('aichatbot.reconcile', 'Reconcile AI Chatbot collection', '\\PlgTaskAichatbot\\Extension\\AichatbotTask'),
        ];
    }

    public function onExecuteTask(TaskInterface $task): TaskResult
    {
        $name = $task->getName();
        try {
            if ($name === 'aichatbot.queue') {
                [$ok, $info] = $this->processQueue();
                return new TaskResult($ok ? TaskStatus::OK : TaskStatus::KNOCKOUT, $info);
            }
            if ($name === 'aichatbot.reconcile') {
                [$ok, $info] = $this->reconcile();
                return new TaskResult($ok ? TaskStatus::OK : TaskStatus::KNOCKOUT, $info);
            }
            return new TaskResult(TaskStatus::NO_RUN, 'Unknown task: ' . $name);
        } catch (\Throwable $e) {
            return new TaskResult(TaskStatus::KNOCKOUT, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Process queued upsert/delete jobs.
     * @return array{0:bool,1:string}
     */
    protected function processQueue(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $batch = (int) $this->params->get('batch_size', 50);
        $maxAttempts = (int) $this->params->get('max_attempts', 5);
        $processed = 0; $failed = 0;

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
            $ok = false; $err = '';
            try {
                if ($action === 'upsert') {
                    $ok = $this->handleUpsert($db, $contentId);
                    if (!$ok) { $err = 'Upsert returned false'; }
                } elseif ($action === 'delete') {
                    $ok = $this->handleDelete($db, $contentId);
                    if (!$ok) { $err = 'Delete returned false'; }
                } else {
                    $ok = true; // ignore unknown
                }
            } catch (\Throwable $e) {
                $ok = false; $err = $e->getMessage();
            }

            $upd = $db->getQuery(true)
                ->update($db->quoteName('#__aichatbot_jobs'))
                ->set($db->quoteName('attempts') . ' = ' . ((int)$job['attempts'] + 1))
                ->set($db->quoteName('status')   . ' = ' . $db->quote($ok ? 'done' : 'error'))
                ->set($db->quoteName('last_error') . ' = ' . $db->quote($ok ? null : mb_substr($err, 0, 500)))
                ->where($db->quoteName('id') . ' = ' . (int)$id);
            $db->setQuery($upd)->execute();

            $processed += (int) $ok; $failed += (int) (!$ok);
        }

        return [true, sprintf('Queue processed: %d ok, %d failed', $processed, $failed)];
    }

    /**
     * Reconcile mapping with current Joomla articles in selected categories.
     */
    protected function reconcile(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $processed = 0; $deleted = 0;

        $catIds = $this->params->get('selected_categories', []);
        if (is_string($catIds)) { $catIds = array_filter(array_map('intval', explode(',', $catIds))); }
        elseif (!is_array($catIds)) { $catIds = []; }

        // Expand category tree
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
            } catch (\Throwable $e) { $allCatIds = $catIds; }
        }
        if (empty($allCatIds)) { $allCatIds = $catIds; }

        // Fetch currently published articles in scope
        $q = $db->getQuery(true)
            ->select($db->quoteName(['id','title','introtext','fulltext','state']))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1');
        if (!empty($allCatIds)) {
            $q->where($db->quoteName('catid') . ' IN (' . implode(',', array_map('intval', $allCatIds)) . ')');
        }
        $db->setQuery($q);
        $articles = (array) $db->loadAssocList('id');

        // Map existing docs
        $dq = $db->getQuery(true)
            ->select($db->quoteName(['content_id','content_hash','remote_id','state']))
            ->from($db->quoteName('#__aichatbot_docs'));
        $db->setQuery($dq);
        $docs = [];
        foreach ((array)$db->loadAssocList() as $row) { $docs[(int)$row['content_id']] = $row; }

        // Upsert changed/new
        foreach ($articles as $id => $a) {
            $hash = $this->computeHash($a['title'], $a['introtext'], $a['fulltext'], (int)$a['state']);
            $row  = $docs[(int)$id] ?? null;
            if (!$row || $row['content_hash'] !== $hash || (int)$row['state'] !== 1) {
                $ok = $this->handleUpsert($db, (int)$id, $a, $hash);
                if ($ok) { $processed++; }
            }
        }
        // Delete out-of-scope or unpublished
        foreach ($docs as $contentId => $row) {
            if (!isset($articles[$contentId])) {
                $ok = $this->handleDelete($db, (int)$contentId);
                if ($ok) { $deleted++; }
            }
        }

        return [true, sprintf('Reconcile: %d upserts, %d deletes', $processed, $deleted)];
    }

    protected function handleUpsert(DatabaseInterface $db, int $contentId, ?array $article = null, ?string $hash = null): bool
    {
        // Load article if not provided
        if ($article === null) {
            $q = $db->getQuery(true)
                ->select($db->quoteName(['id','title','introtext','fulltext','state']))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . (int)$contentId)
                ->setLimit(1);
            $db->setQuery($q);
            $row = $db->loadAssoc();
            if (!$row) { return false; }
            $article = $row;
        }
        if ((int)$article['state'] !== 1) {
            // If not published, ensure remote deletion
            return $this->handleDelete($db, $contentId);
        }

        $title = (string)$article['title'];
        $intro = (string)$article['introtext'];
        $full  = (string)$article['fulltext'];
        if ($hash === null) { $hash = $this->computeHash($title, $intro, $full, 1); }

        // Determine remote id
        $dq = $db->getQuery(true)
            ->select($db->quoteName(['remote_id']))
            ->from($db->quoteName('#__aichatbot_docs'))
            ->where($db->quoteName('content_id') . ' = ' . (int)$contentId)
            ->setLimit(1);
        $db->setQuery($dq);
        $remoteId = (string) ($db->loadResult() ?? '');
        if ($remoteId === '') { $remoteId = 'article-' . $contentId; }

        // Prepare payload (simple text merge; real impl should chunk and include metadata)
        $text = $this->normalize($title) . "\n\n" . $this->normalize($intro) . "\n\n" . $this->normalize($full);

        // Call external API (stubbed to true for now)
        $ok = $this->apiUpsert($remoteId, $text, [ 'content_id' => $contentId, 'title' => $title ]);
        if (!$ok) { return false; }

        // Update mapping row
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        $ins = $db->getQuery(true)
            ->insert($db->quoteName('#__aichatbot_docs'))
            ->columns($db->quoteName(['content_id','remote_id','content_hash','last_synced','state']))
            ->values(implode(',', [ (int)$contentId, $db->quote($remoteId), $db->quote($hash), $db->quote($now), 1 ]))
            ->onDuplicate(
                $db->quoteName('remote_id') . ' = VALUES(' . $db->quoteName('remote_id') . '), '
                . $db->quoteName('content_hash') . ' = VALUES(' . $db->quoteName('content_hash') . '), '
                . $db->quoteName('last_synced') . ' = VALUES(' . $db->quoteName('last_synced') . '), '
                . $db->quoteName('state') . ' = VALUES(' . $db->quoteName('state') . ')'
            );
        $db->setQuery($ins)->execute();
        return true;
    }

    protected function handleDelete(DatabaseInterface $db, int $contentId): bool
    {
        // Get remote id
        $dq = $db->getQuery(true)
            ->select($db->quoteName('remote_id'))
            ->from($db->quoteName('#__aichatbot_docs'))
            ->where($db->quoteName('content_id') . ' = ' . (int)$contentId)
            ->setLimit(1);
        $db->setQuery($dq);
        $remoteId = (string) ($db->loadResult() ?? 'article-' . $contentId);

        $ok = $this->apiDelete($remoteId);
        if (!$ok) { return false; }

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

    // Stub external calls - replace with IONOS Document Collection API
    protected function apiUpsert(string $remoteId, string $text, array $metadata): bool
    {
        // TODO: implement HTTP call using plugin params (ionos_token_id, ionos_token, endpoint, collection_id)
        return true;
    }
    protected function apiDelete(string $remoteId): bool
    {
        // TODO: implement HTTP call
        return true;
    }
}
