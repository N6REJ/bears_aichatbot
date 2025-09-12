<?php
/**
 * Administrator dispatcher for com_bears_aichatbot
 * Resolves controllers robustly to avoid 404 "Invalid controller class: display".
 */

namespace Joomla\Component\BearsAichatbot\Administrator\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Factory;

class Dispatcher extends ComponentDispatcher
{
    /**
     * Try to resolve a controller class, preferring base namespace controllers
     * and falling back to Administrator namespace, then legacy controller.
     */
    public function getController(string $name, string $client = '', array $config = []): BaseController
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        // Normalize controller name
        if ($name === '' || $name === null) {
            $name = $input->getCmd('task', 'display');
        }
        $name = ucfirst($name);

        // Common defaults for our component
        $config = array_merge(['option' => 'com_bears_aichatbot'], $config);

        // 1) Prefer base namespace (Joomla\\Component\\BearsAichatbot\\Controller)
        $basePrefix = 'Joomla\\Component\\BearsAichatbot\\Controller';
        $baseClass  = $basePrefix . '\\' . $name . 'Controller';
        if (class_exists($baseClass)) {
            try {
                $factory = Factory::getContainer()->get(MVCFactoryInterface::class);
                return $factory->createController($name, $basePrefix, $input, $config);
            } catch (\Throwable $e) {
                return new $baseClass($config, $app, $input);
            }
        }

        // 2) Try Administrator namespace (Joomla\\Component\\BearsAichatbot\\Administrator\\Controller)
        $adminPrefix = 'Joomla\\Component\\BearsAichatbot\\Administrator\\Controller';
        $adminClass  = $adminPrefix . '\\' . $name . 'Controller';
        if (class_exists($adminClass)) {
            try {
                $factory = Factory::getContainer()->get(MVCFactoryInterface::class);
                return $factory->createController($name, $adminPrefix, $input, $config);
            } catch (\Throwable $e) {
                return new $adminClass($config, $app, $input);
            }
        }

        // 3) Legacy fallback (controllers/display.php)
        $legacyClass = $name === 'Display' ? 'Bears_aichatbotControllerDisplay' : 'Bears_aichatbotController' . $name;
        if (class_exists($legacyClass)) {
            return new $legacyClass($config, $app, $input);
        }

        // Defer to parent which may have additional resolution logic
        return parent::getController($name, $client, $config);
    }
}
