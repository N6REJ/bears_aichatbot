<?php
/**
 * Services provider for com_bears_aichatbot
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->registerServiceProvider(new MVCFactory('Joomla\\Component\\Bears_aichatbot'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('Joomla\\Component\\Bears_aichatbot'));
        // Register RouterFactory provider with compatibility for Joomla 4.3+/5 namespaces
        try {
            if (class_exists('\\Joomla\\Extension\\Service\\Provider\\RouterFactory')) {
                $container->registerServiceProvider(new \Joomla\Extension\Service\Provider\RouterFactory('Joomla\\Component\\Bears_aichatbot'));
            } elseif (class_exists('\\Joomla\\CMS\\Extension\\Service\\Provider\\RouterFactory')) {
                $container->registerServiceProvider(new \Joomla\CMS\Extension\Service\Provider\RouterFactory('Joomla\\Component\\Bears_aichatbot'));
            }
        } catch (\Throwable $e) {
            // ignore registration failure to avoid breaking admin
        }

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                // Joomla 5: CMSApplicationInterface may not be registered as a service alias.
                // Resolve the current application using concrete classes or the generic 'app' service.
                if ($container->has(AdministratorApplication::class)) {
                    $app = $container->get(AdministratorApplication::class);
                } elseif ($container->has(SiteApplication::class)) {
                    $app = $container->get(SiteApplication::class);
                } elseif ($container->has('app')) {
                    $app = $container->get('app');
                } else {
                    // As a last resort, attempt to use the global Factory (kept for broad compatibility)
                    $app = \Joomla\CMS\Factory::getApplication();
                }

                $dispatcher = $container->get(ComponentDispatcherFactoryInterface::class)
                    ->createDispatcher($app);

                // Obtain RouterFactory from container using either CMS or Core interface
                $routerFactory = null;
                if ($container->has('Joomla\\CMS\\Router\\RouterFactoryInterface')) {
                    $routerFactory = $container->get('Joomla\\CMS\\Router\\RouterFactoryInterface');
                } elseif ($container->has('Joomla\\Router\\RouterFactoryInterface')) {
                    $routerFactory = $container->get('Joomla\\Router\\RouterFactoryInterface');
                }
                if ($routerFactory !== null) {
                    try {
                        $router = $routerFactory->createRouter($app, $dispatcher->getExtension());
                        if ($router) {
                            $dispatcher->setRouter($router);
                        }
                    } catch (\Throwable $e) {
                        // Gracefully continue without setting a router if creation fails
                    }
                }
                return $dispatcher;
            }
        );
    }
};
