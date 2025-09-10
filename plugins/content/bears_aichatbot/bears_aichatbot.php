<?php
/**
 * Entry file for plg_content_bears_aichatbot. Bootstrap is handled via services/provider and namespaced classes.
 */
\defined('_JEXEC') or die;

// Provide a compatibility alias so Joomla's Plugin provider can resolve the Extension class name
if (!class_exists('\\Joomla\\Plugin\\Content\\Bears_aichatbot\\Extension\\BearsAichatbot')
    && class_exists('\\Joomla\\Plugin\\Content\\Bears_aichatbot\\BearsAichatbot')) {
    class_alias('\\Joomla\\Plugin\\Content\\Bears_aichatbot\\BearsAichatbot', '\\Joomla\\Plugin\\Content\\Bears_aichatbot\\Extension\\BearsAichatbot');
}
