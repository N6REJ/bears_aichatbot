<?php
/**
 * Entry point for com_bears_aichatbot
 * This ensures Joomla can always find a controller
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
