<?php
/**
 * API controller for usage JSON, CSV export and collection operations
 */

namespace Joomla\Component\Bears_aichatbot\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Language\Text;

class ApiController extends BaseController
{
    private function respond($payload, int $code = 200): void
    {
        $app = Factory::getApplication();
        if (function_exists('http_response_code')) {
            @http_response_code($code);
        }
        $app->setHeader('Content-Type', 'application/json', true);
        $app->setHeader('status', (string) $code, true);
        echo json_encode($payload);
        $app->close();
    }

    private function respondError(string $message, int $code = 400, array $extra = []): void
    {
        $this->respond(['error' => array_merge(['code' => $code, 'message' => $message], $extra)], $code);
    }

    public function filtersJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $this->respondError('Forbidden', 403);
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $options = [
            'modules' => [],
            'models' => [],
            'collections' => [],
        ];
        try {
            $q = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('module_id'))
                ->from($db->quoteName('#__aichatbot_usage'))
                ->where($db->quoteName('module_id') . ' IS NOT NULL')
                ->order($db->quoteName('module_id') . ' ASC');
            $db->setQuery($q); $options['modules'] = array_map('intval', (array)$db->loadColumn());
        } catch (\Throwable $ignore) {}
        try {
            $q = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('model'))
                ->from($db->quoteName('#__aichatbot_usage'))
                ->where($db->quoteName('model') . ' IS NOT NULL AND ' . $db->quoteName('model') . " != ''")
                ->order($db->quoteName('model') . ' ASC');
            $db->setQuery($q); $options['models'] = (array)$db->loadColumn();
        } catch (\Throwable $ignore) {}
        try {
            $q = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('collection_id'))
                ->from($db->quoteName('#__aichatbot_usage'))
                ->where($db->quoteName('collection_id') . ' IS NOT NULL AND ' . $db->quoteName('collection_id') . " != ''")
                ->order($db->quoteName('collection_id') . ' ASC');
            $db->setQuery($q); $options['collections'] = (array)$db->loadColumn();
        } catch (\Throwable $ignore) {}
        $this->respond(['data' => $options], 200);
    }

    public function usageJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $this->respondError('Forbidden', 403);
        }

        $input = $app->input;
        $from = $input->getString('from');
        $to = $input->getString('to');
        $group = $input->getCmd('group', 'day'); // day|week|month
        $moduleId = $input->getInt('module_id');
        $model = $input->getString('model');
        $collectionId = $input->getString('collection_id');

        $db = Factory::getContainer()->get('DatabaseDriver');
        $q = $db->getQuery(true);

        $dateExpr = 'DATE(created_at)';
        if ($group === 'week') {
            $dateExpr = "STR_TO_DATE(CONCAT(YEARWEEK(created_at, 3),' Monday'), '%X%V %W')"; // ISO week start
        } elseif ($group === 'month') {
            $dateExpr = "DATE_FORMAT(created_at, '%Y-%m-01')";
        }
        $q->select($dateExpr . ' AS period')
          ->select('COUNT(*) AS requests')
          ->select('SUM(prompt_tokens) AS prompt_tokens')
          ->select('SUM(completion_tokens) AS completion_tokens')
          ->select('SUM(total_tokens) AS total_tokens')
          ->select('SUM(COALESCE(retrieved,0)) AS retrieved')
          ->select('SUM(estimated_cost) AS estimated_cost')
          ->from($db->quoteName('#__aichatbot_usage'))
          ->group('period')
          ->order('period ASC');

        if ($from !== '') { $q->where($db->quoteName('created_at') . ' >= ' . $db->quote($from)); }
        if ($to !== '') { $q->where($db->quoteName('created_at') . ' <= ' . $db->quote($to)); }
        if ($moduleId) { $q->where($db->quoteName('module_id') . ' = ' . (int)$moduleId); }
        if ($model !== '') { $q->where($db->quoteName('model') . ' = ' . $db->quote($model)); }
        if ($collectionId !== '') { $q->where($db->quoteName('collection_id') . ' = ' . $db->quote($collectionId)); }

        $db->setQuery($q);
        $rows = (array)$db->loadAssocList();

        $this->respond(['data' => $rows], 200);
    }

    public function seriesJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $this->respondError('Forbidden', 403);
        }
        $input = $app->input;
        $from = $input->getString('from');
        $to = $input->getString('to');
        $group = $input->getCmd('group', 'day');
        $moduleId = $input->getInt('module_id');
        $model = $input->getString('model');
        $collectionId = $input->getString('collection_id');

        $db = Factory::getContainer()->get('DatabaseDriver');
        $dateExpr = 'DATE(created_at)';
        if ($group === 'week') {
            $dateExpr = "STR_TO_DATE(CONCAT(YEARWEEK(created_at, 3),' Monday'), '%X%V %W')";
        } elseif ($group === 'month') {
            $dateExpr = "DATE_FORMAT(created_at, '%Y-%m-01')";
        }

        $q = $db->getQuery(true)
            ->select($dateExpr . ' AS period')
            ->select('COUNT(*) AS requests')
            ->select('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) AS errors')
            ->from($db->quoteName('#__aichatbot_usage'))
            ->group('period')
            ->order('period ASC');
        if ($from !== '') { $q->where($db->quoteName('created_at') . ' >= ' . $db->quote($from)); }
        if ($to !== '') { $q->where($db->quoteName('created_at') . ' <= ' . $db->quote($to)); }
        if ($moduleId) { $q->where($db->quoteName('module_id') . ' = ' . (int)$moduleId); }
        if ($model !== '') { $q->where($db->quoteName('model') . ' = ' . $db->quote($model)); }
        if ($collectionId !== '') { $q->where($db->quoteName('collection_id') . ' = ' . $db->quote($collectionId)); }
        $db->setQuery($q); $rows = (array)$db->loadAssocList();

        $this->respond(['data' => $rows], 200);
    }

    public function kpisJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $this->respondError('Forbidden', 403);
        }
        $input = $app->input;
        $from = $input->getString('from');
        $to = $input->getString('to');
        $moduleId = $input->getInt('module_id');
        $model = $input->getString('model');
        $collectionId = $input->getString('collection_id');

        $db = Factory::getContainer()->get('DatabaseDriver');

        // KPIs from usage table
        $q = $db->getQuery(true)
            ->select('COUNT(*) AS requests')
            ->select('SUM(prompt_tokens) AS prompt_tokens')
            ->select('SUM(completion_tokens) AS completion_tokens')
            ->select('SUM(total_tokens) AS total_tokens')
            ->select('SUM(COALESCE(retrieved,0)) AS retrieved')
            ->select('SUM(estimated_cost) AS total_cost')
            ->from($db->quoteName('#__aichatbot_usage'));
        if ($from !== '') { $q->where($db->quoteName('created_at') . ' >= ' . $db->quote($from)); }
        if ($to !== '') { $q->where($db->quoteName('created_at') . ' <= ' . $db->quote($to)); }
        if ($moduleId) { $q->where($db->quoteName('module_id') . ' = ' . (int)$moduleId); }
        if ($model !== '') { $q->where($db->quoteName('model') . ' = ' . $db->quote($model)); }
        if ($collectionId !== '') { $q->where($db->quoteName('collection_id') . ' = ' . $db->quote($collectionId)); }
        $db->setQuery($q);
        $kpis = (array)$db->loadAssoc();

        // Current docs count
        $q2 = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__aichatbot_docs'));
        $db->setQuery($q2);
        $docs = (int)$db->loadResult();

        $kpis['docs'] = $docs;

        $this->respond(['data' => $kpis], 200);
    }

    public function collectionJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $this->respondError('Forbidden', 403);
        }
        $input = $app->input;
        $from = $input->getString('from');
        $to = $input->getString('to');

        $db = Factory::getContainer()->get('DatabaseDriver');
        $q = $db->getQuery(true)
            ->select($db->quoteName(['stat_date','docs_count']))
            ->from($db->quoteName('#__aichatbot_collection_stats'))
            ->order($db->quoteName('stat_date') . ' ASC');
        if ($from !== '') { $q->where($db->quoteName('stat_date') . ' >= ' . $db->quote($from)); }
        if ($to !== '') { $q->where($db->quoteName('stat_date') . ' <= ' . $db->quote($to)); }
        $db->setQuery($q); $rows = (array)$db->loadAssocList();

        $this->respond(['data' => $rows], 200);
    }

    public function spendJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $this->respondError('Forbidden', 403);
        }
        $input = $app->input;
        $from = $input->getString('from');
        $to = $input->getString('to');
        $group = $input->getCmd('group', 'day');
        $moduleId = $input->getInt('module_id');
        $model = $input->getString('model');
        $collectionId = $input->getString('collection_id');

        $db = Factory::getContainer()->get('DatabaseDriver');
        $dateExpr = 'DATE(created_at)';
        if ($group === 'week') {
            $dateExpr = "STR_TO_DATE(CONCAT(YEARWEEK(created_at, 3),' Monday'), '%X%V %W')";
        } elseif ($group === 'month') {
            $dateExpr = "DATE_FORMAT(created_at, '%Y-%m-01')";
        }
        $q = $db->getQuery(true)
            ->select($dateExpr . ' AS period')
            ->select('SUM(estimated_cost) AS cost')
            ->from($db->quoteName('#__aichatbot_usage'))
            ->group('period')
            ->order('period ASC');
        if ($from !== '') { $q->where($db->quoteName('created_at') . ' >= ' . $db->quote($from)); }
        if ($to !== '') { $q->where($db->quoteName('created_at') . ' <= ' . $db->quote($to)); }
        if ($moduleId) { $q->where($db->quoteName('module_id') . ' = ' . (int)$moduleId); }
        if ($model !== '') { $q->where($db->quoteName('model') . ' = ' . $db->quote($model)); }
        if ($collectionId !== '') { $q->where($db->quoteName('collection_id') . ' = ' . $db->quote($collectionId)); }

        $db->setQuery($q);
        $rows = (array)$db->loadAssocList();

        $this->respond(['data' => $rows], 200);
    }

    public function rebuildCollection()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $this->respondError('Forbidden', 403);
        }
        // CSRF
        if (!\Joomla\CMS\Session\Session::checkToken('post')) {
            $this->respondError('Invalid token', 403);
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $in = $app->input;
        // Load token+endpoint
        $token = '';$tokenId = '';$apiBase = '';
        try {
            $q = $db->getQuery(true)
                ->select($db->quoteName(['params']))
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_bears_aichatbot'))
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('id') . ' ASC')
                ->setLimit(1);
            $db->setQuery($q);
            $paramsStr = (string)($db->loadResult() ?? '');
            if ($paramsStr !== '') {
                $reg = new \Joomla\Registry\Registry($paramsStr);
                $token = trim((string)$reg->get('ionos_token', ''));
                $tokenId = trim((string)$reg->get('ionos_token_id', ''));
                $endpoint = trim((string)$reg->get('ionos_endpoint', 'https://openai.inference.de-txl.ionos.com/v1/chat/completions'));
                $apiBase = preg_replace('#/v1/.*$#', '/v1', $endpoint) ?: 'https://api.inference.ionos.com/v1';
                $apiBase = rtrim($apiBase, '/');
            }
        } catch (\Throwable $ignore) {}
        if ($token === '' || $apiBase === '') {
            $this->respondError('IONOS token or endpoint not configured', 400);
        }

        $http = \Joomla\CMS\Http\HttpFactory::getHttp();
        $headers = [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json', 'Content-Type' => 'application/json' ];
        if ($tokenId !== '') { $headers['X-IONOS-Token-Id'] = $tokenId; }

        // Get current collection id from state
        $activeCollectionId = '';
        try {
            $qcid = $db->getQuery(true)
                ->select($db->quoteName('collection_id'))
                ->from($db->quoteName('#__aichatbot_state'))
                ->where($db->quoteName('id') . ' = 1')
                ->setLimit(1);
            $db->setQuery($qcid);
            $activeCollectionId = (string)($db->loadResult() ?? '');
        } catch (\Throwable $ignore) {}
        if ($activeCollectionId === '') {
            $this->respondError('No collection_id configured', 400);
        }

        // Try to bulk delete documents in the existing collection
        $bulkDeleted = false;
        try {
            $delUrl = $apiBase . '/document-collections/' . rawurlencode($activeCollectionId) . '/documents';
            $respDel = $http->delete($delUrl, $headers, 60);
            if ($respDel->code >= 200 && $respDel->code < 300) { $bulkDeleted = true; }
        } catch (\Throwable $ignore) {}

        // Fallback: delete per-doc using locally known remote ids
        if (!$bulkDeleted) {
            try {
                $qdocs = $db->getQuery(true)
                    ->select($db->quoteName('doc_id'))
                    ->from($db->quoteName('#__aichatbot_docs'))
                    ->where($db->quoteName('doc_id') . " IS NOT NULL AND " . $db->quoteName('doc_id') . " != ''");
                $db->setQuery($qdocs);
                $docIds = (array)$db->loadColumn();
                foreach ($docIds as $docId) {
                    try {
                        $url = $apiBase . '/document-collections/' . rawurlencode($activeCollectionId) . '/documents/' . rawurlencode((string)$docId);
                        $http->delete($url, $headers, 30);
                    } catch (\Throwable $ignore2) {}
                }
            } catch (\Throwable $ignore) {}
        }

        // Clear local mapping and enqueue upserts for all published content
        $enqueued = 0;
        try {
            $db->setQuery('DELETE FROM ' . $db->quoteName('#__aichatbot_docs'))->execute();
            $q = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('state') . ' = 1');
            $db->setQuery($q);
            $ids = array_map('intval', (array)$db->loadColumn());
            foreach ($ids as $cid) {
                $ins = $db->getQuery(true)
                    ->insert($db->quoteName('#__aichatbot_jobs'))
                    ->columns([$db->quoteName('content_id'), $db->quoteName('action'), $db->quoteName('status'), $db->quoteName('attempts')])
                    ->values((int)$cid . ',' . $db->quote('upsert') . ',' . $db->quote('queued') . ',0');
                try { $db->setQuery($ins)->execute(); $enqueued++; } catch (\Throwable $ignore) {}
            }
        } catch (\Throwable $e) {}

        $this->respond(['data' => ['collection_id' => $activeCollectionId, 'enqueued' => $enqueued, 'mode' => 'reuse']], 200);
    }

    public function latencyJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $this->respondError('Forbidden', 403);
        }
        $in = $app->input;
        $from = $in->getString('from');
        $to = $in->getString('to');
        $group = $in->getCmd('group', 'day');
        $moduleId = $in->getInt('module_id');
        $model = $in->getString('model');
        $collectionId = $in->getString('collection_id');
        $db = Factory::getContainer()->get('DatabaseDriver');
        $dateExpr = 'DATE(created_at)';
        if ($group === 'week') { $dateExpr = "STR_TO_DATE(CONCAT(YEARWEEK(created_at, 3),' Monday'), '%X%V %W')"; }
        elseif ($group === 'month') { $dateExpr = "DATE_FORMAT(created_at, '%Y-%m-01')"; }
        $q = $db->getQuery(true)
            ->select($dateExpr . ' AS period')
            ->select('ROUND(AVG(duration_ms),0) AS avg_ms')
            ->select('MAX(duration_ms) AS max_ms')
            ->from($db->quoteName('#__aichatbot_usage'))
            ->group('period')
            ->order('period ASC');
        if ($from !== '') { $q->where($db->quoteName('created_at') . ' >= ' . $db->quote($from)); }
        if ($to !== '') { $q->where($db->quoteName('created_at') . ' <= ' . $db->quote($to)); }
        if ($moduleId) { $q->where($db->quoteName('module_id') . ' = ' . (int)$moduleId); }
        if ($model !== '') { $q->where($db->quoteName('model') . ' = ' . $db->quote($model)); }
        if ($collectionId !== '') { $q->where($db->quoteName('collection_id') . ' = ' . $db->quote($collectionId)); }
        $db->setQuery($q); $rows = (array)$db->loadAssocList();
        $this->respond(['data' => $rows], 200);
    }

    public function histTokensJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $this->respondError('Forbidden', 403);
        }
        $in = $app->input;
        $from = $in->getString('from');
        $to = $in->getString('to');
        $moduleId = $in->getInt('module_id');
        $model = $in->getString('model');
        $collectionId = $in->getString('collection_id');
        $db = Factory::getContainer()->get('DatabaseDriver');
        $q = $db->getQuery(true)
            ->select("CASE 
                WHEN total_tokens <= 100 THEN '0-100'
                WHEN total_tokens <= 500 THEN '101-500'
                WHEN total_tokens <= 1000 THEN '501-1000'
                WHEN total_tokens <= 2000 THEN '1001-2000'
                WHEN total_tokens <= 4000 THEN '2001-4000'
                ELSE '>4000' END AS bucket")
            ->select('MIN(total_tokens) AS min_tokens')
            ->select('COUNT(*) AS count')
            ->from($db->quoteName('#__aichatbot_usage'))
            ->group('bucket')
            ->order('min_tokens ASC');
        if ($from !== '') { $q->where($db->quoteName('created_at') . ' >= ' . $db->quote($from)); }
        if ($to !== '') { $q->where($db->quoteName('created_at') . ' <= ' . $db->quote($to)); }
        if ($moduleId) { $q->where($db->quoteName('module_id') . ' = ' . (int)$moduleId); }
        if ($model !== '') { $q->where($db->quoteName('model') . ' = ' . $db->quote($model)); }
        if ($collectionId !== '') { $q->where($db->quoteName('collection_id') . ' = ' . $db->quote($collectionId)); }
        $db->setQuery($q); $rows = (array)$db->loadAssocList();
        $this->respond(['data' => $rows], 200);
    }

    public function outcomesJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $this->respondError('Forbidden', 403);
        }
        $in = $app->input;
        $from = $in->getString('from');
        $to = $in->getString('to');
        $group = $in->getCmd('group', 'day');
        $moduleId = $in->getInt('module_id');
        $model = $in->getString('model');
        $collectionId = $in->getString('collection_id');
        $db = Factory::getContainer()->get('DatabaseDriver');
        $dateExpr = 'DATE(created_at)';
        if ($group === 'week') { $dateExpr = "STR_TO_DATE(CONCAT(YEARWEEK(created_at, 3),' Monday'), '%X%V %W')"; }
        elseif ($group === 'month') { $dateExpr = "DATE_FORMAT(created_at, '%Y-%m-01')"; }
        $q = $db->getQuery(true)
            ->select($dateExpr . ' AS period')
            ->select("SUM(CASE WHEN outcome='answered' THEN 1 ELSE 0 END) AS answered")
            ->select("SUM(CASE WHEN outcome='refused' THEN 1 ELSE 0 END) AS refused")
            ->select("SUM(CASE WHEN outcome='error' THEN 1 ELSE 0 END) AS error")
            ->from($db->quoteName('#__aichatbot_usage'))
            ->group('period')
            ->order('period ASC');
        if ($from !== '') { $q->where($db->quoteName('created_at') . ' >= ' . $db->quote($from)); }
        if ($to !== '') { $q->where($db->quoteName('created_at') . ' <= ' . $db->quote($to)); }
        if ($moduleId) { $q->where($db->quoteName('module_id') . ' = ' . (int)$moduleId); }
        if ($model !== '') { $q->where($db->quoteName('model') . ' = ' . $db->quote($model)); }
        if ($collectionId !== '') { $q->where($db->quoteName('collection_id') . ' = ' . $db->quote($collectionId)); }
        $db->setQuery($q); $rows = (array)$db->loadAssocList();
        $this->respond(['data' => $rows], 200);
    }

    public function collectionMetaJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $this->respondError('Forbidden', 403);
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $in = $app->input;
        $collectionId = trim($in->getString('collection_id', ''));
        if ($collectionId === '') {
            try {
                $q = $db->getQuery(true)
                    ->select($db->quoteName('collection_id'))
                    ->from($db->quoteName('#__aichatbot_state'))
                    ->where($db->quoteName('id') . ' = 1')
                    ->setLimit(1);
                $db->setQuery($q);
                $cid = (string)($db->loadResult() ?? '');
                if ($cid !== '') { $collectionId = $cid; }
            } catch (\Throwable $ignore) {}
        }
        if ($collectionId === '') {
            $this->respondError('No collection_id configured', 400);
        }
        // Load token and endpoint from any published module of our type
        $token = '';
        $tokenId = '';
        $apiBase = '';
        try {
            $q = $db->getQuery(true)
                ->select($db->quoteName(['params']))
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_bears_aichatbot'))
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('id') . ' ASC')
                ->setLimit(1);
            $db->setQuery($q);
            $paramsStr = (string)($db->loadResult() ?? '');
            if ($paramsStr !== '') {
                $reg = new \Joomla\Registry\Registry($paramsStr);
                $token = trim((string)$reg->get('ionos_token', ''));
                $tokenId = trim((string)$reg->get('ionos_token_id', ''));
                $endpoint = trim((string)$reg->get('ionos_endpoint', 'https://openai.inference.de-txl.ionos.com/v1/chat/completions'));
                $apiBase = preg_replace('#/v1/.*$#', '/v1', $endpoint) ?: 'https://api.inference.ionos.com/v1';
                $apiBase = rtrim($apiBase, '/');
            }
        } catch (\Throwable $ignore) {}
        if ($token === '' || $apiBase === '') {
            $this->respondError('IONOS token or endpoint not configured', 400);
        }
        try {
            $http = \Joomla\CMS\Http\HttpFactory::getHttp();
            $url = $apiBase . '/document-collections/' . rawurlencode($collectionId);
            $headers = [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ];
            if ($tokenId !== '') { $headers['X-IONOS-Token-Id'] = $tokenId; }
            $resp = $http->get($url, $headers, 30);
            if ($resp->code >= 200 && $resp->code < 300) {
                $data = json_decode((string)$resp->body, true);
                $this->respond(['data' => $data], 200);
            }
            $this->respondError('HTTP ' . $resp->code, $resp->code, ['body' => mb_substr((string)$resp->body, 0, 2000)]);
        } catch (\Throwable $e) {
            $this->respondError($e->getMessage(), 500);
        }
    }

    public function usageCsv()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $this->respondError('Forbidden', 403);
        }
        // CSRF check
        if (!\Joomla\CMS\Session\Session::checkToken('get')) {
            $this->respondError('Invalid token', 403);
        }

        $input = $app->input;
        $from = $input->getString('from');
        $to = $input->getString('to');
        $moduleId = $input->getInt('module_id');
        $model = $input->getString('model');
        $collectionId = $input->getString('collection_id');

        $db = Factory::getContainer()->get('DatabaseDriver');
        $q = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__aichatbot_usage'))
            ->order($db->quoteName('created_at') . ' DESC');
        if ($from !== '') { $q->where($db->quoteName('created_at') . ' >= ' . $db->quote($from)); }
        if ($to !== '') { $q->where($db->quoteName('created_at') . ' <= ' . $db->quote($to)); }
        if ($moduleId) { $q->where($db->quoteName('module_id') . ' = ' . (int)$moduleId); }
        if ($model !== '') { $q->where($db->quoteName('model') . ' = ' . $db->quote($model)); }
        if ($collectionId !== '') { $q->where($db->quoteName('collection_id') . ' = ' . $db->quote($collectionId)); }
        $db->setQuery($q);
        $rows = (array)$db->loadAssocList();

        // Stream CSV on success
        $app->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="aichatbot_usage.csv"', true);
        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $r) fputcsv($out, $r);
        }
        fclose($out);
        $app->close();
    }
}
