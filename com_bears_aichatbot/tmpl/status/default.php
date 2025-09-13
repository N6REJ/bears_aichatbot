<?php
/**
 * Bears AI Chatbot - System Status View
 *
 * @version 2025.09.13.1
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
/** @var string $diagnosticInfo */
/** @var array $moduleConfig */

// Provide defaults in case variables aren't set
$title = $title ?? Text::_('COM_BEARS_AICHATBOT_STATUS_TITLE');
$panels = $panels ?? [];
$diagnosticInfo = $diagnosticInfo ?? '';
$moduleConfig = $moduleConfig ?? [];
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

    <!-- Collection Diagnostics Section -->
    <?php if ($diagnosticInfo): ?>
    <div class="diagnostics-section mb-4">
      <div class="card">
        <div class="card-header">
          <h3 class="mb-0">
            <i class="fas fa-stethoscope"></i> 
            <?php echo Text::_('COM_BEARS_AICHATBOT_COLLECTION_DIAGNOSTICS'); ?>
          </h3>
        </div>
        <div class="card-body">
          <div class="diagnostic-info">
            <?php echo $diagnosticInfo; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions Section -->
    <div class="quick-actions-section mb-4">
      <div class="card">
        <div class="card-header">
          <h3 class="mb-0">
            <i class="fas fa-bolt"></i> 
            <?php echo Text::_('COM_BEARS_AICHATBOT_QUICK_ACTIONS'); ?>
          </h3>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12 col-md-4">
              <a href="<?php echo \Joomla\CMS\Router\Route::_('index.php?option=com_bears_aichatbot&view=collections'); ?>" class="btn btn-outline-primary btn-lg w-100">
                <i class="fas fa-database"></i><br>
                <small><?php echo Text::_('COM_BEARS_AICHATBOT_MANAGE_COLLECTIONS'); ?></small>
              </a>
            </div>
            <div class="col-12 col-md-4">
              <a href="<?php echo \Joomla\CMS\Router\Route::_('index.php?option=com_bears_aichatbot&view=usage'); ?>" class="btn btn-outline-success btn-lg w-100">
                <i class="fas fa-chart-line"></i><br>
                <small><?php echo Text::_('COM_BEARS_AICHATBOT_VIEW_USAGE_ANALYTICS'); ?></small>
              </a>
            </div>
            <div class="col-12 col-md-4">
              <a href="<?php echo \Joomla\CMS\Router\Route::_('index.php?option=com_modules&view=modules&filter[search]=bears'); ?>" class="btn btn-outline-info btn-lg w-100">
                <i class="fas fa-cog"></i><br>
                <small><?php echo Text::_('COM_BEARS_AICHATBOT_MODULE_SETTINGS'); ?></small>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- System Information -->
    <div class="system-info-section">
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <div class="card">
            <div class="card-header">
              <strong><?php echo Text::_('COM_BEARS_AICHATBOT_COMPONENT_INFO'); ?></strong>
            </div>
            <div class="card-body">
              <table class="table table-sm table-borderless">
                <tr>
                  <td><strong><?php echo Text::_('COM_BEARS_AICHATBOT_VERSION'); ?>:</strong></td>
                  <td>2025.09.13.6</td>
                </tr>
                <tr>
                  <td><strong><?php echo Text::_('COM_BEARS_AICHATBOT_JOOMLA_VERSION'); ?>:</strong></td>
                  <td><?php echo JVERSION; ?></td>
                </tr>
                <tr>
                  <td><strong><?php echo Text::_('COM_BEARS_AICHATBOT_PHP_VERSION'); ?>:</strong></td>
                  <td><?php echo PHP_VERSION; ?></td>
                </tr>
              </table>
            </div>
          </div>
        </div>
        
        <div class="col-12 col-md-6">
          <div class="card">
            <div class="card-header">
              <strong><?php echo Text::_('COM_BEARS_AICHATBOT_SUPPORT_INFO'); ?></strong>
            </div>
            <div class="card-body">
              <p class="mb-2">
                <strong><?php echo Text::_('COM_BEARS_AICHATBOT_AUTHOR'); ?>:</strong> N6REJ<br>
                <strong><?php echo Text::_('COM_BEARS_AICHATBOT_EMAIL'); ?>:</strong> troy@hallhome.us<br>
                <strong><?php echo Text::_('COM_BEARS_AICHATBOT_WEBSITE'); ?>:</strong> 
                <a href="https://www.hallhome.us" target="_blank">www.hallhome.us</a>
              </p>
              <small class="text-muted">
                <?php echo Text::_('COM_BEARS_AICHATBOT_LICENSE_INFO'); ?>
              </small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
