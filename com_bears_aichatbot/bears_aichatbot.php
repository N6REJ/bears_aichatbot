<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.09.13
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

// Load component language
$lang = Factory::getLanguage();
$lang->load('com_bears_aichatbot', JPATH_ADMINISTRATOR);

// Load component CSS
HTMLHelper::_('stylesheet', 'com_bears_aichatbot/admin.css', ['version' => 'auto', 'relative' => true]);

// Get the requested view
$input = Factory::getApplication()->input;
$view = $input->getCmd('view', 'dashboard');

// For now, we only support the dashboard view
if ($view !== 'dashboard') {
    $view = 'dashboard';
}

// Set page title
$document = Factory::getDocument();
$document->setTitle(Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE'));

// Prepare dashboard data - use the exact variable names the template expects
$title = Text::_('COM_BEARS_AICHATBOT_DASHBOARD_TITLE');
$panels = [
    [
        'title' => Text::_('COM_BEARS_AICHATBOT_PANEL_STATUS'),
        'content' => Text::_('COM_BEARS_AICHATBOT_PANEL_STATUS_DESC'),
    ],
    [
        'title' => Text::_('COM_BEARS_AICHATBOT_PANEL_GETTING_STARTED'),
        'content' => Text::_('COM_BEARS_AICHATBOT_PANEL_GETTING_STARTED_DESC'),
    ],
];

// Include the template directly - variables will be in scope
require JPATH_COMPONENT_ADMINISTRATOR . '/tmpl/dashboard/default.php';
