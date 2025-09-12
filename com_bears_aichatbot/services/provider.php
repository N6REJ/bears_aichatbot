<?php
/**
 * Services provider for com_bears_aichatbot (Joomla 5 native, admin-only)
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
use Joomla\Extension\Service\Provider\RouterFactory;
use Joomla\Router\RouterFactoryInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->registerServiceProvider(new MVCFactory('Joomla\\Component\\Bears_aichatbot'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('Joomla\\Component\\Bears_aichatbot'));
        $container->registerServiceProvider(new RouterFactory('Joomla\\Component\\Bears_aichatbot'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                // Admin-only component: use the AdministratorApplication directly
                $app = $container->get(AdministratorApplication::class);

                $dispatcher = $container->get(ComponentDispatcherFactoryInterface::class)
                    ->createDispatcher($app);

                // Joomla 5 expects a ComponentInterface implementation (MVCComponent)
                $component = new MVCComponent($container->get(MVCFactoryInterface::class), $app);
                $component->setDispatcher($dispatcher);

                // Create and set the router using the J5 RouterFactory
                $router = $container->get(RouterFactoryInterface::class)
                    ->createRouter($app, $dispatcher->getExtension());
                if (method_exists($component, 'setRouter')) {
                    $component->setRouter($router);
                }

                return $component;
            }
        );
    }
};
