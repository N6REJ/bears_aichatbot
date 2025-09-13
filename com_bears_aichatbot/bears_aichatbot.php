<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.09.13.5
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Registry\Registry;

/**
 * Get IONOS configuration from the first published Bears AI Chatbot module
 */
function getModuleConfig(): array
{
    try {
        $db = Factory::getContainer()->get('DatabaseDriver');
        
        // Find the first published Bears AI Chatbot module
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'params']))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_bears_aichatbot'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('id'))
            ->setLimit(1);
        
        $db->setQuery($query);
        $module = $db->loadObject();
        
        if (!$module) {
            return [];
        }
        
        $params = new Registry($module->params);
        
        // Get collection ID from state table if not in module params
        $collectionId = trim((string)$params->get('ionos_collection_id', ''));
        if ($collectionId === '') {
            $stateQuery = $db->getQuery(true)
                ->select($db->quoteName('collection_id'))
                ->from($db->quoteName('#__aichatbot_state'))
                ->where($db->quoteName('id') . ' = 1')
                ->setLimit(1);
            $db->setQuery($stateQuery);
            $collectionId = (string)($db->loadResult() ?? '');
        }
        
        return [
            'token' => trim((string)$params->get('ionos_token', '')),
            'token_id' => trim((string)$params->get('ionos_token_id', '')),
            'collection_id' => $collectionId,
            'model' => trim((string)$params->get('ionos_model', '')),
            'endpoint' => trim((string)$params->get('ionos_endpoint', '')),
            'module_id' => $module->id,
        ];
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * Get token usage data from the database for different time periods
 */
function getTokenUsageData(): array
{
    try {
        $db = Factory::getContainer()->get('DatabaseDriver');
        
        // Check if usage table exists
        $tables = $db->getTableList();
        $prefix = $db->getPrefix();
        $usageTable = $prefix . 'aichatbot_usage';
        
        if (!in_array($usageTable, $tables)) {
            // Return empty data if table doesn't exist yet
            return [
                'today' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                '7day' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                '30day' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                '6mo' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'ytd' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            ];
        }
        
        $now = new DateTime();
        $periods = [
            'today' => $now->format('Y-m-d') . ' 00:00:00',
            '7day' => (clone $now)->modify('-7 days')->format('Y-m-d H:i:s'),
            '30day' => (clone $now)->modify('-30 days')->format('Y-m-d H:i:s'),
            '6mo' => (clone $now)->modify('-6 months')->format('Y-m-d H:i:s'),
            'ytd' => $now->format('Y') . '-01-01 00:00:00',
        ];
        
        $usage = [];
        
        foreach ($periods as $period => $startDate) {
            $query = $db->getQuery(true)
                ->select([
                    'SUM(' . $db->quoteName('prompt_tokens') . ') AS prompt_tokens',
                    'SUM(' . $db->quoteName('completion_tokens') . ') AS completion_tokens',
                    'SUM(' . $db->quoteName('total_tokens') . ') AS total_tokens'
                ])
                ->from($db->quoteName('#__aichatbot_usage'))
                ->where($db->quoteName('created_at') . ' >= ' . $db->quote($startDate));
            
            $db->setQuery($query);
            $result = $db->loadObject();
            
            $usage[$period] = [
                'prompt_tokens' => (int)($result->prompt_tokens ?? 0),
                'completion_tokens' => (int)($result->completion_tokens ?? 0),
                'total_tokens' => (int)($result->total_tokens ?? 0),
            ];
        }
        
        return $usage;
        
    } catch (\Throwable $e) {
        // Return empty data on error
        return [
            'today' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            '7day' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            '30day' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            '6mo' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            'ytd' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
        ];
    }
}

/**
 * Calculate usage trends (percentage change from previous period)
 */
