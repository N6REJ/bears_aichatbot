<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.09.13.7
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

// Provide defaults in case variables aren't set
$title = $title ?? Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE');
$panels = $panels ?? [];
?>
<div class="com-bears-aichatbot">
  <div class="container-fluid">
    <h1 class="page-title">
      <?php echo htmlspecialchars($title ?: Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE'), ENT_QUOTES, 'UTF-8'); ?>
    </h1>

    <div class="row g-3">
      <?php foreach ($panels as $panel) : ?>
        <div class="col-12 col-md-6">
          <div class="card">
            <div class="card-header">
              <strong><?php echo htmlspecialchars($panel['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="card-body">
              <p class="mb-0"><?php echo htmlspecialchars($panel['content'], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
