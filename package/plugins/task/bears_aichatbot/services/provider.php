<?php
/**
 * Joomla 5 Service Provider for plg_task_aichatbot
 */
use Joomla\DI\Container;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Extension\Service\Provider\Plugin as PluginProvider;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

return new class implements Joomla\CMS\Extension\ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->registerServiceProvider(new PluginProvider('\\PlgTaskAichatbot'));
    }
};
