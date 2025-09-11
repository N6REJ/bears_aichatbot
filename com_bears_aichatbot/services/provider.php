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
                $dispatcher = $container->get(ComponentDispatcherFactoryInterface::class)
                    ->createDispatcher('com_bears_aichatbot');
                $dispatcher->setRouter(
                    $container->get(RouterFactoryInterface::class)->createRouter('com_bears_aichatbot')
                );
                return $dispatcher;
            }
        );
    }
};
