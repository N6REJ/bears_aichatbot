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
    protected $default_view = 'hello';

    public function display($cachable = false, $urlparams = [])
    {
        $app   = $this->getApplication() instanceof AdministratorApplication
            ? $this->getApplication()
            : Factory::getApplication();

        $input = $app->getInput();
        $viewName = $input->getCmd('view', $this->default_view);

        // Resolve the view with the Administrator prefix
        $prefix   = 'Joomla\\Component\\BearsAichatbot\\Administrator';
        $view     = $this->getView($viewName, 'html', $prefix, [
            'base_path' => JPATH_COMPONENT_ADMINISTRATOR,
        ]);

        $view->document = Factory::getDocument();
        $view->setLayout('default');
        $view->display();

        return $this;
    }
}
