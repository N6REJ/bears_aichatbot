<?php
/**
 * Dashboard view
 */

namespace Joomla\Component\Bears_aichatbot\Administrator\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    protected $filters = [];

    public function display($tpl = null)
    {
        $this->filters = $this->getModel()->getFilters();
        ToolbarHelper::title(Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE'), 'chart');
        parent::display($tpl);
    }
}
