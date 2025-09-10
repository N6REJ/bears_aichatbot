<?php
/**
 * Entry file for plg_content_bears_aichatbot. Bootstrap is handled via services/provider and namespaced classes.
 */
\defined('_JEXEC') or die;

// Ensure the base class file is loaded so aliases can be created reliably
if (is_file(__DIR__ . '/src/BearsAichatbot.php')) {
    require_once __DIR__ . '/src/BearsAichatbot.php';
}

// Compatibility aliases to satisfy Joomla 4.3+/5 plugin class expectations
// Existing base class (current file structure):
//   \Joomla\Plugin\Content\Bears_aichatbot\BearsAichatbot
// Expected by service provider (per manual):
//   \Joomla\Plugin\Content\BearsAichatbot\Extension\BearsAichatbot (CamelCase element + Extension)
// 1) If CamelCase base missing but underscore base exists, alias it
if (!class_exists('\\Joomla\\Plugin\\Content\\BearsAichatbot\\BearsAichatbot')
    && class_exists('\\Joomla\\Plugin\\Content\\Bears_aichatbot\\BearsAichatbot')) {
    class_alias('\\Joomla\\Plugin\\Content\\Bears_aichatbot\\BearsAichatbot', '\\Joomla\\Plugin\\Content\\BearsAichatbot\\BearsAichatbot');
}
// 2) If Extension class (CamelCase) missing, alias base (prefer CamelCase base, fall back to underscore base)
if (!class_exists('\\Joomla\\Plugin\\Content\\BearsAichatbot\\Extension\\BearsAichatbot')) {
    if (class_exists('\\Joomla\\Plugin\\Content\\BearsAichatbot\\BearsAichatbot')) {
        class_alias('\\Joomla\\Plugin\\Content\\BearsAichatbot\\BearsAichatbot', '\\Joomla\\Plugin\\Content\\BearsAichatbot\\Extension\\BearsAichatbot');
    } elseif (class_exists('\\Joomla\\Plugin\\Content\\Bears_aichatbot\\BearsAichatbot')) {
        class_alias('\\Joomla\\Plugin\\Content\\Bears_aichatbot\\BearsAichatbot', '\\Joomla\\Plugin\\Content\\BearsAichatbot\\Extension\\BearsAichatbot');
    }
}
// 3) Maintain underscore Extension alias as well for backward compatibility
if (!class_exists('\\Joomla\\Plugin\\Content\\Bears_aichatbot\\Extension\\BearsAichatbot')
    && class_exists('\\Joomla\\Plugin\\Content\\Bears_aichatbot\\BearsAichatbot')) {
    class_alias('\\Joomla\\Plugin\\Content\\Bears_aichatbot\\BearsAichatbot', '\\Joomla\\Plugin\\Content\\Bears_aichatbot\\Extension\\BearsAichatbot');
}
