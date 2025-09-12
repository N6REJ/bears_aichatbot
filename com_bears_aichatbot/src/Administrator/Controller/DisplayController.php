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

        // Use Administrator component namespace as the view prefix to match admin views under src/Administrator/View
        $prefix = 'Joomla\\Component\\BearsAichatbot\\Administrator';

        // Normalise view name for class and for getView()
        $nameClass = ucfirst(strtolower($viewName));
        $name = strtolower($viewName);

        // Defensive autoload guard: ensure the Administrator view class is available before calling getView()
        try {
            $viewClass = $prefix . '\\View\\' . $nameClass . '\\HtmlView';
            if (!class_exists($viewClass)) {
                // Path to src/Administrator
                $base = dirname(__DIR__) ; // points to src/Administrator
                $file = $base . DIRECTORY_SEPARATOR . 'View' . DIRECTORY_SEPARATOR . $nameClass . DIRECTORY_SEPARATOR . 'HtmlView.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        } catch (\Throwable $ignore) {}

        // Use core view resolution to ensure paths and layouts are registered properly
        $basePath = dirname(__DIR__, 3); // administrator/components/com_bears_aichatbot
        try {
            $view = $this->getView($name, 'html', $prefix, ['base_path' => $basePath]);
        } catch (\Throwable $e) {
            // Fallback: try base component namespace views if Administrator-prefixed view could not be resolved
            $fallbackPrefix = 'Joomla\\Component\\BearsAichatbot';

            // Defensive autoload guard for base-namespace HtmlView class
            try {
                $fallbackClass = $fallbackPrefix . '\\View\\' . $nameClass . '\\HtmlView';
                if (!class_exists($fallbackClass)) {
                    $baseSrc = dirname(__DIR__, 2); // points to src
                    $fallbackFile = $baseSrc . DIRECTORY_SEPARATOR . 'View' . DIRECTORY_SEPARATOR . $nameClass . DIRECTORY_SEPARATOR . 'HtmlView.php';
                    if (is_file($fallbackFile)) {
                        require_once $fallbackFile;
                    }
                }
            } catch (\Throwable $ignore) {}

            $view = $this->getView($name, 'html', $fallbackPrefix, ['base_path' => $basePath]);
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
