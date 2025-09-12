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
                // Fallback: instantiate directly with constructor signature compatibility
                $factoryObj = null;
                try { $factoryObj = Factory::getContainer()->get(MVCFactoryInterface::class); } catch (\Throwable $ignore) {}
                try {
                    // Preferred J5 order: ($app, ?MVCFactoryInterface, $input, $config)
                    return new $baseClass($app, $factoryObj instanceof MVCFactoryInterface ? $factoryObj : null, $input, $config);
                } catch (\Throwable $e2) {
                    // Legacy order: ($config, ?MVCFactoryInterface, $app, $input)
                    return new $baseClass($config, $factoryObj instanceof MVCFactoryInterface ? $factoryObj : null, $app, $input);
                }
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
                // Fallback: instantiate directly with constructor signature compatibility
                $factoryObj = null;
                try { $factoryObj = Factory::getContainer()->get(MVCFactoryInterface::class); } catch (\Throwable $ignore) {}
                try {
                    // Preferred J5 order: ($app, ?MVCFactoryInterface, $input, $config)
                    return new $adminClass($app, $factoryObj instanceof MVCFactoryInterface ? $factoryObj : null, $input, $config);
                } catch (\Throwable $e2) {
                    // Legacy order: ($config, ?MVCFactoryInterface, $app, $input)
                    return new $adminClass($config, $factoryObj instanceof MVCFactoryInterface ? $factoryObj : null, $app, $input);
                }
            }
        }

        // 3) Legacy fallback (controllers/display.php)
        $legacyClass = $name === 'Display' ? 'Bears_aichatbotControllerDisplay' : 'Bears_aichatbotController' . $name;
        if (class_exists($legacyClass)) {
            // Try legacy instantiation orders for maximum compatibility
            try {
                return new $legacyClass($app, null, $input, $config);
            } catch (\Throwable $e1) {
                try {
                    return new $legacyClass($config, null, $app, $input);
                } catch (\Throwable $e2) {
                    // Final fallback: try original legacy order (may work on some variants)
                    return new $legacyClass($config, $app, $input);
                }
            }
        }

        // Defer to parent which may have additional resolution logic
        return parent::getController($name, $client, $config);
    }
}
