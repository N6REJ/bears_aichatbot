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
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Http\HttpFactory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

// Load configuration file
require_once __DIR__ . '/config/aichatbot.php';

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
        
        // Get the configured no-data message
        $noDataMessage = trim((string)$params->get('no_data_message', "I'm sorry I don't know how to answer that"));
        if (empty($noDataMessage)) {
            $noDataMessage = "I'm sorry I don't know how to answer that";
        }
        
        // If strict and no relevant KB found, refuse without calling the model
        $hasKb = (($kbStats['article_count'] ?? 0)
            + ($kbStats['kunena_count'] ?? 0)
            + ($kbStats['url_count'] ?? 0)
            + ($kbStats['retrieved'] ?? 0)) > 0;
        if ($strict && (!$hasKb || stripos($context, 'No knowledge available') !== false)) {
            return ['success' => true, 'answer' => $noDataMessage, 'kb' => $kbStats];
        }

        // System prompt includes the KB context from Joomla articles
        $systemPrompt = ($strict ? (
            "You are a knowledge base assistant for this Joomla site. Answer using ONLY the content inside <kb>. If the information is not fully supported by <kb>, respond exactly: '" . $noDataMessage . "' Do not use prior knowledge, do not browse the web, and do not guess.\n\n"
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

        // Get limits from module parameters, fallback to config defaults
        $maxTokens = (int) $params->get('max_response_tokens', BearsAIChatbotConfig::get('LIMITS.max_response_tokens', 1024));
        $temperature = (float) $params->get('temperature', 0.2);
        
        $payload = [
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $message],
            ],
            'max_tokens'  => $maxTokens,
            'temperature' => $strict ? 0.0 : $temperature,
        ];

        // OpenAI-compatible chat completions endpoint (IONOS Model Hub)
        $defaultEndpoint = BearsAIChatbotConfig::getEndpoint('ionos', 'chat');
        $url = $endpoint !== '' ? $endpoint : $defaultEndpoint;

        try {
            $http = HttpFactory::getHttp();
            $headers = [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ];

            // Get request timeout from params
            $requestTimeout = (int) $params->get('request_timeout', BearsAIChatbotConfig::get('LIMITS.request_timeout', 30));
            
            $requestBody = json_encode($payload);
            $t0 = microtime(true);
            $response = $http->post($url, $requestBody, $headers, $requestTimeout);
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
                // Debug: Log the raw response body structure using Joomla's logging
                \Joomla\CMS\Log\Log::addLogger(
                    ['text_file' => 'bears_aichatbot.php'],
                    \Joomla\CMS\Log\Log::ALL,
                    ['bears_aichatbot']
                );
                
                \Joomla\CMS\Log\Log::add(
                    'Raw API response keys: ' . json_encode(array_keys($body)),
                    \Joomla\CMS\Log\Log::DEBUG,
                    'bears_aichatbot'
                );
                
                if (isset($body['usage'])) {
                    \Joomla\CMS\Log\Log::add(
                        'Usage object found: ' . json_encode($body['usage']),
                        \Joomla\CMS\Log\Log::INFO,
                        'bears_aichatbot'
                    );
                } else {
                    \Joomla\CMS\Log\Log::add(
                        'No usage object in response - will use fallback',
                        \Joomla\CMS\Log\Log::WARNING,
                        'bears_aichatbot'
                    );
                }
                
                // Primary: standard OpenAI-compatible usage object
                $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];

                // Fallback A: alternative top-level fields seen on some providers
                if (empty($usage)) {
                    $alt = [
                        'prompt_tokens'     => $body['prompt_tokens']     ?? $body['input_tokens']       ?? $body['inputTokenCount']  ?? null,
                        'completion_tokens' => $body['completion_tokens'] ?? $body['output_tokens']      ?? $body['outputTokenCount'] ?? null,
                        'total_tokens'      => $body['total_tokens']      ?? $body['token_count']        ?? $body['tokenCount']       ?? null,
                    ];
                    $alt = array_filter($alt, static function($v){ return $v !== null; });
                    if (!empty($alt)) { $usage = $alt; }
                }

                // Fallback B: read usage from response headers if present
                if (empty($usage)) {
                    $headersResp = [];
                    try { $headersResp = (array) ($response->headers ?? []); } catch (\Throwable $e) {}
                    $h = []; // Initialize the $h array
                    foreach ($headersResp as $hk => $hv) {
                        $key = is_string($hk) ? strtolower($hk) : (string)$hk;
                        $h[$key] = is_array($hv) ? implode(',', $hv) : (string)$hv;
                    }
                    $pt = $h['x-usage-prompt-tokens'] ?? $h['x-openai-usage-prompt-tokens'] ?? $h['x-usage-input-tokens'] ?? null;
                    $ct = $h['x-usage-completion-tokens'] ?? $h['x-openai-usage-completion-tokens'] ?? $h['x-usage-output-tokens'] ?? null;
                    $tt = $h['x-usage-total-tokens'] ?? $h['x-openai-usage-total-tokens'] ?? null;
                    if ($pt !== null || $ct !== null || $tt !== null) {
                        $usage = [
                            'prompt_tokens'     => (int)$pt,
                            'completion_tokens' => (int)$ct,
                            'total_tokens'      => (int)$tt,
                        ];
                    }
                }

                // Fallback C: last-resort estimate when provider omits usage entirely
                if (empty($usage)) {
                    $reqChars = mb_strlen((string)$requestBody, '8bit');
                    $ansChars = mb_strlen((string)$answer, 'UTF-8');
                    $estPrompt = (int) ceil($reqChars / 4.0); // rough heuristic
                    $estCompletion = (int) ceil($ansChars / 4.0);
                    $usage = [
                        'prompt_tokens'     => $estPrompt,
                        'completion_tokens' => $estCompletion,
                        'total_tokens'      => $estPrompt + $estCompletion,
                        '_estimated'        => true,
                    ];
                }

                // Detect outcome: answered/refused
                $ansLower = mb_strtolower($answer);
                $noDataLower = mb_strtolower($noDataMessage);
                $outcome = (strpos($ansLower, $noDataLower) !== false || strpos($ansLower, "i don't know") !== false) ? 'refused' : 'answered';
                // If status >= 400 treat as error
                if ((int)$response->code >= 400) { $outcome = 'error'; }

                $topScore = null;
                if (isset($kbStats['retrieved_top_score']) && is_numeric($kbStats['retrieved_top_score'])) {
                    $topScore = (float)$kbStats['retrieved_top_score'];
                }
                // Log detailed usage data before database insert
                \Joomla\CMS\Log\Log::add(
                    'Attempting to log usage - Tokens: prompt=' . ($usage['prompt_tokens'] ?? 0) . 
                    ', completion=' . ($usage['completion_tokens'] ?? 0) . 
                    ', total=' . ($usage['total_tokens'] ?? 0) . 
                    ', outcome=' . $outcome . 
                    ', estimated=' . (isset($usage['_estimated']) ? 'true' : 'false'),
                    \Joomla\CMS\Log\Log::INFO,
                    'bears_aichatbot'
                );
                
                $logResult = self::logUsageExtended(
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
        $maxTotal = (int) $params->get('max_context_length', BearsAIChatbotConfig::get('LIMITS.max_context_length', 30000));
        $contextParts = [];
        $total = 0;

        // Article fetch limit (configurable, default from config)
        $defaultLimit = BearsAIChatbotConfig::get('LIMITS.max_article_fetch', 500);
        $limit = (int) $params->get('article_limit', $defaultLimit);
        if ($limit < 1) { $limit = $defaultLimit; }

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

            // Apply keyword relevance if user provided a message
            $userMessage = trim($userMessage);
            if ($userMessage !== '') {
                // First, try to search for the complete phrase
                $fullPhrase = mb_strtolower($userMessage);
                $likes = [];
                
                // Add full phrase search (if not too long)
                if (mb_strlen($fullPhrase) <= 100) {
                    $kw = $db->escape($fullPhrase, true);
                    $like = $db->quote('%' . $kw . '%', false);
                    $likes[] = '(' . $db->quoteName('title') . ' LIKE ' . $like . ' OR ' . $db->quoteName('introtext') . ' LIKE ' . $like . ' OR ' . $db->quoteName('fulltext') . ' LIKE ' . $like . ')';
                }
                
                // Also search for individual significant words
                $terms = preg_split('/\s+/', $fullPhrase);
                $maxTerms = 8; // Increased from 5
                $termCount = 0;
                
                // Common question words to skip
                $skipWords = ['what', 'is', 'are', 'how', 'why', 'when', 'where', 'who', 'which', 'can', 'does', 'do', 'the', 'a', 'an'];
                
                foreach ($terms as $t) {
                    $t = trim($t);
                    // Skip very short words unless they might be technical terms (all caps or contains numbers)
                    if (mb_strlen($t) < 2) continue;
                    if (mb_strlen($t) < 3 && !preg_match('/^[A-Z]+$/', $t) && !preg_match('/\d/', $t)) continue;
                    // Skip common question words
                    if (in_array($t, $skipWords)) continue;
                    
                    $kw = $db->escape($t, true);
                    $like = $db->quote('%' . $kw . '%', false);
                    $likes[] = '(' . $db->quoteName('title') . ' LIKE ' . $like . ' OR ' . $db->quoteName('introtext') . ' LIKE ' . $like . ' OR ' . $db->quoteName('fulltext') . ' LIKE ' . $like . ')';
                    $termCount++;
                    if ($termCount >= $maxTerms) break;
                }
                
                $hadLikes = !empty($likes);
                if ($hadLikes) {
                    $query->where('(' . implode(' OR ', $likes) . ')');
                }
            }

            $query->order($db->escape('modified DESC, created DESC'));

            $db->setQuery($query, 0, $limit);
            $items = (array) $db->loadAssocList();

            // Fallback to recent items if no keyword matches
            if (!$items && !$hadLikes) {
                // In non-strict mode, get recent articles from selected categories
                if (!$strict) {
                    $query = $db->getQuery(true)
                        ->select($db->quoteName(['id', 'title', 'introtext', 'fulltext']))
                        ->from($db->quoteName('#__content'))
                        ->where($db->quoteName('state') . ' = 1')
                        ->where($db->quoteName('catid') . ' IN (' . implode(',', $allCatIds) . ')')
                        ->order($db->escape('modified DESC, created DESC'));
                    $db->setQuery($query, 0, $limit);
                    $items = (array) $db->loadAssocList();
                    if (!$items) {
                        self::$lastContextStats['note'] = 'No articles found in selected categories.';
                    }
                }
                // In strict mode with no keywords, we'll let the site-wide fallback handle it
            }
        }

        // If no items found (either no categories selected or no matches in selected categories), do a site-wide search
        if (empty($items)) {
            // Log that we're falling back to site-wide search
            if (!empty($catIds)) {
                self::$lastContextStats['note'] = 'No matches in selected categories; attempting site-wide search.';
            }
            
            if ($strict) {
                // Site-wide keyword-filtered search in strict mode
                $qAll = $db->getQuery(true)
                    ->select($db->quoteName(['id', 'title', 'introtext', 'fulltext']))
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('state') . ' = 1');

                // Use the same improved keyword extraction logic
                $fullPhrase = mb_strtolower(trim($userMessage));
                $likes = [];
                
                // Add full phrase search (if not too long)
                if (mb_strlen($fullPhrase) <= 100) {
                    $kw = $db->escape($fullPhrase, true);
                    $like = $db->quote('%' . $kw . '%', false);
                    $likes[] = '(' . $db->quoteName('title') . ' LIKE ' . $like . ' OR ' . $db->quoteName('introtext') . ' LIKE ' . $like . ' OR ' . $db->quoteName('fulltext') . ' LIKE ' . $like . ')';
                }
                
                // Also search for individual significant words
                $terms = preg_split('/\s+/', $fullPhrase);
                $maxTerms = 8;
                $termCount = 0;
                
                // Common question words to skip
                $skipWords = ['what', 'is', 'are', 'how', 'why', 'when', 'where', 'who', 'which', 'can', 'does', 'do', 'the', 'a', 'an'];
                
                if (!empty($terms)) {
                    foreach ($terms as $t) {
                        $t = trim($t);
                        // Skip very short words unless they might be technical terms
                        if (mb_strlen($t) < 2) continue;
                        if (mb_strlen($t) < 3 && !preg_match('/^[A-Z]+$/', $t) && !preg_match('/\d/', $t)) continue;
                        // Skip common question words
                        if (in_array($t, $skipWords)) continue;
                        
                        $kw = $db->escape($t, true);
                        $like = $db->quote('%' . $kw . '%', false);
                        $likes[] = '(' . $db->quoteName('title') . ' LIKE ' . $like . ' OR ' . $db->quoteName('introtext') . ' LIKE ' . $like . ' OR ' . $db->quoteName('fulltext') . ' LIKE ' . $like . ')';
                        $termCount++;
                        if ($termCount >= $maxTerms) break;
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
                // Non-strict mode: get recent articles site-wide
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
                // Get max Kunena fetch limit from params
                $maxKunenaFetch = (int) $params->get('max_kunena_fetch', BearsAIChatbotConfig::get('LIMITS.max_kunena_fetch', 100));
                
                $kquery = $db->getQuery(true)
                    ->select($db->quoteName(['m.id', 'm.subject']))
                    ->select($db->quoteName('mt.message'))
                    ->from($db->quoteName('#__kunena_messages', 'm'))
                    ->join('INNER', $db->quoteName('#__kunena_messages_text', 'mt') . ' ON ' . $db->quoteName('mt.mesid') . ' = ' . $db->quoteName('m.id'))
                    ->join('INNER', $db->quoteName('#__kunena_categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('m.catid'))
                    ->where($db->quoteName('c.published') . ' = 1')
                    ->where($db->quoteName('m.hold') . ' = 0')
                    ->order($db->escape('m.time DESC'));

                $db->setQuery($kquery, 0, min($limit, $maxKunenaFetch));
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
            // Get max sitemap URLs from params (note: this is in parseHtmlSitemap method)
            $maxUrls = 150; // Will be replaced with param value in the calling method
            
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

            $prompt = (int)($usage['prompt_tokens'] ?? $usage['promptTokens'] ?? 0);
            $completion = (int)($usage['completion_tokens'] ?? $usage['completionTokens'] ?? 0);
            $total = (int)($usage['total_tokens'] ?? $usage['totalTokens'] ?? ($prompt + $completion));

            $retrieved = isset($kbStats['retrieved']) ? (int)$kbStats['retrieved'] : null;
            $articleCount = (int)($kbStats['article_count'] ?? 0);
            $kunenaCount = (int)($kbStats['kunena_count'] ?? 0);
            $urlCount = (int)($kbStats['url_count'] ?? 0);

            $msgLen = mb_strlen($message ?? '', 'UTF-8');
            $ansLen = mb_strlen($answer ?? '', 'UTF-8');

            // Get pricing from configuration file
            $pricing = BearsAIChatbotConfig::getTokenPricing('ionos', 'standard');
            $pp = $pricing['prompt'] ?? 0.0004; // prompt $/1K
            $pc = $pricing['completion'] ?? 0.0006; // completion $/1K
            $cur = $pricing['currency'] ?? 'USD';
            
            try {
                // Allow component params to override if needed
                $compParams = \Joomla\CMS\Component\ComponentHelper::getParams('com_bears_aichatbot');
                $pp = (float)($compParams->get('price_prompt_standard', $pp));
                $pc = (float)($compParams->get('price_completion_standard', $pc));
                $cur = (string)($compParams->get('price_currency', $cur));
            } catch (\Throwable $ignore) {}
            $est = (($prompt / 1000.0) * $pp) + (($completion / 1000.0) * $pc);

            // Log the values we're about to insert for debugging
            \Joomla\CMS\Log\Log::add(
                'Usage INSERT values - moduleId: ' . $moduleId . 
                ', collectionId: ' . ($collectionId !== '' ? $collectionId : 'NULL') .
                ', model: ' . $model .
                ', prompt: ' . $prompt . ', completion: ' . $completion . ', total: ' . $total .
                ', outcome: ' . ($outcome ?? 'NULL'),
                \Joomla\CMS\Log\Log::DEBUG,
                'bears_aichatbot'
            );

            // Build column list - pass as comma-separated string WITHOUT backticks
            $columnList = 'module_id, collection_id, model, endpoint, prompt_tokens, ' .
                         'completion_tokens, total_tokens, retrieved, article_count, ' .
                         'kunena_count, url_count, message_len, answer_len, ' .
                         'status_code, duration_ms, request_bytes, response_bytes, ' .
                         'outcome, retrieved_top_score, price_prompt, price_completion, ' .
                         'currency, estimated_cost';
            
            $q = $db->getQuery(true)
                ->insert($db->quoteName('#__aichatbot_usage'))
                ->columns($columnList)
                ->values(implode(',', [
                    (int)$moduleId,
                    $collectionId !== '' ? $db->quote($collectionId) : 'NULL',
                    $db->quote($model),
                    $db->quote($endpoint),
                    (int)$prompt,
                    (int)$completion,
                    (int)$total,
                    $retrieved === null ? 'NULL' : (int)$retrieved,
                    (int)$articleCount,
                    (int)$kunenaCount,
                    (int)$urlCount,
                    (int)$msgLen,
                    (int)$ansLen,
                    (int)$statusCode,
                    $durationMs === null ? 'NULL' : (int)$durationMs,
                    $requestBytes === null ? 'NULL' : (int)$requestBytes,
                    $responseBytes === null ? 'NULL' : (int)$responseBytes,
                    $outcome === null ? 'NULL' : $db->quote($outcome),
                    $retrievedTopScore === null ? 'NULL' : number_format($retrievedTopScore, 4, '.', ''),
                    number_format($pp, 6, '.', ''),
                    number_format($pc, 6, '.', ''),
                    $db->quote($cur),
                    number_format($est, 6, '.', ''),
                ]));
            $db->setQuery($q)->execute();
            
            // Log success
            \Joomla\CMS\Log\Log::add(
                'Successfully logged usage to database - ID: ' . $db->insertid() . ', Tokens: ' . $total,
                \Joomla\CMS\Log\Log::INFO,
                'bears_aichatbot'
            );
        } catch (\Throwable $e) {
            // Log the actual error for debugging
            \Joomla\CMS\Log\Log::add(
                'Failed to log usage to database: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
                \Joomla\CMS\Log\Log::ERROR,
                'bears_aichatbot'
            );
            
            // Also log the SQL query for debugging
            \Joomla\CMS\Log\Log::add(
                'Failed SQL: ' . (string)$q,
                \Joomla\CMS\Log\Log::ERROR,
                'bears_aichatbot'
            );
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
        $ignoreWords = [];
        
        if ($params) {
            $minLength = (int)$params->get('keyword_min_length', 3);
            $maxLength = (int)$params->get('keyword_max_length', 50);
            $ignoreWordsString = trim((string)$params->get('ignore_words', ''));
            
            // If no custom ignore words configured, use the default from language file
            if ($ignoreWordsString === '') {
                $ignoreWordsString = \Joomla\CMS\Language\Text::_('MOD_BEARS_AICHATBOT_IGNORE_WORDS_DEFAULT');
            }
            
            if ($ignoreWordsString !== '' && $ignoreWordsString !== 'MOD_BEARS_AICHATBOT_IGNORE_WORDS_DEFAULT') {
                $ignoreWords = array_map('trim', explode(',', mb_strtolower($ignoreWordsString, 'UTF-8')));
                $ignoreWords = array_filter($ignoreWords); // Remove empty strings
            }
        }
        
        // If still no ignore words, use minimal fallback
        if (empty($ignoreWords)) {
            $ignoreWords = [
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
            
            // Skip if it's a ignore word
            if (in_array($word, $ignoreWords)) {
                continue;
            }
            
            // Skip if it's just numbers
            if (is_numeric($word)) {
                continue;
            }
            
            // Skip very common words that might not be in ignore words
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
            
            // Set up Joomla logging for this component
            \Joomla\CMS\Log\Log::addLogger(
                ['text_file' => 'bears_aichatbot.php'],
                \Joomla\CMS\Log\Log::ALL,
                ['bears_aichatbot']
            );
            
            // Extract keywords from the message using configurable settings
            $keywords = self::extractKeywords($message, $params);
            
            // Get params for debugging
            $minLength = $params ? (int)$params->get('keyword_min_length', 3) : 3;
            $maxLength = $params ? (int)$params->get('keyword_max_length', 50) : 50;
            $ignoreWordsString = $params ? trim((string)$params->get('ignore_words', '')) : '';
            if ($ignoreWordsString === '') {
                $ignoreWordsString = \Joomla\CMS\Language\Text::_('MOD_BEARS_AICHATBOT_IGNORE_WORDS_DEFAULT');
            }
            $ignoreWordsCount = 0;
            if ($ignoreWordsString !== '' && $ignoreWordsString !== 'MOD_BEARS_AICHATBOT_IGNORE_WORDS_DEFAULT') {
                $ignoreWords = array_map('trim', explode(',', mb_strtolower($ignoreWordsString, 'UTF-8')));
                $ignoreWordsCount = count(array_filter($ignoreWords));
            }
            
            // Debug logging to help troubleshoot keyword extraction
            \Joomla\CMS\Log\Log::add(
                'Message "' . $message . '" extracted keywords: ' . json_encode($keywords),
                \Joomla\CMS\Log\Log::DEBUG,
                'bears_aichatbot'
            );
            
            if (empty($keywords)) {
                \Joomla\CMS\Log\Log::add(
                    'No keywords extracted from message: "' . $message . '"',
                    \Joomla\CMS\Log\Log::WARNING,
                    'bears_aichatbot'
                );
                
                // Debug: test the extraction process step by step
                $testMessage = mb_strtolower($message, 'UTF-8');
                $testMessage = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $testMessage);
                $testWords = preg_split('/\s+/', trim($testMessage));
                
                \Joomla\CMS\Log\Log::add(
                    'Test words after processing: ' . json_encode($testWords),
                    \Joomla\CMS\Log\Log::DEBUG,
                    'bears_aichatbot'
                );
                
                // Test each word against filters for debugging
                foreach ($testWords as $word) {
                    $word = trim($word);
                    if ($word === '') continue;
                    
                    $wordLength = mb_strlen($word, 'UTF-8');
                    $reasons = [];
                    
                    if ($wordLength < $minLength) $reasons[] = 'too short (' . $wordLength . ' < ' . $minLength . ')';
                    if ($wordLength > $maxLength) $reasons[] = 'too long (' . $wordLength . ' > ' . $maxLength . ')';
                    if (is_numeric($word)) $reasons[] = 'numeric';
                    
                    if (!empty($reasons)) {
                        \Joomla\CMS\Log\Log::add(
                            'Word "' . $word . '" filtered out: ' . implode(', ', $reasons),
                            \Joomla\CMS\Log\Log::DEBUG,
                            'bears_aichatbot'
                        );
                    }
                }
                return;
            }
            
            // Determine if this was a successful interaction
            $wasAnswered = ($outcome === 'answered') ? 1 : 0;
            $wasRefused = ($outcome === 'refused') ? 1 : 0;
            
            \Joomla\CMS\Log\Log::add(
                'Processing ' . count($keywords) . ' keywords with outcome: ' . $outcome . ' (answered=' . $wasAnswered . ', refused=' . $wasRefused . ')',
                \Joomla\CMS\Log\Log::INFO,
                'bears_aichatbot'
            );
            
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
                        ->set($db->quoteName('avg_tokens') . ' = ' . number_format($newAvgTokens, 2, '.', ''))
                        ->set($db->quoteName('success_rate') . ' = ' . number_format($newSuccessRate, 2, '.', ''))
                        ->set($db->quoteName('answered_count') . ' = ' . (int)$newAnsweredCount)
                        ->set($db->quoteName('refused_count') . ' = ' . (int)$newRefusedCount)
                        ->set($db->quoteName('last_used') . ' = NOW()')
                        ->where($db->quoteName('id') . ' = ' . (int)$existing->id);
                    
                    $db->setQuery($updateQuery)->execute();
                    
                    \Joomla\CMS\Log\Log::add(
                        'Updated existing keyword "' . $keyword . '" (usage: ' . $newUsageCount . ', success_rate: ' . number_format($newSuccessRate, 2) . '%)',
                        \Joomla\CMS\Log\Log::INFO,
                        'bears_aichatbot'
                    );
                    
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
                            number_format($avgTokens, 2, '.', ''), // No thousands separator
                            (int)$totalTokens,
                            number_format($successRate, 2, '.', ''), // No thousands separator
                            (int)$wasAnswered,
                            (int)$wasRefused
                        ]));
                    
                    $db->setQuery($insertQuery)->execute();
                    
                    \Joomla\CMS\Log\Log::add(
                        'Inserted new keyword "' . $keyword . '" (success_rate: ' . $successRate . '%)',
                        \Joomla\CMS\Log\Log::INFO,
                        'bears_aichatbot'
                    );
                }
            }
            
        } catch (\Throwable $e) {
            // Log the error for debugging
            \Joomla\CMS\Log\Log::add(
                'Keyword tracking error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
                \Joomla\CMS\Log\Log::ERROR,
                'bears_aichatbot'
            );
        }
    }
}
