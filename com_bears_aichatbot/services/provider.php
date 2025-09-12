<?php
/**
 * Joomla 5 native service provider for com_bears_aichatbot (Administrator only)
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        // Register MVC factory and dispatcher factory for our base namespace
        $container->registerServiceProvider(new MVCFactory('Joomla\\Component\\BearsAichatbot'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('Joomla\\Component\\BearsAichatbot'));

        // Register the component service (Administrator only)
        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $app        = $container->get(AdministratorApplication::class);
                $mvcFactory = $container->get(MVCFactoryInterface::class);
                $dispatcher = $container->get(ComponentDispatcherFactoryInterface::class)
                    ->createDispatcher($app);

                // Construct a standard MVCComponent for Joomla 5
                return new MVCComponent($mvcFactory, $app, $dispatcher);
            }
        );
    }
};
