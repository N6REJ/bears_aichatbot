<?php
/** @var Joomla\Component\Bears_aichatbot\Administrator\View\Usage\HtmlView $this */
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

$items = $this->items ?? [];
$pagination = $this->pagination ?? null;
$state = $this->state ?? null;
?>
<div class="container-fluid">
  <table class="table table-striped">
    <thead>
      <tr>
        <th>created_at</th>
        <th>module_id</th>
        <th>model</th>
        <th>collection_id</th>
        <th>prompt</th>
        <th>completion</th>
        <th>total</th>
        <th>retrieved</th>
        <th>status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $r): ?>
      <tr>
        <td><?php echo htmlspecialchars($r->created_at); ?></td>
        <td><?php echo (int)$r->module_id; ?></td>
        <td><?php echo htmlspecialchars($r->model); ?></td>
        <td><?php echo htmlspecialchars($r->collection_id); ?></td>
        <td><?php echo (int)$r->prompt_tokens; ?></td>
        <td><?php echo (int)$r->completion_tokens; ?></td>
        <td><?php echo (int)$r->total_tokens; ?></td>
        <td><?php echo htmlspecialchars($r->retrieved); ?></td>
        <td><?php echo htmlspecialchars($r->status_code); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($pagination): ?>
  <div class="pagination">
    <?php echo $pagination->getListFooter(); ?>
  </div>
  <?php endif; ?>
</div>
