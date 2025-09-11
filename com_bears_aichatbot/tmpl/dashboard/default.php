<?php
/** @var Joomla\Component\Bears_aichatbot\Administrator\View\Dashboard\HtmlView $this */
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

$wa = $this->document->getWebAssetManager();
$base = rtrim(JUri::root(), '/') . '/administrator/components/com_bears_aichatbot';
$wa->registerAndUseStyle('com_bears_aichatbot.dashboard', $base . '/media/css/dashboard.css');
$wa->registerAndUseScript('com_bears_aichatbot.chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], ['defer' => true]);
$wa->registerAndUseScript('com_bears_aichatbot.dashboard', $base . '/media/js/dashboard.js', ['com_bears_aichatbot.chartjs'], ['defer' => true]);

$filters = $this->filters;
$token = JSession::getFormToken();
?>
<div class="container-fluid">
  <ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link active" href="index.php?option=com_bears_aichatbot">Dashboard</a></li>
    <li class="nav-item"><a class="nav-link" href="index.php?option=com_bears_aichatbot&view=usage">Usage</a></li>
  </ul>
  <div class="card mb-3">
    <div class="card-header"><?php echo Text::_('COM_BEARS_AICHATBOT_FILTERS'); ?></div>
    <div class="card-body">
      <form id="baichatbot-filters" class="form-inline row g-2">
        <div class="col-sm-3">
          <label class="form-label"><?php echo Text::_('COM_BEARS_AICHATBOT_DATE_RANGE'); ?></label>
          <div class="input-group">
            <input type="date" class="form-control" name="from" value="<?php echo htmlspecialchars($filters['from']); ?>">
            <span class="input-group-text">→</span>
            <input type="date" class="form-control" name="to" value="<?php echo htmlspecialchars($filters['to']); ?>">
          </div>
        </div>
        <div class="col-sm-2">
          <label class="form-label"><?php echo Text::_('COM_BEARS_AICHATBOT_GROUP_BY'); ?></label>
          <select class="form-select" name="group">
            <option value="day" <?php echo $filters['group']==='day'?'selected':''; ?>><?php echo Text::_('COM_BEARS_AICHATBOT_GROUP_DAY'); ?></option>
            <option value="week" <?php echo $filters['group']==='week'?'selected':''; ?>><?php echo Text::_('COM_BEARS_AICHATBOT_GROUP_WEEK'); ?></option>
            <option value="month" <?php echo $filters['group']==='month'?'selected':''; ?>><?php echo Text::_('COM_BEARS_AICHATBOT_GROUP_MONTH'); ?></option>
          </select>
        </div>
        <div class="col-sm-2">
          <label class="form-label"><?php echo Text::_('COM_BEARS_AICHATBOT_MODULE_ID'); ?></label>
          <select class="form-select" name="module_id" id="flt-module"></select>
        </div>
        <div class="col-sm-2">
          <label class="form-label"><?php echo Text::_('COM_BEARS_AICHATBOT_MODEL'); ?></label>
          <select class="form-select" name="model" id="flt-model"></select>
        </div>
        <div class="col-sm-3">
          <label class="form-label"><?php echo Text::_('COM_BEARS_AICHATBOT_COLLECTION_ID'); ?></label>
          <select class="form-select" name="collection_id" id="flt-collection"></select>
        </div>
        <div class="col-12 mt-2">
          <button type="button" class="btn btn-primary" id="baichatbot-apply"><?php echo Text::_('COM_BEARS_AICHATBOT_APPLY'); ?></button>
          <button type="button" class="btn btn-secondary" id="baichatbot-reset"><?php echo Text::_('COM_BEARS_AICHATBOT_RESET'); ?></button>
          <a class="btn btn-outline-secondary" id="baichatbot-export" href="#" target="_blank"><?php echo Text::_('COM_BEARS_AICHATBOT_EXPORT_CSV'); ?></a>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <div class="h6 text-muted"><?php echo Text::_('COM_BEARS_AICHATBOT_KPI_REQUESTS'); ?></div>
          <div class="display-6" id="kpi-requests">0</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <div class="h6 text-muted"><?php echo Text::_('COM_BEARS_AICHATBOT_KPI_TOTAL'); ?></div>
          <div class="display-6" id="kpi-total">0</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <div class="h6 text-muted">Total Cost (USD)</div>
          <div class="display-6" id="kpi-cost">$0.000000</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <div class="h6 text-muted"><?php echo Text::_('COM_BEARS_AICHATBOT_KPI_PROMPT'); ?></div>
          <div class="display-6" id="kpi-prompt">0</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <div class="h6 text-muted"><?php echo Text::_('COM_BEARS_AICHATBOT_KPI_COMPLETION'); ?></div>
          <div class="display-6" id="kpi-completion">0</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">Tokens Over Time</div>
        <div class="card-body">
          <canvas id="chart-usage" height="120"></canvas>
        </div>
      </div>
      <div class="card mt-3">
        <div class="card-header">Requests and Errors</div>
        <div class="card-body">
          <canvas id="chart-requests" height="120"></canvas>
        </div>
      </div>
      <div class="card mt-3">
        <div class="card-header">Spend Over Time (USD)</div>
        <div class="card-body">
          <canvas id="chart-spend" height="120"></canvas>
        </div>
      </div>
      <div class="card mt-3">
        <div class="card-header">Latency (ms)</div>
        <div class="card-body">
          <canvas id="chart-latency" height="120"></canvas>
        </div>
      </div>
      <div class="card mt-3">
        <div class="card-header">Token Distribution</div>
        <div class="card-body">
          <canvas id="chart-hist" height="120"></canvas>
        </div>
      </div>
      <div class="card mt-3">
        <div class="card-header">Outcomes</div>
        <div class="card-body">
          <canvas id="chart-outcomes" height="120"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header">Collection</div>
        <div class="card-body">
          <div class="h6 text-muted"><?php echo Text::_('COM_BEARS_AICHATBOT_KPI_RETRIEVED'); ?></div>
          <div class="h3" id="kpi-retrieved">0</div>
          <hr />
          <div class="h6 text-muted"><?php echo Text::_('COM_BEARS_AICHATBOT_KPI_DOCS'); ?></div>
          <div class="h3" id="kpi-docs">0</div>
          <div id="collection-meta" class="mt-3 small text-muted"></div>
          <div class="mt-2">
            <button type="button" id="btn-rebuild-collection" class="btn btn-danger btn-sm">Rebuild Document Collection</button>
            <span id="rebuild-status" class="ms-2 text-muted small"></span>
          </div>
          <hr />
          <div class="h6 text-muted">Collection Size (History)</div>
          <canvas id="chart-collection" height="120"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
window.BAICHATBOT = {
  base: '<?php echo addslashes(JUri::base()); ?>',
  option: 'com_bears_aichatbot',
  token: '<?php echo $token; ?>'
};
</script>
