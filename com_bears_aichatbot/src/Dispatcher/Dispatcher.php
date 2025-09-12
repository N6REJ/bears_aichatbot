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
    public function getController(): BaseController
    {
        $input  = Factory::getApplication()->input;
        $task   = $input->getCmd('task', 'display');
        $name   = ucfirst($task);
        $prefix = 'Joomla\\Component\\Bears_aichatbot\\Administrator\\Controller';
        $class  = $prefix . '\\' . $name . 'Controller';

        if (class_exists($class)) {
            try {
                $factory = Factory::getContainer()->get(MVCFactoryInterface::class);
                return $factory->createController($name, $prefix, $input, ['option' => 'com_bears_aichatbot']);
            } catch (\Throwable $e) {
                // Fallback to direct instantiation if factory path fails
                return new $class(['option' => 'com_bears_aichatbot'], Factory::getApplication(), $input);
            }
        }

        // Defer to core resolution (will throw if still not found)
        return parent::getController();
    }
}
