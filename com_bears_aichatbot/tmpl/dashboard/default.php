<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.09.13.4
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

// Variables passed from the main component file
/** @var string $title */
/** @var array $panels */
/** @var array $tokenUsage */
/** @var array $usageTrends */
/** @var string $ionosToken */
/** @var string $ionosTokenId */
/** @var string $ionosCollectionId */
/** @var string $ionosModel */
/** @var string $ionosEndpoint */

// Provide defaults in case variables aren't set
$title = $title ?? Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE');
$panels = $panels ?? [];
$tokenUsage = $tokenUsage ?? [];
$usageTrends = $usageTrends ?? [];
$ionosToken = $ionosToken ?? '';
$ionosTokenId = $ionosTokenId ?? '';
$ionosCollectionId = $ionosCollectionId ?? '';
$ionosModel = $ionosModel ?? '';
$ionosEndpoint = $ionosEndpoint ?? '';

// Helper function to format numbers
function formatNumber($number) {
    if ($number >= 1000000) {
        return number_format($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return number_format($number / 1000, 1) . 'K';
    }
    return number_format($number);
}
?>
<div class="com-bears-aichatbot">
  <div class="container-fluid">
    <h1 class="page-title">
      <?php echo htmlspecialchars($title ?: Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE'), ENT_QUOTES, 'UTF-8'); ?>
    </h1>

    <!-- Token Usage Analytics Section -->
    <div class="token-usage-section mb-4">
      <h2 class="section-title"><?php echo Text::_('COM_BEARS_AICHATBOT_TOKEN_USAGE_TITLE'); ?></h2>
      
      <div class="row g-3 mb-4">
        <?php 
        $periods = [
            'today' => Text::_('COM_BEARS_AICHATBOT_TODAY'),
            '7day' => Text::_('COM_BEARS_AICHATBOT_7DAY'),
            '30day' => Text::_('COM_BEARS_AICHATBOT_30DAY'),
            '6mo' => Text::_('COM_BEARS_AICHATBOT_6MONTH'),
            'ytd' => Text::_('COM_BEARS_AICHATBOT_YTD')
        ];
        
        foreach ($periods as $period => $label) :
            $usage = $tokenUsage[$period] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
            $trend = $usageTrends[$period . '_change'] ?? '0%';
            $trendClass = strpos($trend, '+') === 0 ? 'trend-up' : (strpos($trend, '-') === 0 ? 'trend-down' : 'trend-neutral');
        ?>
        <div class="col-12 col-md-6 col-lg-4 col-xl-2">
          <div class="card token-usage-card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <strong><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></strong>
              <span class="usage-trend <?php echo $trendClass; ?>"><?php echo htmlspecialchars($trend, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="card-body">
              <div class="token-stat">
                <div class="token-type"><?php echo Text::_('COM_BEARS_AICHATBOT_TOTAL_TOKENS'); ?></div>
                <div class="token-count total"><?php echo formatNumber($usage['total_tokens']); ?></div>
              </div>
              <div class="token-breakdown">
                <div class="token-detail">
                  <span class="token-label"><?php echo Text::_('COM_BEARS_AICHATBOT_PROMPT_TOKENS'); ?>:</span>
                  <span class="token-value prompt"><?php echo formatNumber($usage['prompt_tokens']); ?></span>
                </div>
                <div class="token-detail">
                  <span class="token-label"><?php echo Text::_('COM_BEARS_AICHATBOT_COMPLETION_TOKENS'); ?>:</span>
                  <span class="token-value completion"><?php echo formatNumber($usage['completion_tokens']); ?></span>
                </div>
              </div>
              
              <!-- Visual usage bar -->
              <div class="usage-bar-container">
                <?php 
                $promptPercentage = $usage['total_tokens'] > 0 ? ($usage['prompt_tokens'] / $usage['total_tokens']) * 100 : 0;
                $completionPercentage = $usage['total_tokens'] > 0 ? ($usage['completion_tokens'] / $usage['total_tokens']) * 100 : 0;
                ?>
                <div class="usage-bar">
                  <div class="usage-segment prompt-segment" style="width: <?php echo $promptPercentage; ?>%"></div>
                  <div class="usage-segment completion-segment" style="width: <?php echo $completionPercentage; ?>%"></div>
                </div>
                <div class="usage-legend">
                  <span class="legend-item"><span class="legend-color prompt-color"></span> Prompt</span>
                  <span class="legend-item"><span class="legend-color completion-color"></span> Completion</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Status Panels Section -->
    <div class="row g-3">
      <?php foreach ($panels as $panel) : ?>
        <div class="col-12 col-md-6">
          <div class="card">
            <div class="card-header">
              <strong><?php echo htmlspecialchars($panel['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="card-body">
              <?php if (isset($panel['is_html']) && $panel['is_html']): ?>
                <div class="mb-0"><?php echo $panel['content']; ?></div>
              <?php else: ?>
                <p class="mb-0"><?php echo htmlspecialchars($panel['content'], ENT_QUOTES, 'UTF-8'); ?></p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
