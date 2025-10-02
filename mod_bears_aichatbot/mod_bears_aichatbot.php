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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\CMS\Helper\ModuleHelper;

// Load language
$app = Factory::getApplication();
$doc = $app->getDocument();

// Load language strings for JavaScript
Text::script('MOD_BEARS_AICHATBOT_OPEN_CHAT');
Text::script('MOD_BEARS_AICHATBOT_CLOSE_CHAT');
Text::script('MOD_BEARS_AICHATBOT_CHAT_OPENED');
Text::script('MOD_BEARS_AICHATBOT_CHAT_CLOSED');
Text::script('MOD_BEARS_AICHATBOT_USER_MESSAGE');
Text::script('MOD_BEARS_AICHATBOT_BOT_MESSAGE');
Text::script('MOD_BEARS_AICHATBOT_ERROR_MESSAGE');
Text::script('MOD_BEARS_AICHATBOT_THINKING');
Text::script('MOD_BEARS_AICHATBOT_PROCESSING');
Text::script('MOD_BEARS_AICHATBOT_COPY_CONVERSATION');
Text::script('MOD_BEARS_AICHATBOT_TOGGLE_SOUND');
Text::script('MOD_BEARS_AICHATBOT_TOGGLE_DARK');
Text::script('MOD_BEARS_AICHATBOT_TOGGLE_TTS');
Text::script('MOD_BEARS_AICHATBOT_STATUS_ONLINE');
Text::script('MOD_BEARS_AICHATBOT_STATUS_OFFLINE');
Text::script('MOD_BEARS_AICHATBOT_CONVERSATION_HEADER');
Text::script('MOD_BEARS_AICHATBOT_YOU');
Text::script('MOD_BEARS_AICHATBOT_AI');
Text::script('MOD_BEARS_AICHATBOT_CONVERSATION_COPIED');
Text::script('MOD_BEARS_AICHATBOT_COPY_FAILED');
Text::script('MOD_BEARS_AICHATBOT_NO_MESSAGES');
Text::script('MOD_BEARS_AICHATBOT_SOUND_ON');
Text::script('MOD_BEARS_AICHATBOT_SOUND_OFF');
Text::script('MOD_BEARS_AICHATBOT_TTS_ON');
Text::script('MOD_BEARS_AICHATBOT_TTS_OFF');
Text::script('MOD_BEARS_AICHATBOT_TTS_NOT_SUPPORTED');
Text::script('MOD_BEARS_AICHATBOT_OFFLINE_ERROR');

// Params
$introText   = $params->get('intro_text');
$position    = $params->get('chat_position', 'bottom-right');
$offsetBottom = (int) $params->get('chat_offset_bottom', 20);
$offsetSide   = (int) $params->get('chat_offset_side', 20);

// Assets
$moduleBase = rtrim(Uri::root(), '/') . '/modules/mod_bears_aichatbot';
$doc->getWebAssetManager()
    ->registerAndUseStyle('mod_bears_aichatbot.css', $moduleBase . '/media/css/aichatbot.css')
    ->registerAndUseScript('mod_bears_aichatbot.js', $moduleBase . '/media/js/aichatbot-loader.js', [], ['defer' => true]);

require_once __DIR__ . '/helper.php';

// Render layout
require ModuleHelper::getLayoutPath('mod_bears_aichatbot', $params->get('layout', 'default'));
