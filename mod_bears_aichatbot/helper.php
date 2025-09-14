<?php
/**
 * mod_bears_aichatbot - AI Knowledgebase Chatbot for Joomla 5
 * Helper functions
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Http\HttpFactory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class ModBearsAichatbotHelper
{
    protected static $lastContextStats = [];
    /**
     * AJAX endpoint: Answer a user question using IONOS Model Hub with knowledge from selected categories.
     * Route example (via com_ajax):
     *   index.php?option=com_ajax&module=bears_aichatbot&method=ask&format=json&module_id=123
     * Input parameters: message (string), module_id (int)
     *
     * @return array
     */
    public static function askAjax()
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        $moduleId = $input->getInt('module_id');
        if (!$moduleId) {
            return ['success' => false, 'error' => 'Missing module_id'];
        }

        $module = ModuleHelper::getModuleById($moduleId);
        if (!$module || !isset($module->params)) {
            return ['success' => false, 'error' => 'Module not found'];
        }
        $params = new Registry($module->params);

        $message = trim($input->getString('message', ''));
        if ($message === '') {
            return ['success' => false, 'error' => 'Empty message'];
        }

        // Build knowledge base context (will try Document Collection retrieval later)
        $strict = true;
        $kbStats = [];
        $context = '';
        
        // Read collection ID from centralized state table
        $collectionId = '';
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $q  = $db->getQuery(true)
                ->select($db->quoteName('collection_id'))
                ->from($db->quoteName('#__aichatbot_state'))
                ->where($db->quoteName('id') . ' = 1')
                ->setLimit(1);
            $db->setQuery($q);
            $collectionId = (string)($db->loadResult() ?? '');
        } catch (\Throwable $e) { /* ignore */ }
        $topK = (int)$params->get('retrieval_top_k', 6);
        $minScore = (float)$params->get('retrieval_min_score', 0.2);
        if ($topK < 1) { $topK = 6; }

        // IONOS configuration (read from module params defined in XML)
        $tokenId = trim((string) $params->get('ionos_token_id', ''));
        $token   = trim((string) $params->get('ionos_token', ''));
        // Allow custom model and endpoint via module settings
        $model   = trim((string) $params->get('ionos_model', ''));
        $endpoint = trim((string) $params->get('ionos_endpoint', 'https://openai.inference.de-txl.ionos.com/v1/chat/completions'));

        // If params seem empty, try a direct DB fetch of the module params by id as a fallback
        if ($tokenId === '' || $token === '' || $model === '') {
            try {
                $db = Factory::getContainer()->get('DatabaseDriver');
                $q  = $db->getQuery(true)
                    ->select($db->quoteName('params'))
                    ->from($db->quoteName('#__modules'))
                    ->where($db->quoteName('id') . ' = ' . (int) $moduleId)
                    ->setLimit(1);
                $db->setQuery($q);
                $rawParams = (string) $db->loadResult();
                if ($rawParams !== '') {
                    $p2 = new Registry($rawParams);
                    if ($tokenId === '') { $tokenId = trim((string) $p2->get('ionos_token_id', '')); }
                    if ($token === '')   { $token   = trim((string) $p2->get('ionos_token', '')); }
                    if ($model === '')   { $model   = trim((string) $p2->get('ionos_model', '')); }
                }
            } catch (\Throwable $e) {
                // ignore fallback errors; will report missing below
            }
        }

        // Build a detailed missing-list for easier diagnosis (Bearer token and model required)
        $missing = [];
        if ($token === '')   { $missing[] = 'Token'; }
        if ($model === '')   { $missing[] = 'Model'; }
        if (!empty($missing)) {
            return ['success' => false, 'error' => 'Missing: ' . implode(', ', $missing)];
        }

        // Get site URL for better link generation
        $siteUrl = \Joomla\CMS\Uri\Uri::root();
        
        // Build local knowledge context (Articles + Additional URLs + Kunena)
        $localContext = self::buildKnowledgeContext($params, $message, $strict);
        $kbStats = self::$lastContextStats;
        if ($localContext !== '' && stripos($localContext, 'No knowledge available') === false) {
            $context = "Knowledge from Joomla Articles, Kunena and URLs:\n\n" . $localContext;
        }
        
        // Auto-create a document collection on first use if missing but credentials are present
        if ($collectionId === '' && $token !== '') {
            try {
                // Use the correct IONOS Inference API endpoint for document collections
                // Based on collections.ipynb: https://inference.de-txl.ionos.com/collections
                $apiBase = 'https://inference.de-txl.ionos.com';

                $site   = Factory::getApplication()->get('sitename') ?: 'Joomla Site';
                $root   = \Joomla\CMS\Uri\Uri::root();
                $host   = parse_url($root, PHP_URL_HOST) ?: 'localhost';
                $name   = 'bears-aichatbot-' . preg_replace('/[^a-z0-9-]/i', '-', $host) . '-' . date('YmdHis');
                $payload = [ 
                    'properties' => [
                        'name' => $name, 
                        'description' => 'Auto-created by Bears AI Chatbot for ' . $site . ' (' . $root . ')',
                        'chunking' => [
                            'enabled' => true,
                            'strategy' => [
                                'config' => [
                                    'chunk_overlap' => 50,
                                    'chunk_size' => 512
                                ]
                            ]
                        ],
                        'embedding' => [
                            'model' => 'BAAI/bge-large-en-v1.5'
                        ],
                        'engine' => [
                            'db_type' => 'pgvector'
                        ]
                    ]
                ];
                $headers = [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json', 'Content-Type' => 'application/json' ];

                $http = HttpFactory::getHttp();
                $resp = $http->post($apiBase . '/collections', json_encode($payload), $headers, 30);
                if ($resp->code >= 200 && $resp->code < 300) {
                    $data = json_decode((string)$resp->body, true);
                    $newId = (string)($data['id'] ?? '');
                    if ($newId !== '') {
                        // Persist operational state to centralized table only
                        try {
                            $db = Factory::getContainer()->get('DatabaseDriver');
                            $upd = $db->getQuery(true)
                                ->update($db->quoteName('#__aichatbot_state'))
                                ->set($db->quoteName('collection_id') . ' = ' . $db->quote($newId))
                                ->where($db->quoteName('id') . ' = 1');
                            $db->setQuery($upd)->execute();
                        } catch (\Throwable $e) { /* ignore state save error */ }
                        // Use in this request
                        $collectionId = $newId;
                    }
                }
            } catch (\Throwable $e) {
                // Silent failure; collection can be created later by Scheduler
            }
        }
        
        // Try Document Collection retrieval if configured and no context yet
        if ($context === '' && $collectionId !== '' && $token !== '') {
            // Use the correct IONOS Inference API endpoint for document collections
            // Based on collections.ipynb: https://inference.de-txl.ionos.com/collections
            $apiBase = 'https://inference.de-txl.ionos.com';
            try {
                $http = HttpFactory::getHttp();
                $url = rtrim($apiBase, '/') . '/collections/' . rawurlencode($collectionId) . '/query';
                $payload = [ 'query' => $message, 'limit' => $topK ];
                $headers = [ 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json', 'Content-Type' => 'application/json' ];
                $resp = $http->post($url, json_encode($payload), $headers, 30);
                if ($resp->code >= 200 && $resp->code < 300) {
                    $data = json_decode((string)$resp->body, true);

                    // Handle IONOS Inference API response format based on collections.ipynb
                    $candidates = [];
                    if (isset($data['properties']['matches']) && is_array($data['properties']['matches'])) {
                        $matches = $data['properties']['matches'];
                        foreach ($matches as $match) {
                            if (isset($match['document']['properties']['content'])) {
                                // Decode base64 content
                                $content = base64_decode($match['document']['properties']['content']);
                                $name = $match['document']['properties']['name'] ?? '';
                                $score = $match['score'] ?? null;
                                
                                $candidates[] = [
                                    'text' => $content,
                                    'metadata' => ['name' => $name],
                                    'score' => is_numeric($score) ? (float)$score : null
                                ];
                            }
                        }
                    }

                    // Client-side filter/sort according to minScore and topK if score is available
                    if (!empty($candidates)) {
                        // Filter by score if present
                        $hasScores = array_reduce($candidates, function($carry, $it){ return $carry || ($it['score'] !== null); }, false);
                        if ($hasScores) {
                            $candidates = array_values(array_filter($candidates, function($it) use ($minScore){ return $it['score'] === null ? true : ($it['score'] >= $minScore); }));
                            usort($candidates, function($a, $b){ return ($b['score'] <=> $a['score']); });
                        }
                        // Trim to topK
                        if (count($candidates) > $topK) { $candidates = array_slice($candidates, 0, $topK); }

                        $parts = [];
                        $retrievedTopScore = null;
                        foreach ($candidates as $it) {
                            $meta = (array)$it['metadata'];
                            $source = (string)($meta['name'] ?? $meta['source'] ?? $meta['title'] ?? '');
                            $label = $source !== '' ? ('Source: ' . $source) : '';
                            $snippet = mb_substr((string)$it['text'], 0, 1500);
                            $parts[] = ($label !== '' ? ($label . "\n") : '') . $snippet;
                            if (isset($it['score']) && is_numeric($it['score'])) {
                                $s = (float)$it['score'];
                                if ($retrievedTopScore === null || $s > $retrievedTopScore) { $retrievedTopScore = $s; }
                            }
                        }
                        $added = count($parts);
                        if (!empty($parts)) {
                            $docCtx = "Relevant knowledge snippets from the document collection:\n\n" . implode("\n\n---\n\n", $parts);
                            if ($context !== '') {
                                $context .= "\n\n---\n\n" . $docCtx;
                            } else {
                                $context = $docCtx;
                            }
                            $kbStats = array_merge($kbStats, [ 'doc_collection' => $collectionId, 'retrieved' => $added, 'retrieved_top_score' => $retrievedTopScore ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore retrieval failure and fallback
            }
        }

        // Build sitemap if enabled - but prioritize knowledge base content over sitemap links
        $sitemapInfo = '';
        if ((int) $params->get('include_sitemap', 0) === 1) {
            $sitemapUrl = trim((string) $params->get('sitemap_url', ''));
            
            // Preferred: use external sitemap when URL provided; Fallback: use menu-based sitemap
            if ($sitemapUrl !== '') {
                $sitemap = self::fetchExternalSitemap($sitemapUrl);
                if ($sitemap === '') {
                    // External sitemap invalid/unavailable, fallback to menu-based sitemap
                    $sitemap = self::buildSitemap();
                }
            } else {
                // No URL provided: use menu-based sitemap
                $sitemap = self::buildSitemap();
            }
            
            if (!empty($sitemap)) {
                $sitemapInfo = "\n\nSITE STRUCTURE (for reference only - prioritize knowledge base content):\n" . $sitemap . "\n";
            }
        }
        
        // If strict and no relevant KB found, refuse without calling the model
        $hasKb = (($kbStats['article_count'] ?? 0)
            + ($kbStats['kunena_count'] ?? 0)
            + ($kbStats['url_count'] ?? 0)
            + ($kbStats['retrieved'] ?? 0)) > 0;
        if ($strict && (!$hasKb || stripos($context, 'No knowledge available') !== false)) {
            return ['success' => true, 'answer' => "I don't know based on the provided dataset.", 'kb' => $kbStats];
        }

        // System prompt includes the KB context from Joomla articles
        $systemPrompt = ($strict ? (
            "You are a knowledge base assistant for this Joomla site. Answer using ONLY the content inside <kb>. If the information is not fully supported by <kb>, respond exactly: 'I don't know based on the provided dataset.' Do not use prior knowledge, do not browse the web, and do not guess.\n\n"
            . "IMPORTANT INSTRUCTIONS:\n"
            . "1. Use only the <kb> content for facts and instructions.\n"
            . "2. PRIORITIZE answering from the knowledge base content over providing links.\n"
            . "3. Only provide links if they are specifically mentioned in the <kb> content or if the user explicitly asks for page references.\n"
            . "4. The website URL is: " . $siteUrl . "\n"
            . "5. Format links as clickable Markdown: [Link Text](URL) only when necessary.\n"
            . "6. Focus on providing helpful information from the knowledge base rather than directing users to pages.\n"
        ) : (
            "You are a helpful AI assistant for this Joomla site. Use ONLY the provided knowledge base context when possible. If the context lacks the answer, say you don't know and suggest related topics.\n\n"
            . "IMPORTANT INSTRUCTIONS:\n"
            . "1. PRIORITIZE answering from the knowledge base content over providing links.\n"
            . "2. Only provide links if they are specifically mentioned in the knowledge base or if the user explicitly asks for page references.\n"
            . "3. The website URL is: " . $siteUrl . "\n"
            . "4. Format links as clickable Markdown: [Link Text](URL) only when necessary.\n"
            . "5. Focus on providing helpful information from the knowledge base rather than directing users to pages.\n"
        ));
        $systemPrompt .= $sitemapInfo . "\nKnowledge base context follows between <kb> tags.\n<kb>" . $context . '</kb>';

        $payload = [
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $message],
            ],
            'max_tokens'  => 512,
            'temperature' => $strict ? 0.0 : 0.2,
        ];

        // OpenAI-compatible chat completions endpoint (IONOS Model Hub)
        $url = $endpoint !== '' ? $endpoint : 'https://openai.inference.de-txl.ionos.com/v1/chat/completions';

        try {
            $http = HttpFactory::getHttp();
            $headers = [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ];

            $requestBody = json_encode($payload);
            $t0 = microtime(true);
            $response = $http->post($url, $requestBody, $headers);
            $durationMs = (int) round((microtime(true) - $t0) * 1000);
            $reqBytes = strlen($requestBody ?? '');
            $resBytes = strlen($response->body ?? '');

            if ($response->code < 200 || $response->code >= 300) {
                $respBody = '';
                try {
                    if (method_exists($response, 'getBody')) {
                        $respBody = (string) $response->getBody();
                    } elseif (isset($response->body)) {
                        $respBody = (string) $response->body;
                    }
                } catch (\Throwable $ignore) {}
                $detail = '';
                if ($respBody !== '') {
                    $errJson = json_decode($respBody, true);
                    if (is_array($errJson)) {
                        if (isset($errJson['error'])) {
                            // Could be string or object with message
                            if (is_array($errJson['error']) && isset($errJson['error']['message'])) {
                                $detail = (string) $errJson['error']['message'];
                            } elseif (is_string($errJson['error'])) {
                                $detail = $errJson['error'];
                            }
                        } elseif (isset($errJson['message'])) {
                            $detail = (string) $errJson['message'];
                        } elseif (isset($errJson['detail'])) {
                            $detail = (string) $errJson['detail'];
                        }
                    }
                }
                $errMsg = 'IONOS request failed (status ' . $response->code . ')';
                if ($detail !== '') {
                    $errMsg .= ': ' . $detail;
                }
                return [
                    'success'  => false,
                    'error'    => $errMsg,
                    'status'   => $response->code,
                    'endpoint' => $url,
                    'model'    => $model,
                    'body'     => $respBody !== '' ? mb_substr($respBody, 0, 2000) : null,
                ];
            }

            $body = json_decode($response->body, true);
            if (!is_array($body) || empty($body['choices'][0]['message']['content'])) {
                return ['success' => false, 'error' => 'Unexpected response from IONOS'];
            }

            $answer = trim((string) $body['choices'][0]['message']['content']);

            // Log token usage metrics if available
            try {
                $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];
                // Detect outcome: answered/refused
                $ansLower = mb_strtolower($answer);
                $outcome = (strpos($ansLower, "i don't know based on the provided dataset") !== false) ? 'refused' : 'answered';
                // If status >= 400 treat as error
                if ((int)$response->code >= 400) { $outcome = 'error'; }

                $topScore = null;
                if (isset($kbStats['retrieved_top_score']) && is_numeric($kbStats['retrieved_top_score'])) {
                    $topScore = (float)$kbStats['retrieved_top_score'];
                }
                self::logUsageExtended(
                    (int)$moduleId,
                    (string)$model,
                    (string)$endpoint,
                    (string)$collectionId,
                    (string)$message,
                    (string)$answer,
                    (array)$usage,
                    (array)$kbStats,
                    (int)$response->code,
                    (int)$durationMs,
                    (int)$reqBytes,
                    (int)$resBytes,
                    (string)$outcome,
                    $topScore
                );
                
                // Track keywords for this interaction
                $totalTokens = (int)($usage['total_tokens'] ?? $usage['totalTokens'] ?? 0);
                self::updateKeywordStats($message, $totalTokens, $outcome, $params);
                
            } catch (\Throwable $ignore) {}

            return [
                'success' => true,
                'answer'  => $answer,
                'kb'      => $kbStats,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'kb' => $kbStats ?? null];
        }
    }

    /**
     * Build a compact knowledge context string from selected Joomla content categories
     * and optionally Kunena forum content when enabled.
     *
     * @param Registry $params
     * @return string
     */
    protected static function buildKnowledgeContext(Registry $params, string $userMessage, bool $strict = true): string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Stats holder
        self::$lastContextStats = [
            'article_count' => 0,
            'kunena_count'  => 0,
            'url_count'     => 0,
            'article_titles'=> [],
            'kunena_titles' => [],
            'urls'          => [],
            'note'          => '',
        ];
        $hadLikes = false;

        // Budget for context to avoid exceeding token limits
        $maxTotal = 30000;
        $contextParts = [];
        $total = 0;

        // Article fetch limit (configurable, default 500)
        $limit = (int) $params->get('article_limit', 500);
        if ($limit < 1) { $limit = 500; }

        // Additional knowledge URLs
        $extraUrls = trim((string) $params->get('additional_urls', ''));
        if ($extraUrls !== '') {
            $urls = preg_split('/\r?\n/', $extraUrls);
            $urls = array_map('trim', $urls);
            $urls = array_filter($urls, function ($u) { return $u !== '' && preg_match('#^https?://#i', $u); });
            if (!empty($urls)) {
                $http = HttpFactory::getHttp();
                foreach ($urls as $u) {
                    try {
                        $resp = $http->get($u, [ 'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' ]);
                        if ($resp->code >= 200 && $resp->code < 300) {
                            $body = (string) $resp->body;
                            // Basic HTML to text
                            $text = strip_tags($body);
                            $text = preg_replace('/\s+/', ' ', $text);
                            $snippet = mb_substr($text, 0, 1000);
                            $part = 'URL: ' . $u . "\n" . 'Content:' . "\n" . $text;
                            $len = mb_strlen($part);
                            if ($total + $len <= $maxTotal) {
                                $contextParts[] = $part;
                                $total += $len;
                                self::$lastContextStats['urls'][] = $u;
                                self::$lastContextStats['url_count']++;
                            }
                        } else {
                            self::$lastContextStats['note'] = trim(self::$lastContextStats['note'] . ' URL ' . $u . ' HTTP ' . $resp->code);
                        }
                    } catch (\Throwable $e) {
                        self::$lastContextStats['note'] = trim(self::$lastContextStats['note'] . ' URL ' . $u . ' error: ' . $e->getMessage());
                    }
                }
            }
        }

        // Joomla Articles by selected categories
        $catIds = $params->get('content_categories', []);
        if (is_string($catIds)) {
            $catIds = array_filter(array_map('intval', explode(',', $catIds)));
        } elseif (is_array($catIds)) {
            $catIds = array_map('intval', $catIds);
        } else {
            $catIds = [];
        }

        $items = [];
        if (!empty($catIds)) {
            // Expand to include descendant categories
            $allCatIds = [];
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
                        $ors[] = '(' . $db->quoteName('lft') . ' >= ' . (int) $r['lft'] . ' AND ' . $db->quoteName('rgt') . ' <= ' . (int) $r['rgt'] . ')';
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
            if (empty($allCatIds)) { $allCatIds = $catIds; }

            // Base query within selected categories (and descendants)
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'introtext', 'fulltext']))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('catid') . ' IN (' . implode(',', $allCatIds) . ')');

            // Apply simple keyword relevance if user provided a message
            $userMessage = trim($userMessage);
            if ($userMessage !== '') {
                $terms = preg_split('/\s+/', mb_strtolower($userMessage));
                $likes = [];
                $maxTerms = 5;
                foreach ($terms as $t) {
                    $t = trim($t);
                    if (mb_strlen($t) < 3) continue;
                    $kw = $db->escape($t, true);
                    $like = $db->quote('%' . $kw . '%', false);
                    $likes[] = '(' . $db->quoteName('title') . ' LIKE ' . $like . ' OR ' . $db->quoteName('introtext') . ' LIKE ' . $like . ' OR ' . $db->quoteName('fulltext') . ' LIKE ' . $like . ')';
                    if (count($likes) >= $maxTerms) break;
                }
                $hadLikes = !empty($likes);
                if ($hadLikes) {
                    $query->where('(' . implode(' OR ', $likes) . ')');
                }
            }

            $query->order($db->escape('modified DESC, created DESC'));

            $db->setQuery($query, 0, $limit);
            $items = (array) $db->loadAssocList();

            // In strict mode, if we had no usable keywords, treat as no relevant matches
            if ($strict && !$hadLikes) {
                $items = [];
            }

            // Fallback to recent items if no keyword matches (disabled in strict mode)
            if (!$items && !$strict) {
                $query = $db->getQuery(true)
                    ->select($db->quoteName(['id', 'title', 'introtext', 'fulltext']))
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->where($db->quoteName('catid') . ' IN (' . implode(',', $allCatIds) . ')')
                    ->order($db->escape('modified DESC, created DESC'));
                $db->setQuery($query, 0, $limit);
                $items = (array) $db->loadAssocList();
                if (!$items) {
                    self::$lastContextStats['note'] = 'No matches in selected categories; falling back to site-wide recent articles.';
                }
            }
        }

        // If no categories are selected or no items found, do a site-wide search
        if (empty($items)) {
            if ($strict) {
                // Site-wide keyword-filtered search in strict mode
                $qAll = $db->getQuery(true)
                    ->select($db->quoteName(['id', 'title', 'introtext', 'fulltext']))
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('state') . ' = 1');

                $terms = preg_split('/\s+/', mb_strtolower(trim($userMessage)));
                $likes = [];
                $maxTerms = 5;
                if (!empty($terms)) {
                    foreach ($terms as $t) {
                        $t = trim($t);
                        if (mb_strlen($t) < 3) continue;
                        $kw = $db->escape($t, true);
                        $like = $db->quote('%' . $kw . '%', false);
                        $likes[] = '(' . $db->quoteName('title') . ' LIKE ' . $like . ' OR ' . $db->quoteName('introtext') . ' LIKE ' . $like . ' OR ' . $db->quoteName('fulltext') . ' LIKE ' . $like . ')';
                        if (count($likes) >= $maxTerms) break;
                    }
                }
                if (!empty($likes)) {
                    $qAll->where('(' . implode(' OR ', $likes) . ')');
                    $qAll->order($db->escape('modified DESC, created DESC'));
                    $db->setQuery($qAll, 0, $limit);
                    $items = (array) $db->loadAssocList();
                } else {
                    // No usable keywords; leave items empty to trigger refusal
                    $items = [];
                }
            } else {
                $qAll = $db->getQuery(true)
                    ->select($db->quoteName(['id', 'title', 'introtext', 'fulltext']))
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->order($db->escape('modified DESC, created DESC'));
                $db->setQuery($qAll, 0, $limit);
                $items = (array) $db->loadAssocList();
            }
        }

        if ($items) {
            foreach ($items as $row) {
                $content = strip_tags((string)($row['introtext'] ?? '') . "\n" . (string)($row['fulltext'] ?? ''));
                $content = preg_replace('/\s+/', ' ', $content);
                $snippet = mb_substr($content, 0, 600);
                $part = 'Title: ' . $row['title'] . "\n" . 'Content:' . "\n" . $content;

                $len = mb_strlen($part);
                if ($total + $len > $maxTotal) {
                    break;
                }
                $contextParts[] = $part;
                $total += $len;
                if (count(self::$lastContextStats['article_titles']) < 5) {
                    self::$lastContextStats['article_titles'][] = (string) $row['title'];
                }
                self::$lastContextStats['article_count']++;
            }
        }

        // Kunena forum content when enabled
        $useKunena = (int) $params->get('use_kunena', 1) === 1;
        if ($useKunena && $total < $maxTotal) {
            try {
                $kquery = $db->getQuery(true)
                    ->select($db->quoteName(['m.id', 'm.subject']))
                    ->select($db->quoteName('mt.message'))
                    ->from($db->quoteName('#__kunena_messages', 'm'))
                    ->join('INNER', $db->quoteName('#__kunena_messages_text', 'mt') . ' ON ' . $db->quoteName('mt.mesid') . ' = ' . $db->quoteName('m.id'))
                    ->join('INNER', $db->quoteName('#__kunena_categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('m.catid'))
                    ->where($db->quoteName('c.published') . ' = 1')
                    ->where($db->quoteName('m.hold') . ' = 0')
                    ->order($db->escape('m.time DESC'));

                $db->setQuery($kquery, 0, min($limit, 100));
                $kitems = $db->loadAssocList();

                if ($kitems) {
                    foreach ($kitems as $row) {
                        $content = strip_tags((string)($row['message'] ?? ''));
                        $content = preg_replace('/\s+/', ' ', $content);
                        $snippet = mb_substr($content, 0, 600);
                        $subject = trim((string)($row['subject'] ?? 'Forum Post'));
                        $part = 'Forum: ' . ($subject !== '' ? $subject : 'Forum Post') . "\n" . 'Content: ' . $snippet;

                        $len = mb_strlen($part);
                        if ($total + $len > $maxTotal) {
                            break;
                        }
                        $contextParts[] = $part;
                        $total += $len;
                        if (count(self::$lastContextStats['kunena_titles']) < 5) {
                            self::$lastContextStats['kunena_titles'][] = $subject !== '' ? $subject : 'Forum Post';
                        }
                        self::$lastContextStats['kunena_count']++;
                    }
                }
            } catch (\Throwable $e) {
                // Kunena likely not installed or tables missing; ignore silently
            }
        }



        if (empty($contextParts)) {
            self::$lastContextStats['note'] = self::$lastContextStats['note'] ?: 'No knowledge available from selected sources.';
            return 'No knowledge available from selected sources.';
        }

        return implode("\n---\n", $contextParts);
    }
    
    /**
     * Build a sitemap of the site's menu structure
     * 
     * @return string
     */
    protected static function buildSitemap(): string
    {
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $app = Factory::getApplication();
            $siteUrl = \Joomla\CMS\Uri\Uri::root();
            
            // Get all published menu items
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'alias', 'path', 'link', 'type', 'parent_id', 'level']))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('client_id') . ' = 0') // Site menus only
                ->where($db->quoteName('type') . ' != ' . $db->quote('separator'))
                ->where($db->quoteName('type') . ' != ' . $db->quote('heading'))
                ->order($db->quoteName('lft'));
            
            $db->setQuery($query);
            $items = $db->loadObjectList();
            
            if (empty($items)) {
                return '';
            }
            
            $sitemap = [];
            $router = $app->getRouter();
            
            foreach ($items as $item) {
                // Skip system items
                if ($item->type === 'alias' || $item->type === 'url') {
                    continue;
                }
                
                // Build the URL
                $url = '';
                if ($item->type === 'component') {
                    // Parse the link
                    $uri = new \Joomla\CMS\Uri\Uri($item->link);
                    $uri->setVar('Itemid', $item->id);
                    
                    // Use the path for SEF URLs
                    if ($item->path && $item->path !== '') {
                        $url = $siteUrl . $item->path;
                    } else {
                        // Fallback to building from link
                        $url = $siteUrl . 'index.php?' . $uri->getQuery();
                    }
                }
                
                if ($url !== '') {
                    // Add indentation based on level
                    $indent = str_repeat('  ', $item->level - 1);
                    $sitemap[] = $indent . '- ' . $item->title . ': ' . $url;
                }
            }
            
            // Also add common article categories as potential URLs
            $catQuery = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'alias', 'path']))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('level') . ' > 0')
                ->order($db->quoteName('lft'));
            
            $db->setQuery($catQuery);
            $categories = $db->loadObjectList();
            
            if (!empty($categories)) {
                $sitemap[] = "\nContent Categories:";
                foreach ($categories as $cat) {
                    if ($cat->path && $cat->path !== 'uncategorised') {
                        $url = $siteUrl . $cat->path;
                        $sitemap[] = '  - ' . $cat->title . ': ' . $url;
                    }
                }
            }
            
            return implode("\n", $sitemap);
            
        } catch (\Throwable $e) {
            // Return empty string if sitemap generation fails
            return '';
        }
    }
    
    /**
     * Fetch and parse an external sitemap (HTML or XML format)
     * 
     * @param string $sitemapUrl
     * @return string
     */
    protected static function fetchExternalSitemap(string $sitemapUrl): string
    {
        try {
            $http = HttpFactory::getHttp();
            
            // First try to fetch the sitemap
            $response = $http->get($sitemapUrl, ['Accept' => 'text/html, application/xml, text/xml, */*']);
            
            if ($response->code < 200 || $response->code >= 300) {
                return '';
            }
            
            // Check if it's XML or HTML
            $body = $response->body;
            $isXml = (strpos($body, '<?xml') !== false || strpos($body, '<urlset') !== false || strpos($body, '<sitemapindex') !== false);
            
            if ($isXml) {
                // Parse as XML sitemap
                return self::parseXmlSitemap($body);
            } else {
                // Parse as HTML sitemap
                return self::parseHtmlSitemap($body);
            }
            
        } catch (\Throwable $e) {
            return '';
        }
    }
    
    /**
     * Parse an HTML sitemap page (supports OSMap and similar structures)
     * 
     * @param string $html
     * @return string
     */
    protected static function parseHtmlSitemap(string $html): string
    {
        try {
            $sitemap = [];
            $urlCount = 0;
            $maxUrls = 150; // Increased limit for comprehensive sitemaps
            
            // Use DOMDocument to parse HTML
            $dom = new \DOMDocument();
            @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $xpath = new \DOMXPath($dom);
            
            // Look for OSMap structure first (more specific)
            $osmapLinks = $xpath->query('//a[@class="osmap-link"]');
            
            // If no OSMap links found, fall back to all links
            $links = $osmapLinks->length > 0 ? $osmapLinks : $xpath->query('//a[@href]');
            
            if ($links->length === 0) {
                return '';
            }
            
            $baseUrl = \Joomla\CMS\Uri\Uri::root();
            $processedUrls = [];
            
            foreach ($links as $link) {
                if ($urlCount >= $maxUrls) break;
                
                $href = $link->getAttribute('href');
                $text = trim($link->textContent);
                
                // Skip empty links, anchors, or javascript
                if (empty($href) || $href === '#' || empty($text) || strpos($href, 'javascript:') === 0) {
                    continue;
                }
                
                // Skip modal triggers and special actions
                if ($link->hasAttribute('data-bs-toggle') || $link->hasAttribute('data-toggle')) {
                    continue;
                }
                
                // Make absolute URL if relative
                if (strpos($href, 'http') !== 0) {
                    if (strpos($href, '/') === 0) {
                        // Absolute path
                        $parsedBase = parse_url($baseUrl);
                        $href = $parsedBase['scheme'] . '://' . $parsedBase['host'] . $href;
                    } else {
                        // Relative path
                        $href = rtrim($baseUrl, '/') . '/' . $href;
                    }
                }
                
                // Skip if already processed
                if (in_array($href, $processedUrls)) {
                    continue;
                }
                
                // Skip external links (not from same domain)
                $parsedUrl = parse_url($href);
                $parsedBase = parse_url($baseUrl);
                if (isset($parsedUrl['host']) && isset($parsedBase['host'])) {
                    if ($parsedUrl['host'] !== $parsedBase['host']) {
                        continue;
                    }
                }
                
                // Skip certain file types
                if (preg_match('/\.(pdf|doc|docx|xls|xlsx|zip|rar|jpg|jpeg|png|gif|mp3|mp4)$/i', $href)) {
                    continue;
                }
                
                // Get hierarchy level for better organization (OSMap specific)
                $level = 0;
                $parent = $link->parentNode;
                while ($parent) {
                    if ($parent->nodeName === 'ul' && $parent->hasAttribute('class')) {
                        $class = $parent->getAttribute('class');
                        if (preg_match('/level_(\d+)/', $class, $matches)) {
                            $level = (int) $matches[1];
                            break;
                        }
                    }
                    $parent = $parent->parentNode;
                }
                
                // Add indentation based on level
                $indent = str_repeat('  ', $level);
                
                $processedUrls[] = $href;
                $sitemap[] = $indent . '- ' . $text . ': ' . $href;
                $urlCount++;
            }
            
            if (!empty($sitemap)) {
                return implode("\n", $sitemap);
            }
            
            return '';
            
        } catch (\Throwable $e) {
            return '';
        }
    }
    
    /**
     * Parse an XML sitemap
     * 
     * @param string $xmlContent
     * @return string
     */
    protected static function parseXmlSitemap(string $xmlContent): string
    {
        try {
            $xml = simplexml_load_string($xmlContent);
            if (!$xml) {
                return '';
            }
            
            $sitemap = [];
            $urlCount = 0;
            $maxUrls = 100; // Limit to prevent overwhelming the AI
            
            // Handle standard sitemap format
            if (isset($xml->url)) {
                foreach ($xml->url as $url) {
                    if ($urlCount >= $maxUrls) break;
                    
                    $loc = (string) $url->loc;
                    if ($loc === '') continue;
                    
                    // Try to extract a title from the URL path
                    $parsedUrl = parse_url($loc);
                    $path = isset($parsedUrl['path']) ? trim($parsedUrl['path'], '/') : '';
                    
                    if ($path === '' || $path === 'index.php') {
                        $title = 'Home';
                    } else {
                        // Convert path to title (e.g., "about-us" becomes "About Us")
                        $segments = explode('/', $path);
                        $lastSegment = end($segments);
                        $title = ucwords(str_replace(['-', '_'], ' ', $lastSegment));
                    }
                    
                    $sitemap[] = '- ' . $title . ': ' . $loc;
                    $urlCount++;
                }
            }
            
            // Handle sitemap index format (multiple sitemaps)
            if (isset($xml->sitemap)) {
                foreach ($xml->sitemap as $sitemapEntry) {
                    if ($urlCount >= $maxUrls) break;
                    
                    $loc = (string) $sitemapEntry->loc;
                    if ($loc === '') continue;
                    
                    // Fetch sub-sitemap
                    try {
                        $http = HttpFactory::getHttp();
                        $subResponse = $http->get($loc, ['Accept' => 'application/xml, text/xml']);
                        if ($subResponse->code >= 200 && $subResponse->code < 300) {
                            $subXml = simplexml_load_string($subResponse->body);
                            if ($subXml && isset($subXml->url)) {
                                foreach ($subXml->url as $url) {
                                    if ($urlCount >= $maxUrls) break;
                                    
                                    $subLoc = (string) $url->loc;
                                    if ($subLoc === '') continue;
                                    
                                    $parsedUrl = parse_url($subLoc);
                                    $path = isset($parsedUrl['path']) ? trim($parsedUrl['path'], '/') : '';
                                    
                                    if ($path === '' || $path === 'index.php') {
                                        $title = 'Home';
                                    } else {
                                        $segments = explode('/', $path);
                                        $lastSegment = end($segments);
                                        $title = ucwords(str_replace(['-', '_'], ' ', $lastSegment));
                                    }
                                    
                                    $sitemap[] = '- ' . $title . ': ' . $subLoc;
                                    $urlCount++;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // Skip sub-sitemap if it fails
                        continue;
                    }
                }
            }
            
            // If we got some URLs, return them
            if (!empty($sitemap)) {
                return implode("\n", $sitemap);
            }
            
            return '';
            
        } catch (\Throwable $e) {
            return '';
        }
    }

    // Extended logging with latency, sizes, outcome and optional retrieved top score
    protected static function logUsageExtended(int $moduleId, string $model, string $endpoint, string $collectionId, string $message, string $answer, array $usage, array $kbStats, int $statusCode = 0, ?int $durationMs = null, ?int $requestBytes = null, ?int $responseBytes = null, ?string $outcome = null, ?float $retrievedTopScore = null): void
    {
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            // Ensure table exists
            $ddl = "CREATE TABLE IF NOT EXISTS `#__aichatbot_usage` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `module_id` INT DEFAULT NULL,
  `collection_id` VARCHAR(191) DEFAULT NULL,
  `model` VARCHAR(191) DEFAULT NULL,
  `endpoint` VARCHAR(255) DEFAULT NULL,
  `prompt_tokens` INT DEFAULT 0,
  `completion_tokens` INT DEFAULT 0,
  `total_tokens` INT DEFAULT 0,
  `retrieved` INT DEFAULT NULL,
  `article_count` INT DEFAULT 0,
  `kunena_count` INT DEFAULT 0,
  `url_count` INT DEFAULT 0,
  `message_len` INT DEFAULT 0,
  `answer_len` INT DEFAULT 0,
  `status_code` INT DEFAULT NULL,
  `duration_ms` INT DEFAULT NULL,
  `request_bytes` INT DEFAULT NULL,
  `response_bytes` INT DEFAULT NULL,
  `outcome` VARCHAR(20) DEFAULT NULL,
  `retrieved_top_score` DECIMAL(6,4) DEFAULT NULL,
  `price_prompt` DECIMAL(10,6) DEFAULT NULL,
  `price_completion` DECIMAL(10,6) DEFAULT NULL,
  `currency` VARCHAR(8) DEFAULT NULL,
  `estimated_cost` DECIMAL(12,6) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_module_id` (`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $db->setQuery($ddl)->execute();

            $prompt = (int)($usage['prompt_tokens'] ?? $usage['promptTokens'] ?? 0);
            $completion = (int)($usage['completion_tokens'] ?? $usage['completionTokens'] ?? 0);
            $total = (int)($usage['total_tokens'] ?? $usage['totalTokens'] ?? ($prompt + $completion));

            $retrieved = isset($kbStats['retrieved']) ? (int)$kbStats['retrieved'] : null;
            $articleCount = (int)($kbStats['article_count'] ?? 0);
            $kunenaCount = (int)($kbStats['kunena_count'] ?? 0);
            $urlCount = (int)($kbStats['url_count'] ?? 0);

            $msgLen = mb_strlen($message ?? '', 'UTF-8');
            $ansLen = mb_strlen($answer ?? '', 'UTF-8');

            // Pricing for IONOS Model Hub "standard" package (per 1K tokens)
            // Reference: https://cloud.ionos.com/managed/ai-model-hub#prices
            // Defaults; can be overridden by component params later if needed
            $pp = 0.0004; // prompt $/1K
            $pc = 0.0006; // completion $/1K
            $cur = 'USD';
            try {
                // If component params exist, allow override via component configuration later
                $compParams = \Joomla\CMS\Component\ComponentHelper::getParams('com_bears_aichatbot');
                $pp = (float)($compParams->get('price_prompt_standard', $pp));
                $pc = (float)($compParams->get('price_completion_standard', $pc));
                $cur = (string)($compParams->get('price_currency', $cur));
            } catch (\Throwable $ignore) {}
            $est = (($prompt / 1000.0) * $pp) + (($completion / 1000.0) * $pc);

            $q = $db->getQuery(true)
                ->insert($db->quoteName('#__aichatbot_usage'))
                ->columns([
                    $db->quoteName('module_id'),
                    $db->quoteName('collection_id'),
                    $db->quoteName('model'),
                    $db->quoteName('endpoint'),
                    $db->quoteName('prompt_tokens'),
                    $db->quoteName('completion_tokens'),
                    $db->quoteName('total_tokens'),
                    $db->quoteName('retrieved'),
                    $db->quoteName('article_count'),
                    $db->quoteName('kunena_count'),
                    $db->quoteName('url_count'),
                    $db->quoteName('message_len'),
                    $db->quoteName('answer_len'),
                    $db->quoteName('status_code'),
                    $db->quoteName('duration_ms'),
                    $db->quoteName('request_bytes'),
                    $db->quoteName('response_bytes'),
                    $db->quoteName('outcome'),
                    $db->quoteName('retrieved_top_score'),
                    $db->quoteName('price_prompt'),
                    $db->quoteName('price_completion'),
                    $db->quoteName('currency'),
                    $db->quoteName('estimated_cost'),
                ])
                ->values(implode(',', [
                    (int)$moduleId,
                    $db->quote($collectionId !== '' ? $collectionId : null),
                    $db->quote($model),
                    $db->quote($endpoint),
                    (int)$prompt,
                    (int)$completion,
                    (int)$total,
                    $retrieved === null ? 'NULL' : (string)(int)$retrieved,
                    (int)$articleCount,
                    (int)$kunenaCount,
                    (int)$urlCount,
                    (int)$msgLen,
                    (int)$ansLen,
                    (int)$statusCode,
                    $durationMs === null ? 'NULL' : (string)(int)$durationMs,
                    $requestBytes === null ? 'NULL' : (string)(int)$requestBytes,
                    $responseBytes === null ? 'NULL' : (string)(int)$responseBytes,
                    $outcome === null ? 'NULL' : $db->quote($outcome),
                    $retrievedTopScore === null ? 'NULL' : (string)number_format($retrievedTopScore,4,'.',''),
                    (string)$pp,
                    (string)$pc,
                    $db->quote($cur),
                    (string)$est,
                ]));
            $db->setQuery($q)->execute();
        } catch (\Throwable $e) {
            // swallow logging errors
        }
    }

    /**
     * Extract and normalize keywords from a message using configurable settings
     */
    protected static function extractKeywords(string $message, ?Registry $params = null): array
    {
        // Get configuration from module params or use defaults
        $minLength = 3;
        $maxLength = 50;
        $stopWords = [];
        
        if ($params) {
            $minLength = (int)$params->get('keyword_min_length', 3);
            $maxLength = (int)$params->get('keyword_max_length', 50);
            $stopWordsString = trim((string)$params->get('stop_words', ''));
            
            // If no custom stop words configured, use the default from language file
            if ($stopWordsString === '') {
                $stopWordsString = \Joomla\CMS\Language\Text::_('MOD_BEARS_AICHATBOT_STOP_WORDS_DEFAULT');
            }
            
            if ($stopWordsString !== '' && $stopWordsString !== 'MOD_BEARS_AICHATBOT_STOP_WORDS_DEFAULT') {
                $stopWords = array_map('trim', explode(',', mb_strtolower($stopWordsString, 'UTF-8')));
                $stopWords = array_filter($stopWords); // Remove empty strings
            }
        }
        
        // If still no stop words, use minimal fallback
        if (empty($stopWords)) {
            $stopWords = [
                'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
                'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
                'will', 'would', 'could', 'should', 'may', 'might', 'can', 'must', 'shall',
                'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them',
                'my', 'your', 'his', 'her', 'its', 'our', 'their', 'this', 'that', 'these', 'those',
                'what', 'where', 'when', 'why', 'how', 'who', 'which', 'whose', 'whom'
            ];
        }
        
        // Convert to lowercase and remove special characters, but preserve alphanumeric
        $message = mb_strtolower($message, 'UTF-8');
        $message = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $message);
        
        // Split into words
        $words = preg_split('/\s+/', trim($message));
        
        // Filter and process words
        $keywords = [];
        foreach ($words as $word) {
            $word = trim($word);
            
            // Skip empty words
            if ($word === '') {
                continue;
            }
            
            // Check length constraints
            $wordLength = mb_strlen($word, 'UTF-8');
            if ($wordLength < $minLength || $wordLength > $maxLength) {
                continue;
            }
            
            // Skip if it's a stop word
            if (in_array($word, $stopWords)) {
                continue;
            }
            
            // Skip if it's just numbers
            if (is_numeric($word)) {
                continue;
            }
            
            // Skip very common words that might not be in stop words
            if (in_array($word, ['tell', 'know', 'need', 'want', 'get', 'use', 'work', 'make', 'find', 'help'])) {
                continue;
            }
            
            $keywords[] = $word;
        }
        
        // Return unique keywords, limited to top 10 by frequency in this message
        $keywordCounts = array_count_values($keywords);
        arsort($keywordCounts);
        
        return array_slice(array_keys($keywordCounts), 0, 10);
    }

    /**
     * Update keyword statistics based on a chat interaction
     */
    protected static function updateKeywordStats(string $message, int $totalTokens, string $outcome, ?Registry $params = null): void
    {
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            
            // Ensure keywords table exists
            $ddl = "CREATE TABLE IF NOT EXISTS `#__aichatbot_keywords` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `keyword` VARCHAR(100) NOT NULL,
  `usage_count` INT DEFAULT 1,
  `first_used` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `avg_tokens` DECIMAL(8,2) DEFAULT 0,
  `total_tokens` INT DEFAULT 0,
  `success_rate` DECIMAL(5,2) DEFAULT 0,
  `answered_count` INT DEFAULT 0,
  `refused_count` INT DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_keyword` (`keyword`),
  KEY `idx_usage_count` (`usage_count`),
  KEY `idx_last_used` (`last_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $db->setQuery($ddl)->execute();
            error_log('Bears AI Chatbot: Keywords table created/verified successfully');
            
            // Extract keywords from the message using configurable settings
            $keywords = self::extractKeywords($message, $params);
            
            // Get params for debugging
            $minLength = $params ? (int)$params->get('keyword_min_length', 3) : 3;
            $maxLength = $params ? (int)$params->get('keyword_max_length', 50) : 50;
            $stopWordsString = $params ? trim((string)$params->get('stop_words', '')) : '';
            if ($stopWordsString === '') {
                $stopWordsString = \Joomla\CMS\Language\Text::_('MOD_BEARS_AICHATBOT_STOP_WORDS_DEFAULT');
            }
            $stopWordsCount = 0;
            if ($stopWordsString !== '' && $stopWordsString !== 'MOD_BEARS_AICHATBOT_STOP_WORDS_DEFAULT') {
                $stopWords = array_map('trim', explode(',', mb_strtolower($stopWordsString, 'UTF-8')));
                $stopWordsCount = count(array_filter($stopWords));
            }
            
            // Debug logging to help troubleshoot keyword extraction
            error_log('Bears AI Chatbot: Message "' . $message . '" extracted keywords: ' . json_encode($keywords));
            error_log('Bears AI Chatbot: Keyword extraction params - minLength: ' . $minLength . ', maxLength: ' . $maxLength . ', stopWords count: ' . $stopWordsCount);
            
            if (empty($keywords)) {
                error_log('Bears AI Chatbot: No keywords extracted from message: "' . $message . '"');
                // Let's also test the extraction process step by step
                $testMessage = mb_strtolower($message, 'UTF-8');
                $testMessage = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $testMessage);
                $testWords = preg_split('/\s+/', trim($testMessage));
                error_log('Bears AI Chatbot: Test words after processing: ' . json_encode($testWords));
                
                // Test each word against filters
                foreach ($testWords as $word) {
                    $word = trim($word);
                    if ($word === '') continue;
                    
                    $wordLength = mb_strlen($word, 'UTF-8');
                    $reasons = [];
                    
                    if ($wordLength < $minLength) $reasons[] = 'too short (' . $wordLength . ' < ' . $minLength . ')';
                    if ($wordLength > $maxLength) $reasons[] = 'too long (' . $wordLength . ' > ' . $maxLength . ')';
                    if (is_numeric($word)) $reasons[] = 'numeric';
                    
                    if (!empty($reasons)) {
                        error_log('Bears AI Chatbot: Word "' . $word . '" filtered out: ' . implode(', ', $reasons));
                    } else {
                        error_log('Bears AI Chatbot: Word "' . $word . '" should have been kept (length: ' . $wordLength . ')');
                    }
                }
                return;
            }
            
            // Determine if this was a successful interaction
            $wasAnswered = ($outcome === 'answered') ? 1 : 0;
            $wasRefused = ($outcome === 'refused') ? 1 : 0;
            
            error_log('Bears AI Chatbot: Processing keywords with outcome: ' . $outcome . ' (answered=' . $wasAnswered . ', refused=' . $wasRefused . ')');
            
            foreach ($keywords as $keyword) {
                // Check if keyword exists
                $checkQuery = $db->getQuery(true)
                    ->select(['id', 'usage_count', 'total_tokens', 'answered_count', 'refused_count'])
                    ->from($db->quoteName('#__aichatbot_keywords'))
                    ->where($db->quoteName('keyword') . ' = ' . $db->quote($keyword))
                    ->setLimit(1);
                
                $db->setQuery($checkQuery);
                $existing = $db->loadObject();
                
                if ($existing) {
                    // Update existing keyword
                    $newUsageCount = $existing->usage_count + 1;
                    $newTotalTokens = $existing->total_tokens + $totalTokens;
                    $newAnsweredCount = $existing->answered_count + $wasAnswered;
                    $newRefusedCount = $existing->refused_count + $wasRefused;
                    $newAvgTokens = $newTotalTokens / $newUsageCount;
                    $newSuccessRate = ($newAnsweredCount / $newUsageCount) * 100;
                    
                    $updateQuery = $db->getQuery(true)
                        ->update($db->quoteName('#__aichatbot_keywords'))
                        ->set($db->quoteName('usage_count') . ' = ' . (int)$newUsageCount)
                        ->set($db->quoteName('total_tokens') . ' = ' . (int)$newTotalTokens)
                        ->set($db->quoteName('avg_tokens') . ' = ' . number_format($newAvgTokens, 2))
                        ->set($db->quoteName('success_rate') . ' = ' . number_format($newSuccessRate, 2))
                        ->set($db->quoteName('answered_count') . ' = ' . (int)$newAnsweredCount)
                        ->set($db->quoteName('refused_count') . ' = ' . (int)$newRefusedCount)
                        ->set($db->quoteName('last_used') . ' = NOW()')
                        ->where($db->quoteName('id') . ' = ' . (int)$existing->id);
                    
                    $db->setQuery($updateQuery)->execute();
                    error_log('Bears AI Chatbot: Updated existing keyword "' . $keyword . '" (usage: ' . $newUsageCount . ')');
                    
                } else {
                    // Insert new keyword
                    $avgTokens = $totalTokens;
                    $successRate = $wasAnswered * 100; // 100% if answered, 0% if refused
                    
                    $insertQuery = $db->getQuery(true)
                        ->insert($db->quoteName('#__aichatbot_keywords'))
                        ->columns([
                            $db->quoteName('keyword'),
                            $db->quoteName('usage_count'),
                            $db->quoteName('avg_tokens'),
                            $db->quoteName('total_tokens'),
                            $db->quoteName('success_rate'),
                            $db->quoteName('answered_count'),
                            $db->quoteName('refused_count')
                        ])
                        ->values(implode(',', [
                            $db->quote($keyword),
                            1,
                            number_format($avgTokens, 2),
                            (int)$totalTokens,
                            number_format($successRate, 2),
                            (int)$wasAnswered,
                            (int)$wasRefused
                        ]));
                    
                    $db->setQuery($insertQuery)->execute();
                    error_log('Bears AI Chatbot: Inserted new keyword "' . $keyword . '" (success_rate: ' . $successRate . '%)');
                }
            }
            
        } catch (\Throwable $e) {
            // Log the error for debugging
            error_log('Bears AI Chatbot: Keyword tracking error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
