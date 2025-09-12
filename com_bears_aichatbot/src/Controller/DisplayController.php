<?php
/**
 * Compatibility Display controller in base namespace to satisfy Joomla dispatcher resolution.
 * It proxies to the Administrator controller.
 */

namespace Joomla\Component\Bears_aichatbot\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController as JBaseController;

class DisplayController extends JBaseController
{
    protected $default_view = 'dashboard';
}
