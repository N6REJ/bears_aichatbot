<?php
/**
 * Administrator Display Controller for com_bears_aichatbot
 */

namespace Joomla\Component\BearsAichatbot\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
    protected $default_view = 'dashboard';

    public function display($cachable = false, $urlparams = [])
    {
        // Use Joomla 5 BaseController::display which resolves view/layout automatically
        $this->input->set('view', $this->input->getCmd('view', $this->default_view));
        return parent::display($cachable, $urlparams);
    }
}
