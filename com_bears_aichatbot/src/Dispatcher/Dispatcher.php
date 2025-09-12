<?php
/**
 * Base-namespace dispatcher that delegates to Admin namespace and provides
 * robust controller resolution under the base prefix.
 */

namespace Joomla\Component\BearsAichatbot\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;

class Dispatcher extends \Joomla\Component\BearsAichatbot\Administrator\Dispatcher\Dispatcher
{
    public function getController(string $name, string $client = '', array $config = []): BaseController
    {
        $input = Factory::getApplication()->input;
        if ($name === '' || $name === null) {
            $name = $input->getCmd('task', 'display');
        }
        $name = ucfirst($name);

        // Try base namespace controller first (this class lives under base prefix)
        $prefix = 'Joomla\\Component\\BearsAichatbot\\Controller';
        $class  = $prefix . '\\' . $name . 'Controller';

        if (class_exists($class)) {
            try {
                $factory = Factory::getContainer()->get(MVCFactoryInterface::class);
                return $factory->createController($name, $prefix, $input, array_merge(['option' => 'com_bears_aichatbot'], $config));
            } catch (\Throwable $e) {
                // Fallback: instantiate directly with correct BaseController signature
                $app = Factory::getApplication();
                $factoryObj = null;
                try { $factoryObj = Factory::getContainer()->get(MVCFactoryInterface::class); } catch (\Throwable $ignore) {}
                return new $class($app, $factoryObj instanceof MVCFactoryInterface ? $factoryObj : null, $input, array_merge(['option' => 'com_bears_aichatbot'], $config));
            }
        }

        // Fallback: call parent (Admin dispatcher) which also tries Admin namespace and core logic
        return parent::getController($name, $client, $config);
    }
}
