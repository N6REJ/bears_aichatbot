<?php
/**
 * Bears AI Chatbot - Keywords View Template
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
use Joomla\CMS\HTML\HTMLHelper;

// Ensure classes are available for IDE
if (!class_exists('Text')) {
    class_alias('Joomla\CMS\Language\Text', 'Text');
}

// Load Bootstrap for better styling
HTMLHelper::_('bootstrap.framework');

/**
 * Variables available in this template (passed from parent scope):
 * @var string $selectedPeriod - Selected time period for filtering
 * @var array $keywords - Array of keyword data
 * @var array $totals - Summary statistics
 * @var array $trending - Trending keywords data  
 * @var string $title - Page title
 * 
 * Available classes (imported above):
 * @var Text - Joomla\CMS\Language\Text for translations
 * @var HTMLHelper - Joomla\CMS\HTML\HTMLHelper for HTML utilities
 */
?>

<div class="com-bears-aichatbot-keywords">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="lead"><?php echo Text::_('COM_BEARS_AICHATBOT_KEYWORDS_DESC'); ?></p>
            </div>
            <div class="time-period-filter">
                <form method="get" action="<?php echo \Joomla\CMS\Router\Route::_('index.php?option=com_bears_aichatbot&view=keywords'); ?>" class="d-flex align-items-center">
                    <input type="hidden" name="option" value="com_bears_aichatbot">
                    <input type="hidden" name="view" value="keywords">
                    <label for="period" class="form-label me-2 mb-0"><?php echo Text::_('COM_BEARS_AICHATBOT_TIME_PERIOD'); ?>:</label>
                    <select name="period" id="period" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="7" <?php echo ($selectedPeriod === '7') ? 'selected' : ''; ?>><?php echo Text::_('COM_BEARS_AICHATBOT_LAST_7_DAYS'); ?></option>
                        <option value="30" <?php echo ($selectedPeriod === '30') ? 'selected' : ''; ?>><?php echo Text::_('COM_BEARS_AICHATBOT_LAST_30_DAYS'); ?></option>
                        <option value="60" <?php echo ($selectedPeriod === '60') ? 'selected' : ''; ?>><?php echo Text::_('COM_BEARS_AICHATBOT_LAST_60_DAYS'); ?></option>
                        <option value="90" <?php echo ($selectedPeriod === '90') ? 'selected' : ''; ?>><?php echo Text::_('COM_BEARS_AICHATBOT_LAST_90_DAYS'); ?></option>
                        <option value="ytd" <?php echo ($selectedPeriod === 'ytd') ? 'selected' : ''; ?>><?php echo Text::_('COM_BEARS_AICHATBOT_YTD'); ?></option>
                        <option value="all" <?php echo ($selectedPeriod === 'all') ? 'selected' : ''; ?>><?php echo Text::_('COM_BEARS_AICHATBOT_ALL_TIME'); ?></option>
                    </select>
                </form>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?php echo number_format($totals['total_keywords']); ?></h4>
                            <p class="card-text"><?php echo Text::_('COM_BEARS_AICHATBOT_TOTAL_KEYWORDS'); ?></p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-tags fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?php echo number_format($totals['total_queries']); ?></h4>
                            <p class="card-text"><?php echo Text::_('COM_BEARS_AICHATBOT_TOTAL_QUERIES'); ?></p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-comments fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?php echo $totals['avg_success_rate']; ?>%</h4>
                            <p class="card-text"><?php echo Text::_('COM_BEARS_AICHATBOT_AVG_SUCCESS_RATE'); ?></p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Trending Keywords (Last 7 Days) -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-fire text-danger"></i>
                        <?php echo Text::_('COM_BEARS_AICHATBOT_TRENDING_KEYWORDS'); ?>
                    </h5>
                    <small class="text-muted"><?php echo Text::_('COM_BEARS_AICHATBOT_LAST_7_DAYS'); ?></small>
                </div>
                <div class="card-body">
                    <?php if (!empty($trending)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($trending as $i => $keyword): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <span class="badge badge-primary badge-pill me-2"><?php echo $i + 1; ?></span>
                                        <strong><?php echo htmlspecialchars($keyword['keyword'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block"><?php echo $keyword['usage_count']; ?> uses</small>
                                        <small class="text-success"><?php echo number_format($keyword['success_rate'], 1); ?>% success</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted"><?php echo Text::_('COM_BEARS_AICHATBOT_NO_TRENDING_KEYWORDS'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- All Keywords Table -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list"></i>
                        <?php echo Text::_('COM_BEARS_AICHATBOT_ALL_KEYWORDS'); ?>
                    </h5>
                    <small class="text-muted"><?php echo Text::_('COM_BEARS_AICHATBOT_TOP_50_KEYWORDS'); ?></small>
                </div>
                <div class="card-body">
                    <?php if (!empty($keywords)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th><?php echo Text::_('COM_BEARS_AICHATBOT_KEYWORD'); ?></th>
                                        <th class="text-center"><?php echo Text::_('COM_BEARS_AICHATBOT_USAGE_COUNT'); ?></th>
                                        <th class="text-center"><?php echo Text::_('COM_BEARS_AICHATBOT_SUCCESS_RATE'); ?></th>
                                        <th class="text-center"><?php echo Text::_('COM_BEARS_AICHATBOT_AVG_TOKENS'); ?></th>
                                        <th class="text-center"><?php echo Text::_('COM_BEARS_AICHATBOT_LAST_USED'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($keywords as $keyword): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($keyword['keyword'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo $keyword['answered_count']; ?> answered, 
                                                    <?php echo $keyword['refused_count']; ?> refused
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-primary badge-pill">
                                                    <?php echo number_format($keyword['usage_count']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php 
                                                $successRate = (float)$keyword['success_rate'];
                                                $badgeClass = $successRate >= 80 ? 'success' : ($successRate >= 60 ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge badge-<?php echo $badgeClass; ?>">
                                                    <?php echo number_format($successRate, 1); ?>%
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <small class="text-muted">
                                                    <?php echo number_format($keyword['avg_tokens'], 1); ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <small class="text-muted">
                                                    <?php 
                                                    $lastUsed = new DateTime($keyword['last_used']);
                                                    $now = new DateTime();
                                                    $diff = $now->diff($lastUsed);
                                                    
                                                    if ($diff->days == 0) {
                                                        echo Text::_('COM_BEARS_AICHATBOT_TODAY');
                                                    } elseif ($diff->days == 1) {
                                                        echo Text::_('COM_BEARS_AICHATBOT_YESTERDAY');
                                                    } elseif ($diff->days < 7) {
                                                        echo $diff->days . ' ' . Text::_('COM_BEARS_AICHATBOT_DAYS_AGO');
                                                    } else {
                                                        echo $lastUsed->format('M j, Y');
                                                    }
                                                    ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <?php echo Text::_('COM_BEARS_AICHATBOT_NO_KEYWORDS_YET'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Information -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-question-circle"></i>
                        <?php echo Text::_('COM_BEARS_AICHATBOT_KEYWORDS_HELP'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><?php echo Text::_('COM_BEARS_AICHATBOT_WHAT_ARE_KEYWORDS'); ?></h6>
                            <p class="small text-muted">
                                <?php echo Text::_('COM_BEARS_AICHATBOT_KEYWORDS_EXPLANATION'); ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6><?php echo Text::_('COM_BEARS_AICHATBOT_HOW_TO_USE'); ?></h6>
                            <ul class="small text-muted">
                                <li><?php echo Text::_('COM_BEARS_AICHATBOT_KEYWORDS_TIP_1'); ?></li>
                                <li><?php echo Text::_('COM_BEARS_AICHATBOT_KEYWORDS_TIP_2'); ?></li>
                                <li><?php echo Text::_('COM_BEARS_AICHATBOT_KEYWORDS_TIP_3'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.com-bears-aichatbot-keywords .card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: none;
    margin-bottom: 1rem;
}

.com-bears-aichatbot-keywords .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.com-bears-aichatbot-keywords .badge-pill {
    border-radius: 50px;
}

.com-bears-aichatbot-keywords .table th {
    border-top: none;
    font-weight: 600;
    font-size: 0.875rem;
}

.com-bears-aichatbot-keywords .table td {
    vertical-align: middle;
}

.com-bears-aichatbot-keywords .list-group-item {
    border-left: none;
    border-right: none;
}

.com-bears-aichatbot-keywords .list-group-item:first-child {
    border-top: none;
}

.com-bears-aichatbot-keywords .list-group-item:last-child {
    border-bottom: none;
}

.com-bears-aichatbot-keywords .time-period-filter {
    min-width: 200px;
}

.com-bears-aichatbot-keywords .time-period-filter .form-select {
    min-width: 150px;
}

.com-bears-aichatbot-keywords .page-header {
    margin-bottom: 2rem;
}

@media (max-width: 768px) {
    .com-bears-aichatbot-keywords .page-header .d-flex {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .com-bears-aichatbot-keywords .time-period-filter {
        margin-top: 1rem;
        width: 100%;
    }
    
    .com-bears-aichatbot-keywords .time-period-filter form {
        width: 100%;
    }
    
    .com-bears-aichatbot-keywords .time-period-filter .form-select {
        width: 100%;
    }
}
</style>
