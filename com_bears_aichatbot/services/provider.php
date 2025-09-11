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
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\Router\RouterFactoryInterface;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->registerServiceProvider(new MVCFactory('Joomla\\Component\\Bears_aichatbot'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('Joomla\\Component\\Bears_aichatbot'));
        $container->registerServiceProvider(new RouterFactory('Joomla\\Component\\Bears_aichatbot'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $app = $container->get(CMSApplicationInterface::class);
                $dispatcher = $container->get(ComponentDispatcherFactoryInterface::class)
                    ->createDispatcher($app, 'com_bears_aichatbot');
                $router = $container->get(RouterFactoryInterface::class)
                    ->createRouter($app, $dispatcher->getExtension());
                $dispatcher->setRouter($router);
                return $dispatcher;
            }
        );
    }
};
