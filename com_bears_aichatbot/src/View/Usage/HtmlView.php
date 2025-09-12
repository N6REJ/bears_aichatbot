<?php
/**
 * Usage view (base namespace copy to avoid admin namespace conflicts)
 */

namespace Joomla\Component\BearsAichatbot\View\Usage;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    protected $items;
    protected $pagination;
    protected $state;

    public function display($tpl = null)
    {
        // Base namespace variant; not used by Administrator controller
        ToolbarHelper::title(Text::_('COM_BEARS_AICHATBOT_USAGE_TITLE'), 'list');
        parent::display($tpl);
    }
}
