<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.09.14.18
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
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="page-title mb-0">
        <?php echo htmlspecialchars($title ?: Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE'), ENT_QUOTES, 'UTF-8'); ?>
      </h1>
      <div class="page-actions">
        <a href="<?php echo \Joomla\CMS\Router\Route::_('index.php?option=com_bears_aichatbot&view=collections'); ?>" class="btn btn-primary">
          <i class="fas fa-database"></i> <?php echo Text::_('COM_BEARS_AICHATBOT_VIEW_COLLECTIONS'); ?>
        </a>
      </div>
    </div>

    <!-- System Status Section -->
    <div class="system-status-section mb-4">
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
              
              <!-- Mini chart for this period -->
              <div class="mini-chart-container">
                <canvas id="chart-<?php echo $period; ?>" width="200" height="100"></canvas>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Interactive Chart Section -->
    <div class="chart-section mb-4">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="mb-0"><?php echo Text::_('COM_BEARS_AICHATBOT_USAGE_CHART_TITLE'); ?></h3>
          <div class="chart-controls">
            <select id="chartPeriod" class="form-select form-select-sm">
              <option value="7"><?php echo Text::_('COM_BEARS_AICHATBOT_LAST_7_DAYS'); ?></option>
              <option value="30" selected><?php echo Text::_('COM_BEARS_AICHATBOT_LAST_30_DAYS'); ?></option>
              <option value="90"><?php echo Text::_('COM_BEARS_AICHATBOT_LAST_90_DAYS'); ?></option>
            </select>
          </div>
        </div>
        <div class="card-body">
          <div class="chart-container">
            <canvas id="mainUsageChart" width="800" height="400"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Token usage data from PHP
    const tokenData = <?php echo json_encode($tokenUsage); ?>;
    
    // Initialize mini charts for each period card
    <?php foreach ($periods as $period => $label): ?>
    <?php $usage = $tokenUsage[$period] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0]; ?>
    initMiniChart('chart-<?php echo $period; ?>', {
        prompt: <?php echo $usage['prompt_tokens']; ?>,
        completion: <?php echo $usage['completion_tokens']; ?>
    });
    <?php endforeach; ?>
    
    // Initialize main interactive chart
    initMainChart();
    
    // Handle period selection change
    document.getElementById('chartPeriod').addEventListener('change', function() {
        updateMainChart(this.value);
    });
});

function initMiniChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['<?php echo Text::_('COM_BEARS_AICHATBOT_PROMPT_TOKENS'); ?>', '<?php echo Text::_('COM_BEARS_AICHATBOT_COMPLETION_TOKENS'); ?>'],
            datasets: [{
                data: [data.prompt, data.completion],
                backgroundColor: ['#28a745', '#17a2b8'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

function initMainChart() {
    const ctx = document.getElementById('mainUsageChart');
    if (!ctx) return;
    
    // Generate sample daily data for the last 30 days
    const days = [];
    const promptData = [];
    const completionData = [];
    
    for (let i = 29; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        days.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        
        // Generate sample data (replace with actual database queries)
        const dailyPrompt = Math.floor(Math.random() * 1000) + 100;
        const dailyCompletion = Math.floor(Math.random() * 200) + 20;
        
        promptData.push(dailyPrompt);
        completionData.push(dailyCompletion);
    }
    
    window.mainChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: days,
            datasets: [{
                label: '<?php echo Text::_('COM_BEARS_AICHATBOT_PROMPT_TOKENS'); ?>',
                data: promptData,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                fill: true,
                tension: 0.4
            }, {
                label: '<?php echo Text::_('COM_BEARS_AICHATBOT_COMPLETION_TOKENS'); ?>',
                data: completionData,
                borderColor: '#17a2b8',
                backgroundColor: 'rgba(23, 162, 184, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        footer: function(tooltipItems) {
                            let total = 0;
                            tooltipItems.forEach(function(tooltipItem) {
                                total += tooltipItem.parsed.y;
                            });
                            return 'Total: ' + total.toLocaleString() + ' tokens';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value >= 1000 ? (value/1000).toFixed(1) + 'K' : value;
                        }
                    }
                }
            }
        }
    });
}

function updateMainChart(days) {
    // This would fetch new data based on the selected period
    // For now, just update the title
    console.log('Updating chart for last ' + days + ' days');
    // In a real implementation, you'd make an AJAX call to get new data
}
</script>