function calculateUsageTrends(array $tokenUsage): array
{
    try {
        $db = Factory::getContainer()->get('DatabaseDriver');
        
        // Check if usage table exists
        $tables = $db->getTableList();
        $prefix = $db->getPrefix();
        $usageTable = $prefix . 'aichatbot_usage';
        
        if (!in_array($usageTable, $tables)) {
            return [
                'today_change' => '0%',
                '7day_change' => '0%',
                '30day_change' => '0%',
                '6mo_change' => '0%',
                'ytd_change' => '0%',
            ];
        }
        
        $now = new DateTime();
        $trends = [];
        
        // Calculate trends by comparing with previous periods
        $comparisons = [
            'today' => [
                'current_start' => $now->format('Y-m-d') . ' 00:00:00',
                'previous_start' => $now->modify('-1 day')->format('Y-m-d') . ' 00:00:00',
                'previous_end' => $now->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
            ],
            '7day' => [
                'current_start' => $now->modify('-7 days')->format('Y-m-d H:i:s'),
                'previous_start' => $now->modify('-7 days')->format('Y-m-d H:i:s'),
                'previous_end' => $now->modify('+7 days')->format('Y-m-d H:i:s'),
            ],
        ];
        
        foreach (['today', '7day'] as $period) {
            $current = $tokenUsage[$period]['total_tokens'] ?? 0;
            
            if (isset($comparisons[$period])) {
                $comp = $comparisons[$period];
                $query = $db->getQuery(true)
                    ->select('SUM(' . $db->quoteName('total_tokens') . ') AS total_tokens')
                    ->from($db->quoteName('#__aichatbot_usage'))
                    ->where($db->quoteName('created_at') . ' >= ' . $db->quote($comp['previous_start']))
                    ->where($db->quoteName('created_at') . ' < ' . $db->quote($comp['previous_end']));
                
                $db->setQuery($query);
                $previous = (int)($db->loadResult() ?? 0);
                
                if ($previous > 0) {
                    $change = (($current - $previous) / $previous) * 100;
                    $trends[$period . '_change'] = ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
                } else {
                    $trends[$period . '_change'] = $current > 0 ? '+100%' : '0%';
                }
            } else {
                $trends[$period . '_change'] = '0%';
            }
        }
        
        // For longer periods, use simple indicators
        $trends['30day_change'] = $tokenUsage['30day']['total_tokens'] > 0 ? '+' . number_format($tokenUsage['30day']['total_tokens'] / 1000, 1) . 'K' : '0%';
        $trends['6mo_change'] = $tokenUsage['6mo']['total_tokens'] > 0 ? '+' . number_format($tokenUsage['6mo']['total_tokens'] / 1000, 1) . 'K' : '0%';
        $trends['ytd_change'] = $tokenUsage['ytd']['total_tokens'] > 0 ? '+' . number_format($tokenUsage['ytd']['total_tokens'] / 1000, 1) . 'K' : '0%';
        
        return $trends;
        
    } catch (\Throwable $e) {
        return [
            'today_change' => '0%',
            '7day_change' => '0%',
            '30day_change' => '0%',
            '6mo_change' => '0%',
            'ytd_change' => '0%',
        ];
    }
}

// Load component language
$lang = Factory::getLanguage();
$lang->load('com_bears_aichatbot', JPATH_ADMINISTRATOR);

// Load component CSS
HTMLHelper::_('stylesheet', 'com_bears_aichatbot/admin.css', ['version' => 'auto', 'relative' => true]);

// Get the requested view
$input = Factory::getApplication()->input;
$view = $input->getCmd('view', 'dashboard');

// For now, we only support the dashboard view
if ($view !== 'dashboard') {
    $view = 'dashboard';
}

