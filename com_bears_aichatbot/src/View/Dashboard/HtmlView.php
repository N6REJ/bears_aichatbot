<?php
/**
 * HtmlView for the Dashboard view
 */

namespace Joomla\Component\BearsAichatbot\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public string $title = '';
    public array $panels = [];

    public function display($tpl = null)
    {
        // Ensure component language file is loaded
        $lang = $this->getLanguage();
        $lang->load('com_bears_aichatbot', JPATH_ADMINISTRATOR) || $lang->load('com_bears_aichatbot', JPATH_COMPONENT_ADMINISTRATOR);

        $this->title = Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE');
        $this->panels = [
            [
                'title' => Text::_('COM_BEARS_AICHATBOT_PANEL_STATUS'),
                'content' => Text::_('COM_BEARS_AICHATBOT_PANEL_STATUS_DESC'),
            ],
            [
                'title' => Text::_('COM_BEARS_AICHATBOT_PANEL_GETTING_STARTED'),
                'content' => Text::_('COM_BEARS_AICHATBOT_PANEL_GETTING_STARTED_DESC'),
            ],
        ];

        return parent::display($tpl);
    }
}
