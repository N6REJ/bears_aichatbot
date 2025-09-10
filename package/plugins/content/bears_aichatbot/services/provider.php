<?php
/**
 * Joomla 5 Service Provider for plg_content_bears_aichatbot
 */
defined('_JEXEC') or die;

use Joomla\DI\Container;
use Joomla\CMS\Extension\Service\Provider\Plugin as PluginProvider;

return new class implements Joomla\CMS\Extension\ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->registerServiceProvider(new PluginProvider('\\PlgContentBearsAichatbot'));
    }
};
