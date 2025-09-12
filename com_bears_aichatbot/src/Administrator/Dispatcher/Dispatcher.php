<?php
/**
 * Administrator Dispatcher for com_bears_aichatbot
 */

namespace Joomla\Component\BearsAichatbot\Administrator\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcher;

class Dispatcher extends ComponentDispatcher
{
    /**
     * Ensure we can always resolve a controller for the default task
     */
    protected string $defaultController = 'display';

    /**
     * Try to resolve the controller under the Administrator prefix first,
     * then fall back to the base namespace, and finally to parent logic.
     */
    public function getController($name)
    {
        $name = $name ?: $this->defaultController;

        // Prefer Administrator namespace
        $prefix = 'Joomla\\Component\\BearsAichatbot\\Administrator';
        try {
            $controller = $this->factory->createController($name, $prefix);
            if ($controller) {
                return $controller;
            }
        } catch (\Throwable $ignore) {}

        // Fallback to base component namespace
        $prefix = 'Joomla\\Component\\BearsAichatbot';
        try {
            $controller = $this->factory->createController($name, $prefix);
            if ($controller) {
                return $controller;
            }
        } catch (\Throwable $ignore) {}

        return parent::getController($name);
    }
}
