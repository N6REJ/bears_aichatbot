<?php
/**
 * Entry point for com_bears_aichatbot (administrator)
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

$app = Factory::getApplication();

// Permissions
$user = $app->getIdentity();
if (!$user->authorise('core.manage', 'com_bears_aichatbot')) {
    throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
}

$controller = BaseController::getInstance('Bears_aichatbot');
$controller->execute($app->input->get('task'));
$controller->redirect();
