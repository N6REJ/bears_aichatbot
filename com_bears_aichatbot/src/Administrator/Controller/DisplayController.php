<?php
/**
 * Administrator Display controller for com_bears_aichatbot
 */

namespace Joomla\Component\BearsAichatbot\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController as JBaseController;

class DisplayController extends JBaseController
{
    protected $default_view = 'dashboard';

    public function display($cachable = false, $urlparams = [])
    {
        $app   = Factory::getApplication();
        $input = $app->input;
        $viewName = $input->getCmd('view', $this->default_view ?: 'dashboard');
        $input->set('view', $viewName);

        // Force the correct Joomla 5 Administrator view prefix (without trailing \\View)
        $prefix = 'Joomla\\Component\\BearsAichatbot\\Administrator';

        // Normalise view name to expected PSR segment (Usage, Dashboard, ...)
        $name = ucfirst(strtolower($viewName));

        // Defensive autoload guard: ensure the view class is available before calling getView()
        try {
            $viewClass = $prefix . '\\View\\' . $name . '\\HtmlView';
            if (!class_exists($viewClass)) {
                // Base path to src/Administrator
                $base = dirname(__DIR__); // src/Administrator
                $file = $base . DIRECTORY_SEPARATOR . 'View' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'HtmlView.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        } catch (\Throwable $ignore) {}

        // Create the view and attach the corresponding model if present
        $view = $this->getView($name, 'html', $prefix);
        try {
            $modelClass = ucfirst($viewName);
            $model = $this->getModel($modelClass);
            if ($model) {
                $view->setModel($model, true);
            }
        } catch (\Throwable $ignore) {}

        $view->document = Factory::getDocument();
        $view->display();

        return $this;
    }
}
