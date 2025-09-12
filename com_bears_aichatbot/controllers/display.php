<?php
/**
 * Legacy fallback Display controller for com_bears_aichatbot
 * Provides compatibility for environments resolving legacy controllers
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

class Bears_aichatbotControllerDisplay extends BaseController
{
    protected $default_view = 'dashboard';
}
