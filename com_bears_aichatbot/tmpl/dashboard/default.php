<?php
\defined('_JEXEC') or die;
/** @var Joomla\Component\BearsAichatbot\Administrator\View\Dashboard\HtmlView $this */
use Joomla\CMS\Language\Text;
?>
<div class="com-bears-aichatbot">
  <div class="container-fluid">
    <h1 class="page-title">
      <?php echo htmlspecialchars($this->title ?: Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE'), ENT_QUOTES, 'UTF-8'); ?>
    </h1>

    <div class="row g-3">
      <?php foreach ($this->panels as $panel) : ?>
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
