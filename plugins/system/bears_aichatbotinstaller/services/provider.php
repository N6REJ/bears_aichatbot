<?php
/**
 * Service Provider for plg_system_bears_aichatbotinstaller
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
            throw new \RuntimeException('Joomla Plugin service provider class not found.');
        }

        $container->registerServiceProvider(new $providerClass('system', 'bears_aichatbotinstaller', dirname(__DIR__)));
    }
};
