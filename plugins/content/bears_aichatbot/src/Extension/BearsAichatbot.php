<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.10.02
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
 */
namespace Joomla\Plugin\Content\BearsAichatbot\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BearsAichatbot extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /**
     * Handle article save: enqueue upsert if published and track hash; enqueue delete if unpublished
     */
    public function onContentAfterSave(string $context, $article, bool $isNew, $data = []): void
    {
        // Only act on com_content.article
        if (stripos($context, 'com_content') === false) {
            return;
        }
        if (!$this->params->get('enabled_queue', 1)) {
            return;
        }

        // Article object may vary; support array or object
        $id    = (int)($article->id ?? ($data['id'] ?? 0));
        $title = (string)($article->title ?? ($data['title'] ?? ''));
        $intro = (string)($article->introtext ?? ($data['introtext'] ?? ''));
        $full  = (string)($article->fulltext ?? ($data['fulltext'] ?? ''));
        $state = (int)($article->state ?? ($data['state'] ?? 0));

        if ($id <= 0) {
            return;
        }

        $hash = $this->computeHash($title, $intro, $full, $state);
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Upsert mapping row
        $db->transactionStart();
        try {
            // Read current mapping
            $query = $db->getQuery(true)
                ->select($db->quoteName(['content_id', 'content_hash', 'remote_id', 'state']))
                ->from($db->quoteName('#__aichatbot_docs'))
                ->where($db->quoteName('content_id') . ' = ' . (int)$id)
                ->setLimit(1);
            $db->setQuery($query);
            $row = $db->loadAssoc();

            $now = (new \DateTime())->format('Y-m-d H:i:s');
            $needsUpsert = false;
            $needsDelete = false;

            if ($state == 1) { // published
                if (!$row) {
                    // Insert
                    $ins = $db->getQuery(true)
                        ->insert($db->quoteName('#__aichatbot_docs'))
                        ->columns($db->quoteName(['content_id', 'content_hash', 'last_synced', 'state']))
                        ->values(implode(',', [ (int)$id, $db->quote($hash), $db->quote(null), (int)$state ]));
                    $db->setQuery($ins)->execute();
                    $needsUpsert = true;
                } else {
                    // Update hash/state if changed
                    if ($row['content_hash'] !== $hash || (int)$row['state'] !== $state) {
                        $upd = $db->getQuery(true)
                            ->update($db->quoteName('#__aichatbot_docs'))
                            ->set($db->quoteName('content_hash') . ' = ' . $db->quote($hash))
                            ->set($db->quoteName('last_synced') . ' = ' . $db->quote(null))
                            ->set($db->quoteName('state') . ' = ' . (int)$state)
                            ->where($db->quoteName('content_id') . ' = ' . (int)$id);
                        $db->setQuery($upd)->execute();
                        $needsUpsert = true;
                    }
                }
            } else {
                // Unpublished or trashed: delete from collection
                if ($row) {
                    $needsDelete = true;
                    // Keep mapping until job completes; task processor will remove it after remote delete
                    $upd = $db->getQuery(true)
                        ->update($db->quoteName('#__aichatbot_docs'))
                        ->set($db->quoteName('state') . ' = ' . (int)$state)
                        ->where($db->quoteName('content_id') . ' = ' . (int)$id);
                    $db->setQuery($upd)->execute();
                }
            }

            // Enqueue job accordingly
            if ($needsUpsert) {
                $this->enqueueJob($db, $id, 'upsert');
            } elseif ($needsDelete) {
                $this->enqueueJob($db, $id, 'delete');
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            // Swallow to avoid breaking save; consider logging
        }
    }

    /**
     * Handle delete: enqueue delete job
     */
    public function onContentAfterDelete(string $context, $article): void
    {
        if (stripos($context, 'com_content') === false) {
            return;
        }
        if (!$this->params->get('enabled_queue', 1)) {
            return;
        }
        $id = (int)($article->id ?? 0);
        if ($id <= 0) return;

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->transactionStart();
        try {
            // Enqueue delete job
            $this->enqueueJob($db, $id, 'delete');
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
        }
    }

    /**
     * Handle state changes for multiple items
     */
    public function onContentChangeState(string $context, $pks, int $value): void
    {
        if (stripos($context, 'com_content') === false) {
            return;
        }
        if (!$this->params->get('enabled_queue', 1)) {
            return;
        }
        if (!is_array($pks)) {
            $pks = [$pks];
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        foreach ($pks as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            $db->transactionStart();
            try {
                if ((int)$value === 1) {
                    // Mark mapping state; hash will be recalculated on next save or reconcile
                    $upd = $db->getQuery(true)
                        ->update($db->quoteName('#__aichatbot_docs'))
                        ->set($db->quoteName('state') . ' = 1')
                        ->where($db->quoteName('content_id') . ' = ' . (int)$id);
                    $db->setQuery($upd)->execute();
                    $this->enqueueJob($db, $id, 'upsert');
                } else {
                    $this->enqueueJob($db, $id, 'delete');
                }
                $db->transactionCommit();
            } catch (\Throwable $e) {
                $db->transactionRollback();
            }
        }
    }

    /**
     * Compute a deterministic content hash
     */
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

    protected function enqueueJob(DatabaseInterface $db, int $contentId, string $action): void
    {
        $ins = $db->getQuery(true)
            ->insert($db->quoteName('#__aichatbot_jobs'))
            ->columns($db->quoteName(['content_id', 'action', 'status', 'attempts']))
            ->values(implode(',', [ (int)$contentId, $db->quote($action), $db->quote('queued'), 0 ]));
        $db->setQuery($ins)->execute();
    }
}
