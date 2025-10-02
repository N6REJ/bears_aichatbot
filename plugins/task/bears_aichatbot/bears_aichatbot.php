<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.10.02.2
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\PluginHelper;

// Load the plugin's autoloader if needed
$autoloadFile = __DIR__ . '/services/provider.php';
if (file_exists($autoloadFile)) {
    return require $autoloadFile;
}
