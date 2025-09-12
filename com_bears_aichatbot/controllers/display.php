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

// Provide additional legacy alias expected by some Joomla dispatcher variants
if (!class_exists('BearsAichatbotControllerDisplay')) {
    class_alias('Bears_aichatbotControllerDisplay', 'BearsAichatbotControllerDisplay');
}

// Ensure a namespaced base controller class exists when legacy file is loaded
if (!class_exists('Joomla\\Component\\BearsAichatbot\\Controller\\DisplayController') && class_exists('BearsAichatbotControllerDisplay')) {
    class_alias('BearsAichatbotControllerDisplay', 'Joomla\\Component\\BearsAichatbot\\Controller\\DisplayController');
}
