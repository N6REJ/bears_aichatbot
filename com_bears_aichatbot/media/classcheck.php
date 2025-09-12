<?php
// TEMP DEBUG (delete after use)
// This script lives in: administrator/components/com_bears_aichatbot/media
// JPATH_BASE must point to administrator/ (three levels up from media)

define('_JEXEC', 1);
define('JPATH_BASE', dirname(__DIR__, 3));

header('Content-Type: text/plain; charset=utf-8');
// Start output buffering to avoid "headers already sent" during Joomla session start
if (!headers_sent()) {
    ob_start();
}

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;

echo "Joomla Admin Class Check\n==========================\n";
echo 'Script __DIR__: ' . __DIR__ . "\n";
echo 'JPATH_BASE: ' . JPATH_BASE . "\n";
echo 'PHP: ' . PHP_VERSION . "\n";

try {
    require_once JPATH_BASE . '/includes/defines.php';
    require_once JPATH_BASE . '/includes/framework.php';
    // Load admin app services (ensures DI providers like Session are registered)
    if (is_file(JPATH_BASE . '/includes/app.php')) {
        require_once JPATH_BASE . '/includes/app.php';
    }

    echo "\n"; // spacer
    echo 'Joomla: ' . (defined('JVERSION') ? JVERSION : 'unknown') . "\n";

    // Ensure the Administrator application is fully booted so container services are available
    try {
        /** @var AdministratorApplication $app */
        // Prefer resolving from the container in J5
        $container = \Joomla\CMS\Factory::getContainer();
        if ($container->has(AdministratorApplication::class)) {
            $app = $container->get(AdministratorApplication::class);
        } else {
            $app = Factory::getApplication('administrator');
        }
        if (method_exists($app, 'boot')) {
            $app->boot();
        }
        if (method_exists($app, 'initialise')) {
            $app->initialise();
        }
        echo "[APP BOOTED] Admin application booted and initialised\n";
    } catch (\Throwable $e) {
        echo "[BOOT/INITIALISE WARNING] " . $e->getMessage() . "\n";
    }

    $tests = [
        'Joomla\\Component\\Bears_aichatbot\\Administrator\\Controller\\DisplayController',
        'Joomla\\Component\\Bears_aichatbot\\Controller\\DisplayController',
        'Joomla\\Component\\BearsAichatbot\\Administrator\\Controller\\DisplayController',
        'Joomla\\Component\\BearsAichatbot\\Controller\\DisplayController',
        'Bears_aichatbotControllerDisplay',
        'Joomla\\Component\\Bears_aichatbot\\Dispatcher\\Dispatcher',
        'Joomla\\Component\\Bears_aichatbot\\Administrator\\Dispatcher\\Dispatcher',
        'Joomla\\Component\\BearsAichatbot\\Dispatcher\\Dispatcher',
        'Joomla\\Component\\BearsAichatbot\\Administrator\\Dispatcher\\Dispatcher',
    ];

    // Direct file checks to ensure deployment and namespaces are correct
    $base = JPATH_BASE . '/components/com_bears_aichatbot/src';
    $files = [
        $base . '/Controller/DisplayController.php',
        $base . '/Administrator/Dispatcher/Dispatcher.php',
        $base . '/Dispatcher/Dispatcher.php',
    ];
    foreach ($files as $f) {
        echo '[FILE EXISTS] ' . $f . ' => ' . (is_file($f) ? 'true' : 'false') . "\n";
        if (is_file($f)) {
            require_once $f;
        }
    }

    foreach ($tests as $class) {
        echo $class . ' => ' . (class_exists($class) ? 'true' : 'false') . "\n";
    }

    // Manually register our provider early (so MVCFactory for our namespace is available)
    try {
        $container = \Joomla\CMS\Factory::getContainer();
        $providerFile = JPATH_BASE . '/components/com_bears_aichatbot/services/provider.php';
        if (is_file($providerFile)) {
            $provider = require $providerFile;
            if ($provider instanceof \Joomla\DI\ServiceProviderInterface) {
                $container->registerServiceProvider($provider);
                echo "[PROVIDER] Registered component service provider\n";
            } else {
                echo "[PROVIDER WARNING] provider.php did not return a ServiceProviderInterface\n";
            }
        } else {
            echo "[PROVIDER WARNING] services/provider.php not found\n";
        }
    } catch (\Throwable $e) {
        echo "[PROVIDER REGISTER ERROR] " . $e->getMessage() . "\n";
    }

    // Try to resolve dispatcher class used by the factory (after provider registration)
    try {
        $app = \Joomla\CMS\Factory::getApplication('administrator');
        /** @var \Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface $df */
        $df  = \Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface::class);
        $disp = $df->createDispatcher($app);
        echo "\nFactory dispatcher class: " . get_class($disp) . "\n";
    } catch (\Throwable $e) {
        echo "\n[DISPATCHER FACTORY ERROR] " . $e->getMessage() . "\n";
    }

    // Try creating controllers via MVCFactory for both prefixes (after provider registration)
    try {
        $container = \Joomla\CMS\Factory::getContainer();
        if ($container->has(\Joomla\CMS\MVC\Factory\MVCFactoryInterface::class)) {
            /** @var \Joomla\CMS\MVC\Factory\MVCFactoryInterface $mvc */
            $mvc = $container->get(\Joomla\CMS\MVC\Factory\MVCFactoryInterface::class);
            $input = \Joomla\CMS\Factory::getApplication()->input;
            try {
                $c1 = $mvc->createController('Display', 'Joomla\\\\Component\\\\BearsAichatbot\\\\Controller', $input, ['option' => 'com_bears_aichatbot']);
                echo "MVCFactory base controller: " . get_class($c1) . "\n";
            } catch (\Throwable $e1) {
                echo "[MVCFactory base ERROR] " . $e1->getMessage() . "\n";
            }
            try {
                $c2 = $mvc->createController('Display', 'Joomla\\\\Component\\\\BearsAichatbot\\\\Administrator\\\\Controller', $input, ['option' => 'com_bears_aichatbot']);
                echo "MVCFactory admin controller: " . get_class($c2) . "\n";
            } catch (\Throwable $e2) {
                echo "[MVCFactory admin ERROR] " . $e2->getMessage() . "\n";
            }
        } else {
            echo "[MVCFactory NOTICE] MVCFactoryInterface not present in container (provider not registered?)\n";
        }
    } catch (\Throwable $e) {
        echo "[MVCFactory ERROR] " . $e->getMessage() . "\n";
    }

    // Inspect the component + dispatcher via the provider binding
    try {
        $container = \Joomla\CMS\Factory::getContainer();
        $providerFile = JPATH_BASE . '/components/com_bears_aichatbot/services/provider.php';
        if (is_file($providerFile)) {
            $provider = require $providerFile;
            if ($provider instanceof \Joomla\DI\ServiceProviderInterface) {
                $container->registerServiceProvider($provider);
            }
        }
        /** @var \Joomla\CMS\Extension\ComponentInterface $component */
        $component = $container->get(\Joomla\CMS\Extension\ComponentInterface::class);
        echo "Component class: " . get_class($component) . "\n";
        if (method_exists($component, 'getDispatcher')) {
            $cd = $component->getDispatcher();
            echo "Component->getDispatcher(): " . get_class($cd) . "\n";
            // Try asking dispatcher for the 'display' controller
            try {
                if (method_exists($cd, 'getController')) {
                    $ctrl = $cd->getController('display');
                    echo "Dispatcher->getController('display'): " . get_class($ctrl) . "\n";
                }
            } catch (\Throwable $e) {
                echo "[DISPATCHER getController ERROR] " . $e->getMessage() . "\n";
            }
        } else {
            echo "Component has no getDispatcher() method.\n";
        }
    } catch (\Throwable $e) {
        echo "[PROVIDER/COMPONENT ERROR] " . $e->getMessage() . "\n";
    }

} catch (Throwable $e) {
    echo "[BOOTSTRAP ERROR] " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nHint: If you still see Factory::getApplication() in the stack, your server is serving an older cached file. Clear OPcache and reload.\n";
}

// Flush output buffer if started
if (function_exists('ob_get_level') && ob_get_level() > 0) {
    ob_end_flush();
}
