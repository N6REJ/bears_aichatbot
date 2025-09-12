<?php
/**
 * Services provider for com_bears_aichatbot (Joomla 5 admin; router optional with cross-namespace support)
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->registerServiceProvider(new MVCFactory('Joomla\\Component\\Bears_aichatbot'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('Joomla\\Component\\Bears_aichatbot'));

        // Try to register a RouterFactory provider (supports J5 and legacy namespaces)
        try {
            if (class_exists('\\Joomla\\Extension\\Service\\Provider\\RouterFactory')) {
                $container->registerServiceProvider(new \Joomla\Extension\Service\Provider\RouterFactory('Joomla\\Component\\Bears_aichatbot'));
            } elseif (class_exists('\\Joomla\\CMS\\Extension\\Service\\Provider\\RouterFactory')) {
                $container->registerServiceProvider(new \Joomla\CMS\Extension\Service\Provider\RouterFactory('Joomla\\Component\\Bears_aichatbot'));
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                // Admin-only component: use the AdministratorApplication directly
                $app = $container->get(AdministratorApplication::class);

                // Provide class aliases to handle factories that look in the base namespace (without Administrator)
                try {
                    // Base-namespace aliases
                    if (!class_exists('Joomla\\Component\\Bears_aichatbot\\Dispatcher\\Dispatcher') && class_exists('Joomla\\Component\\Bears_aichatbot\\Administrator\\Dispatcher\\Dispatcher')) {
                        class_alias('Joomla\\Component\\Bears_aichatbot\\Administrator\\Dispatcher\\Dispatcher', 'Joomla\\Component\\Bears_aichatbot\\Dispatcher\\Dispatcher');
                    }
                    if (!class_exists('Joomla\\Component\\Bears_aichatbot\\Controller\\DisplayController') && class_exists('Joomla\\Component\\Bears_aichatbot\\Administrator\\Controller\\DisplayController')) {
                        class_alias('Joomla\\Component\\Bears_aichatbot\\Administrator\\Controller\\DisplayController', 'Joomla\\Component\\Bears_aichatbot\\Controller\\DisplayController');
                    }
                    // Normalized (no-underscore) namespace aliases some Joomla internals may compute
                    if (!class_exists('Joomla\\Component\\BearsAichatbot\\Dispatcher\\Dispatcher') && class_exists('Joomla\\Component\\Bears_aichatbot\\Administrator\\Dispatcher\\Dispatcher')) {
                        class_alias('Joomla\\Component\\Bears_aichatbot\\Administrator\\Dispatcher\\Dispatcher', 'Joomla\\Component\\BearsAichatbot\\Dispatcher\\Dispatcher');
                    }
                    if (!class_exists('Joomla\\Component\\BearsAichatbot\\Controller\\DisplayController') && class_exists('Joomla\\Component\\Bears_aichatbot\\Administrator\\Controller\\DisplayController')) {
                        class_alias('Joomla\\Component\\Bears_aichatbot\\Administrator\\Controller\\DisplayController', 'Joomla\\Component\\BearsAichatbot\\Controller\\DisplayController');
                    }
                } catch (\Throwable $ignore) {}

                $dispatcherFactory = $container->get(ComponentDispatcherFactoryInterface::class);
                $dispatcher = $dispatcherFactory->createDispatcher($app);

                // Build ComponentInterface implementation with constructor compatibility
                try {
                    // Preferred signature (Joomla 5 variant): dispatcherFactory first
                    $component = new MVCComponent($dispatcherFactory, $container->get(MVCFactoryInterface::class), $app);
                } catch (\Throwable $e) {
                    // Fallback to older signature: MVCFactory first
                    $component = new MVCComponent($container->get(MVCFactoryInterface::class), $app);
                }
                // Ensure dispatcher is set when supported (older/newer Joomla variants may differ)
                if (method_exists($component, 'setDispatcher')) {
                    // Prefer our custom Dispatcher that enforces Admin controller resolution when available
                    try {
                        $customDispatcherClass = 'Joomla\\Component\\Bears_aichatbot\\Dispatcher\\Dispatcher';
                        if (class_exists($customDispatcherClass)) {
                            $factory = $container->get(MVCFactoryInterface::class);
                            $forcedDispatcher = new $customDispatcherClass($app, $dispatcher->getExtension(), $factory);
                            $component->setDispatcher($forcedDispatcher);
                        } else {
                            $component->setDispatcher($dispatcher);
                        }
                    } catch (\Throwable $e) {
                        $component->setDispatcher($dispatcher);
                    }
                }

                // Try to obtain a RouterFactory from the container (either namespace)
                $routerFactory = null;
                if ($container->has('Joomla\\Router\\RouterFactoryInterface')) {
                    $routerFactory = $container->get('Joomla\\Router\\RouterFactoryInterface');
                } elseif ($container->has('Joomla\\CMS\\Router\\RouterFactoryInterface')) {
                    $routerFactory = $container->get('Joomla\\CMS\\Router\\RouterFactoryInterface');
                }

                if ($routerFactory) {
                    try {
                        $router = $routerFactory->createRouter($app, $dispatcher->getExtension());
                        if ($router && method_exists($component, 'setRouter')) {
                            $component->setRouter($router);
                        }
                    } catch (\Throwable $e) {
                        // gracefully skip router if creation fails
                    }
                }

                return $component;
            }
        );
    }
};
