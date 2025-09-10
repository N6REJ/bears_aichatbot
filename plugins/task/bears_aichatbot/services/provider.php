<?php
/**
 * Service Provider for plg_task_bears_aichatbot
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Extension\Service\Provider\Plugin as PluginProvider;
use Joomla\CMS\Extension\ServiceProviderInterface;
use Joomla\DI\Container;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new PluginProvider('\\PlgTaskBearsAichatbot'));
    }
};
