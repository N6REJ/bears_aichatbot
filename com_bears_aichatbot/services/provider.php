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
        $container->registerServiceProvider(new MVCFactory('Joomla\\Component\\BearsAichatbot'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('Joomla\\Component\\BearsAichatbot'));

        // Try to register a RouterFactory provider (supports J5 and legacy namespaces)
        try {
            if (class_exists('\\Joomla\\Extension\\Service\\Provider\\RouterFactory')) {
                $container->registerServiceProvider(new \Joomla\Extension\Service\Provider\RouterFactory('Joomla\\Component\\BearsAichatbot'));
            } elseif (class_exists('\\Joomla\\CMS\\Extension\\Service\\Provider\\RouterFactory')) {
                $container->registerServiceProvider(new \Joomla\CMS\Extension\Service\Provider\RouterFactory('Joomla\\Component\\BearsAichatbot'));
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                // Admin-only component: use the AdministratorApplication directly
                $app = $container->get(AdministratorApplication::class);

                // TEMP DEBUG: confirm provider executes on component boot (remove after test)
                throw new \RuntimeException('[PROVIDER TEST] services/provider.php executed', 500);

                // Provide class aliases so both normalised and underscored base namespaces resolve to Admin classes
                try {
                    // Normalised base aliases (preferred in J4.3+/J5)
                    if (!class_exists('Joomla\\Component\\BearsAichatbot\\Dispatcher\\Dispatcher') && class_exists('Joomla\\Component\\BearsAichatbot\\Administrator\\Dispatcher\\Dispatcher')) {
                        class_alias('Joomla\\Component\\BearsAichatbot\\Administrator\\Dispatcher\\Dispatcher', 'Joomla\\Component\\BearsAichatbot\\Dispatcher\\Dispatcher');
                    }
                    if (!class_exists('Joomla\\Component\\BearsAichatbot\\Controller\\DisplayController') && class_exists('Joomla\\Component\\BearsAichatbot\\Administrator\\Controller\\DisplayController')) {
                        class_alias('Joomla\\Component\\BearsAichatbot\\Administrator\\Controller\\DisplayController', 'Joomla\\Component\\BearsAichatbot\\Controller\\DisplayController');
                    }
                    // Underscored base aliases (backward references)
                    if (!class_exists('Joomla\\Component\\Bears_aichatbot\\Dispatcher\\Dispatcher') && class_exists('Joomla\\Component\\BearsAichatbot\\Administrator\\Dispatcher\\Dispatcher')) {
                        class_alias('Joomla\\Component\\BearsAichatbot\\Administrator\\Dispatcher\\Dispatcher', 'Joomla\\Component\\Bears_aichatbot\\Dispatcher\\Dispatcher');
                    }
                    if (!class_exists('Joomla\\Component\\Bears_aichatbot\\Controller\\DisplayController') && class_exists('Joomla\\Component\\BearsAichatbot\\Administrator\\Controller\\DisplayController')) {
                        class_alias('Joomla\\Component\\BearsAichatbot\\Administrator\\Controller\\DisplayController', 'Joomla\\Component\\Bears_aichatbot\\Controller\\DisplayController');
                    }
                } catch (\Throwable $ignore) {}

                // Note: Removed eval-based shim. Base-namespace controller file now exists under src/Controller/DisplayController.php

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

                // Prepare a forced custom dispatcher if available
                $forcedDispatcher = null;
                try {
                    // Ensure the Administrator dispatcher class is available even if the autoloader mapping is not yet registered
                    $adminDispatcherFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Dispatcher' . DIRECTORY_SEPARATOR . 'Dispatcher.php';
                    if (is_file($adminDispatcherFile)) {
                        require_once $adminDispatcherFile;
                    }

                    // Prefer base namespace bridge (will extend Administrator dispatcher) if present
                    $customDispatcherClass = 'Joomla\\Component\\BearsAichatbot\\Dispatcher\\Dispatcher';
                    if (!class_exists($customDispatcherClass) && class_exists('Joomla\\Component\\BearsAichatbot\\Administrator\\Dispatcher\\Dispatcher')) {
                        // Create a runtime alias so factory can resolve base dispatcher
                        class_alias('Joomla\\Component\\BearsAichatbot\\Administrator\\Dispatcher\\Dispatcher', $customDispatcherClass);
                    }

                    if (class_exists($customDispatcherClass)) {
                        $factory = $container->get(MVCFactoryInterface::class);
                        $forcedDispatcher = new $customDispatcherClass($app, $dispatcher->getExtension(), $factory);
                    }
                } catch (\Throwable $ignore) {}

                // Ensure dispatcher is set when supported; otherwise, wrap component to force our dispatcher
                if ($forcedDispatcher instanceof \Joomla\CMS\Dispatcher\ComponentDispatcher) {
                    if (method_exists($component, 'setDispatcher')) {
                        try {
                            $component->setDispatcher($forcedDispatcher);
                        } catch (\Throwable $ignore) {}
                    } else {
                        // Wrap MVCComponent to force our dispatcher via getDispatcher override
                        $mvcFactory = $container->get(MVCFactoryInterface::class);
                        try {
                            $component = new class($dispatcherFactory, $mvcFactory, $app, $forcedDispatcher) extends MVCComponent {
                                private $forcedDispatcher;
                                public function __construct($dispatcherFactory, $mvcFactory, $app, $forced)
                                {
                                    $this->forcedDispatcher = $forced;
                                    parent::__construct($dispatcherFactory, $mvcFactory, $app);
                                }
                                public function getDispatcher()
                                {
                                    return $this->forcedDispatcher;
                                }
                            };
                        } catch (\Throwable $e) {
                            // Fallback for older constructor signature
                            $component = new class($mvcFactory, $app, $forcedDispatcher) extends MVCComponent {
                                private $forcedDispatcher;
                                public function __construct($mvcFactory, $app, $forced)
                                {
                                    $this->forcedDispatcher = $forced;
                                    parent::__construct($mvcFactory, $app);
                                }
                                public function getDispatcher()
                                {
                                    return $this->forcedDispatcher;
                                }
                            };
                        }
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
