<?php
/**
 * Dashboard view (base namespace copy to avoid admin namespace conflicts)
 */

namespace Joomla\Component\BearsAichatbot\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    protected $filters = [];

    public function display($tpl = null)
    {
        // Base namespace variant; not used by Administrator controller
        ToolbarHelper::title(Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE'), 'chart');
        parent::display($tpl);
    }
}
