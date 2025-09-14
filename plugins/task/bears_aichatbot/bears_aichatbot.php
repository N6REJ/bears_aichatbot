<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Task.bears_aichatbot
 *
 * @copyright   (C) 2025
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\PluginHelper;

// Load the plugin's autoloader if needed
$autoloadFile = __DIR__ . '/services/provider.php';
if (file_exists($autoloadFile)) {
    return require $autoloadFile;
}
