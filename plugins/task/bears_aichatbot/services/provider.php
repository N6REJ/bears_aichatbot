<?php
/**
 * Service Provider for plg_task_bears_aichatbot
 */
\defined('_JEXEC') or die;

use Joomla\DI\ServiceProviderInterface;
use Joomla\DI\Container;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        // Support both Joomla 4.3+/5 namespaces for the Plugin provider
        $providerClass = null;
        if (class_exists('\\Joomla\\Extension\\Service\\Provider\\Plugin')) {
            $providerClass = '\\Joomla\\Extension\\Service\\Provider\\Plugin';
        } elseif (class_exists('\\Joomla\\CMS\\Extension\\Service\\Provider\\Plugin')) {
            $providerClass = '\\Joomla\\CMS\\Extension\\Service\\Provider\\Plugin';
        } else {
            // Gracefully no-op if provider class is unavailable to avoid breaking the Plugin Manager
            return;
        }
        // Register by group and element (per manual)
        if ($providerClass !== null) {
            $container->registerServiceProvider(new $providerClass('task', 'bears_aichatbot', dirname(__DIR__)));
        }
    }
};
