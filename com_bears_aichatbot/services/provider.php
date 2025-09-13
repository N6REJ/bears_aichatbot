<?php
/**
 * Joomla 5 service provider for com_bears_aichatbot
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
        // Register MVC factory and dispatcher factory for Administrator namespace
        $container->registerServiceProvider(new MVCFactory('Joomla\\Component\\BearsAichatbot\\Administrator'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('Joomla\\Component\\BearsAichatbot\\Administrator'));

        // Register the component service (Administrator only)
        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $app               = $container->get(AdministratorApplication::class);
                $mvcFactory        = $container->get(MVCFactoryInterface::class);
                $dispatcherFactory = $container->get(ComponentDispatcherFactoryInterface::class);

                // Create the dispatcher (used by some constructor variants and/or set later)
                $dispatcher = $dispatcherFactory->createDispatcher($app);

                // Joomla 5 environments may expect the dispatcherFactory-first signature.
                // Try that first, then fall back to the older ($mvcFactory, $app) signature.
                try {
                    $component = new MVCComponent($dispatcherFactory, $mvcFactory, $app);
                } catch (\Throwable $e) {
                    $component = new MVCComponent($mvcFactory, $app);
                }

                // If the component exposes setDispatcher, wire in the created dispatcher
                if (method_exists($component, 'setDispatcher')) {
                    $component->setDispatcher($dispatcher);
                }

                return $component;
            }
        );
    }
};
