<?php
/**
 * Base-namespace Display controller so core dispatcher can resolve a controller
 * even if Administrator-prefixed classes are not loaded yet.
 */

namespace Joomla\Component\BearsAichatbot\Controller;

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

        // Normalise view name for class and for getView()
        $nameClass = ucfirst(strtolower($viewName));
        $name = strtolower($viewName);

        // Defensive autoload guard: ensure the Admin view class is available
        try {
            $viewClass = $prefix . '\\View\\' . $nameClass . '\\HtmlView';
            if (!class_exists($viewClass)) {
                $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Administrator'; // points to src/Administrator
                $file = $base . DIRECTORY_SEPARATOR . 'View' . DIRECTORY_SEPARATOR . $nameClass . DIRECTORY_SEPARATOR . 'HtmlView.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        } catch (\Throwable $ignore) {}

        // Prefer manual view instantiation to avoid getView() resolution issues
        $view = null;
        $viewClass = $prefix . '\\View\\' . $nameClass . '\\HtmlView';
        if (class_exists($viewClass)) {
            try {
                $view = new $viewClass();
            } catch (\Throwable $e) {
                $view = null;
            }
        }
        // Fallback to core resolution if manual instantiation failed
        if ($view === null) {
            $view = $this->getView($name, 'html', $prefix);
        }

        // Attach the corresponding model if present
        try {
            $modelClass = $nameClass;
            $model = $this->getModel($modelClass);
            if ($model) {
                $view->setModel($model, true);
            }
        } catch (\Throwable $ignore) {}

        // Provide document and render
        $view->document = Factory::getDocument();
        $view->display();

        return $this;
    }
}
