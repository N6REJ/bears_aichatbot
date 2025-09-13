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

namespace Joomla\Component\BearsAichatbot\Administrator\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public string $title = '';
    public array $panels = [];

    public function display($tpl = null)
    {
        // Ensure component language file is loaded (standard Joomla 5 behavior)
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
