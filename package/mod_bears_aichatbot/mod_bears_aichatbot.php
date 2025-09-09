<?php
/**
 * mod_bears_aichatbot - AI Knowledgebase Chatbot for Joomla 5
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\CMS\Helper\ModuleHelper;

// Load language
$app = Factory::getApplication();
$doc = $app->getDocument();

// Params
$introText   = $params->get('intro_text');
$position    = $params->get('chat_position', 'bottom-right');
$offsetBottom = (int) $params->get('chat_offset_bottom', 20);
$offsetSide   = (int) $params->get('chat_offset_side', 20);

// Assets
$moduleBase = rtrim(Uri::root(), '/') . '/modules/mod_bears_aichatbot';
$doc->getWebAssetManager()
    ->registerAndUseStyle('mod_bears_aichatbot.css', $moduleBase . '/media/css/aichatbot.css')
    ->registerAndUseScript('mod_bears_aichatbot.js', $moduleBase . '/media/js/aichatbot.js', [], ['defer' => true]);

require_once __DIR__ . '/helper.php';

// Render layout
require ModuleHelper::getLayoutPath('mod_bears_aichatbot', $params->get('layout', 'default'));
