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
use Joomla\CMS\MVC\Controller\BaseController;

// Load component language
$lang = Factory::getLanguage();
$lang->load('com_bears_aichatbot', JPATH_ADMINISTRATOR);

// Get the controller
$controller = BaseController::getInstance('BearsAichatbot');

// Set default view if none specified
$input = Factory::getApplication()->input;
if (!$input->getCmd('view')) {
    $input->set('view', 'dashboard');
}

// Execute the task
$controller->execute($input->getCmd('task', 'display'));

// Redirect if set by the controller
$controller->redirect();
