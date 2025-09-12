<?php
/**
 * Base-namespace Display controller so core dispatcher can resolve a controller
 * even if Administrator-prefixed classes are not loaded yet.
 */

namespace Joomla\Component\BearsAichatbot\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController as JBaseController;

class DisplayController extends JBaseController
{
    protected $default_view = 'dashboard';
}