// Set page title
$document = Factory::getDocument();
$document->setTitle(Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE'));

// Prepare dashboard data - use the exact variable names the template expects
$title = Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE');

// Get IONOS configuration from the module
$moduleConfig = getModuleConfig();
$ionosToken = $moduleConfig['token'] ?? '';
$ionosTokenId = $moduleConfig['token_id'] ?? '';
$ionosCollectionId = $moduleConfig['collection_id'] ?? '';
$ionosModel = $moduleConfig['model'] ?? '';
$ionosEndpoint = $moduleConfig['endpoint'] ?? '';

// Get real token usage data from database
$tokenUsage = getTokenUsageData();

// Calculate usage trends (percentage change from previous period)
$usageTrends = calculateUsageTrends($tokenUsage);

// Build status content with configuration details
$statusContent = '';
if ($ionosToken && $ionosTokenId) {
    $statusContent = Text::_('COM_BEARS_AICHATBOT_PANEL_STATUS_CONNECTED') . '<br><br>';
    $statusContent .= '<strong>' . Text::_('COM_BEARS_AICHATBOT_CONFIG_DETAILS') . '</strong><br>';
    $statusContent .= Text::_('COM_BEARS_AICHATBOT_TOKEN_ID') . ': ' . htmlspecialchars(substr($ionosTokenId, 0, 8) . '...', ENT_QUOTES, 'UTF-8') . '<br>';
    if ($ionosCollectionId) {
        $statusContent .= Text::_('COM_BEARS_AICHATBOT_COLLECTION_ID') . ': ' . htmlspecialchars(substr($ionosCollectionId, 0, 12) . '...', ENT_QUOTES, 'UTF-8') . '<br>';
    } else {
        $statusContent .= Text::_('COM_BEARS_AICHATBOT_COLLECTION_ID') . ': <em>Not created yet (will be auto-created)</em><br>';
    }
    $statusContent .= Text::_('COM_BEARS_AICHATBOT_MODEL') . ': ' . htmlspecialchars($ionosModel, ENT_QUOTES, 'UTF-8') . '<br>';
    $statusContent .= Text::_('COM_BEARS_AICHATBOT_ENDPOINT') . ': ' . htmlspecialchars($ionosEndpoint, ENT_QUOTES, 'UTF-8') . '<br>';
} else {
    $statusContent = Text::_('COM_BEARS_AICHATBOT_PANEL_STATUS_NOT_CONFIGURED');
}

// Build usage summary content
$summaryContent = '';
$totalTokensAllTime = array_sum(array_column($tokenUsage, 'total_tokens'));
$todayTokens = $tokenUsage['today']['total_tokens'] ?? 0;
$weekTokens = $tokenUsage['7day']['total_tokens'] ?? 0;

if ($totalTokensAllTime > 0) {
    $summaryContent = '<strong>' . Text::_('COM_BEARS_AICHATBOT_USAGE_SUMMARY') . '</strong><br>';
    $summaryContent .= Text::_('COM_BEARS_AICHATBOT_TODAY_USAGE') . ': ' . number_format($todayTokens) . ' tokens<br>';
    $summaryContent .= Text::_('COM_BEARS_AICHATBOT_WEEK_USAGE') . ': ' . number_format($weekTokens) . ' tokens<br>';
    $summaryContent .= Text::_('COM_BEARS_AICHATBOT_TOTAL_TRACKED') . ': ' . number_format($totalTokensAllTime) . ' tokens<br>';
    
    // Calculate estimated cost (using IONOS standard pricing)
    $estimatedCost = ($totalTokensAllTime / 1000) * 0.0005; // Rough estimate
    $summaryContent .= Text::_('COM_BEARS_AICHATBOT_ESTIMATED_COST') . ': ~$' . number_format($estimatedCost, 4);
} else {
    $summaryContent = Text::_('COM_BEARS_AICHATBOT_NO_USAGE_YET');
}

$panels = [
    [
        'title' => Text::_('COM_BEARS_AICHATBOT_PANEL_STATUS'),
        'content' => $statusContent,
        'is_html' => true,
    ],
    [
        'title' => Text::_('COM_BEARS_AICHATBOT_PANEL_USAGE_SUMMARY'),
        'content' => $summaryContent,
        'is_html' => true,
    ],
];

// Include the template directly - variables will be in scope
require JPATH_COMPONENT_ADMINISTRATOR . '/tmpl/dashboard/default.php';
