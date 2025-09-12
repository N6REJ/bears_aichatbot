<?php
/**
 * Dispatcher for com_bears_aichatbot (administrator)
 */

namespace Joomla\Component\Bears_aichatbot\Administrator\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Factory;

class Dispatcher extends ComponentDispatcher
{
    /**
     * Override to be tolerant of namespace resolution differences.
     * Try Admin controller namespace explicitly before deferring to parent.
     */
    public function getController(string $name, string $client = '', array $config = []): BaseController
    {
        $input = Factory::getApplication()->input;

        // Normalise controller name to StudlyCaps; derive from task if missing
        if ($name === '' || $name === null) {
            $task = $input->getCmd('task', 'display');
            $name = $task;
        }
        $name = ucfirst($name);

        $prefix = 'Joomla\\Component\\Bears_aichatbot\\Administrator\\Controller';
        $class  = $prefix . '\\' . $name . 'Controller';

        if (class_exists($class)) {
            try {
                $factory = Factory::getContainer()->get(MVCFactoryInterface::class);
                return $factory->createController($name, $prefix, $input, array_merge(['option' => 'com_bears_aichatbot'], $config));
            } catch (\Throwable $e) {
                // Fallback to direct instantiation if factory path fails
                return new $class(array_merge(['option' => 'com_bears_aichatbot'], $config), Factory::getApplication(), $input);
            }
        }

        // Defer to core resolution (will throw if still not found)
        return parent::getController($name, $client, $config);
    }
}
