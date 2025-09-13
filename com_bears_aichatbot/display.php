<?php
/**
 * Default display entry point for com_bears_aichatbot
 * This file is required for Joomla to resolve the display controller
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

// Get the application
$app = Factory::getApplication();

// Get the controller
$controller = BaseController::getInstance('BearsAichatbot');

// Execute the task
$controller->execute($app->input->getCmd('task', 'display'));

// Redirect if set by the controller
$controller->redirect();
