<?php
/**
 * Usage view
 */

namespace Joomla\Component\Bears_aichatbot\Administrator\View\Usage;

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
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        ToolbarHelper::title(Text::_('COM_BEARS_AICHATBOT_USAGE_TITLE'), 'list');
        parent::display($tpl);
    }
}
