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

// Also provide a base-namespace Dispatcher for environments resolving without the Administrator segment
namespace Joomla\Component\Bears_aichatbot\Dispatcher {
    \defined('_JEXEC') or die;
    use Joomla\CMS\Dispatcher\ComponentDispatcher as JComponentDispatcher;
    use Joomla\CMS\MVC\Controller\BaseController as JBaseController;
    use Joomla\CMS\MVC\Factory\MVCFactoryInterface as JMVCFactoryInterface;
    use Joomla\CMS\Factory as JFactory;

    class Dispatcher extends JComponentDispatcher
    {
        public function getController(): JBaseController
        {
            $input  = JFactory::getApplication()->input;
            $task   = $input->getCmd('task', 'display');
            $name   = ucfirst($task);
            $prefix = 'Joomla\\Component\\Bears_aichatbot\\Administrator\\Controller';
            $class  = $prefix . '\\' . $name . 'Controller';

            if (class_exists($class)) {
                try {
                    $factory = JFactory::getContainer()->get(JMVCFactoryInterface::class);
                    return $factory->createController($name, $prefix, $input, ['option' => 'com_bears_aichatbot']);
                } catch (\Throwable $e) {
                    return new $class(['option' => 'com_bears_aichatbot'], JFactory::getApplication(), $input);
                }
            }

            return parent::getController();
        }
    }
}
