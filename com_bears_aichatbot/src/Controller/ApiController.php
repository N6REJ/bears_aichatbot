<?php
/**
 * API controller for usage JSON and CSV export
 */

namespace Joomla\Component\Bears_aichatbot\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Language\Text;

class ApiController extends BaseController
{
    public function filtersJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $app->setHeader('status', 403, true);
            echo json_encode(['error' => 'Forbidden']);
            $app->close();
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

        $app->setHeader('Content-Type', 'application/json', true);
        echo json_encode(['data' => $options]);
        $app->close();
    }

    public function usageJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $app->setHeader('status', 403, true);
            echo json_encode(['error' => 'Forbidden']);
            $app->close();
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

        $app->setHeader('Content-Type', 'application/json', true);
        echo json_encode(['data' => $rows]);
        $app->close();
    }

    public function seriesJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $app->setHeader('status', 403, true);
            echo json_encode(['error' => 'Forbidden']);
            $app->close();
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

        $app->setHeader('Content-Type', 'application/json', true);
        echo json_encode(['data' => $rows]);
        $app->close();
    }

    public function kpisJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $app->setHeader('status', 403, true);
            echo json_encode(['error' => 'Forbidden']);
            $app->close();
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

        $app->setHeader('Content-Type', 'application/json', true);
        echo json_encode(['data' => $kpis]);
        $app->close();
    }

    public function collectionJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $app->setHeader('status', 403, true);
            echo json_encode(['error' => 'Forbidden']);
            $app->close();
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
        $db->setQuery($q);
        $rows = (array)$db->loadAssocList();

        $app->setHeader('Content-Type', 'application/json', true);
        echo json_encode(['data' => $rows]);
        $app->close();
    }

    public function spendJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $app->setHeader('status', 403, true);
            echo json_encode(['error' => 'Forbidden']);
            $app->close();
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

        $app->setHeader('Content-Type', 'application/json', true);
        echo json_encode(['data' => $rows]);
        $app->close();
    }

    public function rebuildCollection()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $app->setHeader('status', 403, true);
            echo json_encode(['error' => 'Forbidden']);
            $app->close();
        }
        // CSRF
        if (!\Joomla\CMS\Session\Session::checkToken('post')) {
            $app->setHeader('status', 403, true);
            echo json_encode(['error' => 'Invalid token']);
            $app->close();
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $in = $app->input;
        $recreate = (int)$in->get('recreate', 1) === 1; // always recreate new collection by default
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
            $app->setHeader('Content-Type', 'application/json', true);
            echo json_encode(['error' => 'IONOS token or endpoint not configured']);
            $app->close();
        }
        $http = \Joomla\CMS\Http\HttpFactory::getHttp();
        $headers = [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json', 'Content-Type' => 'application/json' ];
        if ($tokenId !== '') { $headers['X-IONOS-Token-Id'] = $tokenId; }
        $newId = '';
        try {
            $site   = Factory::getApplication()->get('sitename') ?: 'Joomla Site';
            $root   = \Joomla\CMS\Uri\Uri::root();
            $host   = parse_url($root, PHP_URL_HOST) ?: 'localhost';
            $name   = 'bears-aichatbot-' . preg_replace('/[^a-z0-9-]/i', '-', $host) . '-' . date('YmdHis');
            $payload = [ 'name' => $name, 'description' => 'Rebuilt by Bears AI Chatbot dashboard' ];
            $resp = $http->post($apiBase . '/document-collections', json_encode($payload), $headers, 30);
            if ($resp->code >= 200 && $resp->code < 300) {
                $data = json_decode((string)$resp->body, true);
                $newId = (string)($data['id'] ?? $data['collection_id'] ?? '');
            } else {
                throw new \RuntimeException('Create failed: HTTP ' . $resp->code);
            }
        } catch (\Throwable $e) {
            $app->setHeader('Content-Type', 'application/json', true);
            echo json_encode(['error' => 'Create collection failed: ' . $e->getMessage()]);
            $app->close();
        }
        if ($newId === '') {
            $app->setHeader('Content-Type', 'application/json', true);
            echo json_encode(['error' => 'Create collection returned no id']);
            $app->close();
        }
        // Persist new collection id
        try {
            $upd = $db->getQuery(true)
                ->update($db->quoteName('#__aichatbot_state'))
                ->set($db->quoteName('collection_id') . ' = ' . $db->quote($newId))
                ->where($db->quoteName('id') . ' = 1');
            $db->setQuery($upd)->execute();
        } catch (\Throwable $e) {}

        // Clear mapping table and enqueue upsert jobs for all currently published content
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

        $app->setHeader('Content-Type', 'application/json', true);
        echo json_encode(['data' => ['collection_id' => $newId, 'enqueued' => $enqueued]]);
        $app->close();
    }

    public function latencyJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $app->setHeader('status', 403, true);
            echo json_encode(['error' => 'Forbidden']);
            $app->close();
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
        $app->setHeader('Content-Type', 'application/json', true);
        echo json_encode(['data' => $rows]);
        $app->close();
    }

    public function histTokensJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $app->setHeader('status', 403, true);
            echo json_encode(['error' => 'Forbidden']);
            $app->close();
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
            ->select('COUNT(*) AS count')
            ->from($db->quoteName('#__aichatbot_usage'))
            ->group('bucket')
            ->order('MIN(total_tokens) ASC');
        if ($from !== '') { $q->where($db->quoteName('created_at') . ' >= ' . $db->quote($from)); }
        if ($to !== '') { $q->where($db->quoteName('created_at') . ' <= ' . $db->quote($to)); }
        if ($moduleId) { $q->where($db->quoteName('module_id') . ' = ' . (int)$moduleId); }
        if ($model !== '') { $q->where($db->quoteName('model') . ' = ' . $db->quote($model)); }
        if ($collectionId !== '') { $q->where($db->quoteName('collection_id') . ' = ' . $db->quote($collectionId)); }
        $db->setQuery($q); $rows = (array)$db->loadAssocList();
        $app->setHeader('Content-Type', 'application/json', true);
        echo json_encode(['data' => $rows]);
        $app->close();
    }

    public function outcomesJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $app->setHeader('status', 403, true);
            echo json_encode(['error' => 'Forbidden']);
            $app->close();
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
        $app->setHeader('Content-Type', 'application/json', true);
        echo json_encode(['data' => $rows]);
        $app->close();
    }

    public function collectionMetaJson()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $app->setHeader('status', 403, true);
            echo json_encode(['error' => 'Forbidden']);
            $app->close();
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
            $app->setHeader('Content-Type', 'application/json', true);
            echo json_encode(['error' => 'No collection_id configured']);
            $app->close();
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
            $app->setHeader('Content-Type', 'application/json', true);
            echo json_encode(['error' => 'IONOS token or endpoint not configured']);
            $app->close();
        }
        try {
            $http = \Joomla\CMS\Http\HttpFactory::getHttp();
            $url = $apiBase . '/document-collections/' . rawurlencode($collectionId);
            $headers = [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ];
            if ($tokenId !== '') { $headers['X-IONOS-Token-Id'] = $tokenId; }
            $resp = $http->get($url, $headers, 30);
            if ($resp->code >= 200 && $resp->code < 300) {
                $data = json_decode((string)$resp->body, true);
                $app->setHeader('Content-Type', 'application/json', true);
                echo json_encode(['data' => $data]);
                $app->close();
            }
            $app->setHeader('Content-Type', 'application/json', true);
            echo json_encode(['error' => 'HTTP ' . $resp->code, 'body' => mb_substr((string)$resp->body, 0, 2000)]);
            $app->close();
        } catch (\Throwable $e) {
            $app->setHeader('Content-Type', 'application/json', true);
            echo json_encode(['error' => $e->getMessage()]);
            $app->close();
        }
    }

    public function usageCsv()
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
            $app->setHeader('status', 403, true);
            echo 'Forbidden';
            $app->close();
        }

        // CSRF check
        if (!\Joomla\CMS\Session\Session::checkToken('get')) {
            $app->setHeader('status', 403, true);
            echo 'Invalid token';
            $app->close();
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
