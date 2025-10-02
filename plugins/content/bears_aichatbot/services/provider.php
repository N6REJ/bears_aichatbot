<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.10.02.2
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
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

        // Register by group/element and optionally path, depending on constructor signature
        if ($providerClass !== null) {
            try {
                $ref  = new \ReflectionClass($providerClass);
                $ctor = $ref->getConstructor();
                $n    = $ctor ? $ctor->getNumberOfParameters() : 0;
                if ($n >= 3) {
                    $container->registerServiceProvider(new $providerClass('content', 'bears_aichatbot', dirname(__DIR__)));
                } else {
                    $container->registerServiceProvider(new $providerClass('content', 'bears_aichatbot'));
                }
            } catch (\Throwable $e) {
                // Graceful no-op to avoid breaking the Plugin Manager
            }
        }
    }
};
