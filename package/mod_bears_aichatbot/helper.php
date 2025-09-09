<?php
/**
 * mod_bears_aichatbot - AI Knowledgebase Chatbot for Joomla 5
 * Helper functions
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Http\HttpFactory;

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

        // Build knowledge base context from selected categories (strict mode enforced)
        $strict = true;
        $context = self::buildKnowledgeContext($params, $message, $strict);
        $kbStats = self::$lastContextStats;

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
        
        // Build sitemap if enabled
        $sitemapInfo = '';
        if ((int) $params->get('include_sitemap', 1) === 1) {
            $sitemapUrl = trim((string) $params->get('sitemap_url', ''));
            
            // Try to use external sitemap if URL provided
            if ($sitemapUrl !== '') {
                $sitemap = self::fetchExternalSitemap($sitemapUrl);
            } else {
                // Otherwise build from menu structure
                $sitemap = self::buildSitemap();
            }
            
            if (!empty($sitemap)) {
                $sitemapInfo = "\n\nSITE STRUCTURE (use these actual URLs when referencing pages):\n" . $sitemap . "\n";
            }
        }
        
        // If strict and no relevant KB found, refuse without calling the model
        $hasKb = (($kbStats['article_count'] ?? 0) + ($kbStats['kunena_count'] ?? 0) + ($kbStats['url_count'] ?? 0)) > 0;
        if ($strict && (!$hasKb || stripos($context, 'No knowledge available') !== false)) {
            return ['success' => true, 'answer' => "I don't know based on the provided dataset.", 'kb' => $kbStats];
        }

        // System prompt includes the KB context from Joomla articles
        $systemPrompt = ($strict ? (
            "You are a knowledge base assistant for this Joomla site. Answer using ONLY the content inside <kb>. If the information is not fully supported by <kb>, respond exactly: 'I don't know based on the provided dataset.' Do not use prior knowledge, do not browse the web, and do not guess.\n\n"
            . "IMPORTANT INSTRUCTIONS:\n"
            . "1. Use only the <kb> content for facts and instructions.\n"
            . "2. When referencing pages, only use URLs present in the site structure below.\n"
            . "3. The website URL is: " . $siteUrl . "\n"
            . "4. Format links as clickable Markdown: [Link Text](URL)\n"
            . "5. NEVER make up URLs or content not present in <kb> or the site structure.\n"
        ) : (
            "You are a helpful AI assistant for this Joomla site. Use ONLY the provided knowledge base context when possible. If the context lacks the answer, say you don't know and suggest related topics.\n\n"
            . "IMPORTANT INSTRUCTIONS:\n"
            . "1. When referencing pages, use the actual URLs from the site structure provided below.\n"
            . "2. The website URL is: " . $siteUrl . "\n"
            . "3. Format links as clickable Markdown: [Link Text](URL)\n"
            . "4. NEVER make up URLs. Only use URLs from the site structure or knowledge base.\n"
            . "5. When you mention a topic that appears in the site structure, provide the actual link.\n"
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

            $response = $http->post($url, json_encode($payload), $headers);

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
                // Fall back to building from menu
                return self::buildSitemap();
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
            // Fall back to building from menu if external sitemap fails
            return self::buildSitemap();
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
                return self::buildSitemap();
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
            
            return self::buildSitemap();
            
        } catch (\Throwable $e) {
            return self::buildSitemap();
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
                return self::buildSitemap();
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
            
            // Otherwise fall back to building from menu
            return self::buildSitemap();
            
        } catch (\Throwable $e) {
            // Fall back to building from menu if external sitemap fails
            return self::buildSitemap();
        }
    }
}
