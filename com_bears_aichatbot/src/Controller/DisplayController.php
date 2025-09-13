<?php
/**
 * Display Controller for com_bears_aichatbot
 */

namespace Joomla\Component\BearsAichatbot\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
    protected $default_view = 'dashboard';

    public function display($cachable = false, $urlparams = [])
    {
        $this->input->set('view', $this->input->getCmd('view', $this->default_view));
        return parent::display($cachable, $urlparams);
    }
}
