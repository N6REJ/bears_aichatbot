<?php
/**
 * Dashboard view
 */

namespace Joomla\Component\BearsAichatbot\Administrator\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\BearsAichatbot\Administrator\Model\DashboardModel;

class HtmlView extends BaseHtmlView
{
    protected $filters = [];

    public function display($tpl = null)
    {
        // Try to obtain the model via the view registry first
        $model = null;
        try { $model = $this->getModel(); } catch (\Throwable $ignore) { $model = null; }
        // Try by explicit name
        if ($model === null) {
            try { $model = $this->getModel('Dashboard'); } catch (\Throwable $ignore) { $model = null; }
        }
        // Final fallback: instantiate the model directly
        if ($model === null && class_exists(DashboardModel::class)) {
            try { $model = new DashboardModel(); } catch (\Throwable $ignore) { $model = null; }
        }

        if ($model) {
            try { $this->filters = $model->getFilters(); } catch (\Throwable $ignore) { $this->filters = []; }
        }

        // Ensure filters array has expected keys to avoid template notices
        $this->filters = array_merge([
            'from' => '',
            'to' => '',
            'group' => 'day',
            'module_id' => 0,
            'model' => '',
            'collection_id' => '',
        ], is_array($this->filters) ? $this->filters : []);

        ToolbarHelper::title(Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE'), 'chart');
        parent::display($tpl);
    }
}
