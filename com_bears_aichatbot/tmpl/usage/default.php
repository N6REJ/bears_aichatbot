<?php
/**
 * Bears AI Chatbot - Token Usage Analytics View
 *
 * @version 2025.09.19
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
/** @var array $tokenUsage */
/** @var array $usageTrends */
/** @var string $summaryContent */

// Provide defaults in case variables aren't set
$title = $title ?? Text::_('COM_BEARS_AICHATBOT_USAGE_TITLE');
$tokenUsage = $tokenUsage ?? [];
$usageTrends = $usageTrends ?? [];
$summaryContent = $summaryContent ?? '';

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
        <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
      </h1>
      <div class="page-actions">
        <button type="button" class="btn btn-primary" onclick="window.location.reload()">
          <i class="fas fa-sync-alt"></i> <?php echo Text::_('COM_BEARS_AICHATBOT_REFRESH'); ?>
        </button>
      </div>
    </div>

    <!-- Usage Summary Card -->
    <?php if ($summaryContent): ?>
    <div class="usage-summary-section mb-4">
      <div class="card">
        <div class="card-header">
          <h3 class="mb-0">
            <i class="fas fa-chart-pie"></i> 
            <?php echo Text::_('COM_BEARS_AICHATBOT_USAGE_SUMMARY'); ?>
          </h3>
        </div>
        <div class="card-body">
          <?php echo $summaryContent; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

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

    <!-- Usage Details Table -->
    <div class="usage-details-section">
      <div class="card">
        <div class="card-header">
          <h3 class="mb-0">
            <i class="fas fa-table"></i> 
            <?php echo Text::_('COM_BEARS_AICHATBOT_USAGE_DETAILS'); ?>
          </h3>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th><?php echo Text::_('COM_BEARS_AICHATBOT_PERIOD'); ?></th>
                  <th class="text-end"><?php echo Text::_('COM_BEARS_AICHATBOT_PROMPT_TOKENS'); ?></th>
                  <th class="text-end"><?php echo Text::_('COM_BEARS_AICHATBOT_COMPLETION_TOKENS'); ?></th>
                  <th class="text-end"><?php echo Text::_('COM_BEARS_AICHATBOT_TOTAL_TOKENS'); ?></th>
                  <th class="text-center"><?php echo Text::_('COM_BEARS_AICHATBOT_TREND'); ?></th>
                  <th class="text-end"><?php echo Text::_('COM_BEARS_AICHATBOT_EST_COST'); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($periods as $period => $label): ?>
                <?php 
                $usage = $tokenUsage[$period] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
                $trend = $usageTrends[$period . '_change'] ?? '0%';
                $trendClass = strpos($trend, '+') === 0 ? 'text-success' : (strpos($trend, '-') === 0 ? 'text-danger' : 'text-muted');
                $estimatedCost = ($usage['total_tokens'] / 1000) * 0.0005; // Rough estimate
                ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></strong></td>
                  <td class="text-end"><?php echo number_format($usage['prompt_tokens']); ?></td>
                  <td class="text-end"><?php echo number_format($usage['completion_tokens']); ?></td>
                  <td class="text-end"><strong><?php echo number_format($usage['total_tokens']); ?></strong></td>
                  <td class="text-center">
                    <span class="<?php echo $trendClass; ?>"><?php echo htmlspecialchars($trend, ENT_QUOTES, 'UTF-8'); ?></span>
                  </td>
                  <td class="text-end">~$<?php echo number_format($estimatedCost, 4); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize main interactive chart
    initMainChart();
    
    // Handle period selection change
    document.getElementById('chartPeriod').addEventListener('change', function() {
        updateMainChart(this.value);
    });
});

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
