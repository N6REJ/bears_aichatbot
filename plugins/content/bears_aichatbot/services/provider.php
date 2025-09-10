<?php
/**
 * Service Provider for plg_content_bears_aichatbot
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Extension\Service\Provider\Plugin as PluginProvider;
use Joomla\CMS\Extension\ServiceProviderInterface;
use Joomla\DI\Container;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        // Fully-qualified class per Joomla 5 PSR-4 autoloading for plugins
        $container->registerServiceProvider(new PluginProvider('\\Joomla\\Plugin\\Content\\Bears_aichatbot\\BearsAichatbot'));
    }
};
