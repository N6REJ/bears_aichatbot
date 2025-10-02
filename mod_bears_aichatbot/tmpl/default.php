<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.09.19
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Uri\Uri;

$moduleId     = (int) $module->id;
$ajaxEndpoint = Uri::base() . 'index.php?option=com_ajax&module=bears_aichatbot&method=ask&format=json&module_id=' . $moduleId;

$introText    = $params->get('intro_text');
$position     = $params->get('chat_position', 'bottom-right');
$offsetBottom = (int) $params->get('chat_offset_bottom', 20);
$offsetSide   = (int) $params->get('chat_offset_side', 20);
?>
<div class="bears-aichatbot" 
     data-module-id="<?php echo $moduleId; ?>"
     data-ajax-url="<?php echo htmlspecialchars($ajaxEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
     data-position="<?php echo htmlspecialchars($position, ENT_QUOTES, 'UTF-8'); ?>"
     data-offset-bottom="<?php echo (int) $offsetBottom; ?>"
     data-offset-side="<?php echo (int) $offsetSide; ?>"
     data-open-width="<?php echo (int) $params->get('open_width', 400); ?>"
     data-open-height="<?php echo (int) $params->get('open_height', 500); ?>"
     data-button-label="<?php echo htmlspecialchars($params->get('button_label', 'Knowledgebase'), ENT_QUOTES, 'UTF-8'); ?>"
     data-dark-mode="<?php echo (int) $params->get('dark_mode', 0); ?>"
     data-sound-notifications="<?php echo (int) $params->get('sound_notifications', 0); ?>"
     data-sound-volume="<?php echo (float) $params->get('sound_volume', 0.1); ?>"
     data-sound-sent-frequency="<?php echo (int) $params->get('sound_sent_frequency', 800); ?>"
     data-sound-sent-duration="<?php echo (int) $params->get('sound_sent_duration', 100); ?>"
     data-sound-received-frequency="<?php echo (int) $params->get('sound_received_frequency', 600); ?>"
     data-sound-received-duration="<?php echo (int) $params->get('sound_received_duration', 150); ?>"
     data-sound-error-frequency="<?php echo (int) $params->get('sound_error_frequency', 300); ?>"
     data-sound-error-duration="<?php echo (int) $params->get('sound_error_duration', 200); ?>"
     data-connection-check-interval="<?php echo (int) $params->get('connection_check_interval', 0); ?>"
     data-text-to-speech="<?php echo (int) $params->get('text_to_speech', 0); ?>"
     data-tts-rate="<?php echo (float) $params->get('tts_rate', 0.9); ?>"
     data-tts-pitch="<?php echo (float) $params->get('tts_pitch', 1.0); ?>"
     data-tts-volume="<?php echo (float) $params->get('tts_volume', 0.8); ?>"
     role="complementary"
     aria-label="<?php echo Text::_('MOD_BEARS_AICHATBOT_ARIA_LABEL'); ?>">
    
    <!-- Screen reader only description -->
    <div id="chat-description-<?php echo $moduleId; ?>" class="bears-sr-only">
        <?php echo Text::_('MOD_BEARS_AICHATBOT_CHAT_DESCRIPTION'); ?>
    </div>
    
    <!-- Status announcements for screen readers -->
    <div id="chat-status-<?php echo $moduleId; ?>" 
         aria-live="assertive" 
         class="bears-sr-only"></div>
    
    <div class="bears-aichatbot-window" 
         role="dialog" 
         aria-modal="false"
         aria-labelledby="chat-title-<?php echo $moduleId; ?>"
         aria-describedby="chat-description-<?php echo $moduleId; ?>">
        <div class="bears-aichatbot-header">
            <div class="bears-aichatbot-title" id="chat-title-<?php echo $moduleId; ?>">
                <?php echo Text::_('MOD_BEARS_AICHATBOT_TITLE'); ?>
            </div>
        </div>
        <div class="bears-aichatbot-messages" 
             id="bears-aichatbot-messages-<?php echo $moduleId; ?>"
             role="log"
             aria-live="polite"
             aria-label="<?php echo Text::_('MOD_BEARS_AICHATBOT_CONVERSATION_LABEL'); ?>"
             tabindex="0">
            <?php if (!empty($introText)) : ?>
                <div class="message bot" role="article" aria-label="<?php echo Text::_('MOD_BEARS_AICHATBOT_BOT_MESSAGE'); ?>">
                    <div class="bubble"><?php echo $introText; ?></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="bears-aichatbot-input" role="form" aria-label="<?php echo Text::_('MOD_BEARS_AICHATBOT_INPUT_FORM_LABEL'); ?>">
            <label for="bears-aichatbot-input-<?php echo $moduleId; ?>" class="bears-sr-only">
                <?php echo Text::_('MOD_BEARS_AICHATBOT_INPUT_LABEL'); ?>
            </label>
            <input type="text" 
                   id="bears-aichatbot-input-<?php echo $moduleId; ?>"
                   class="bears-aichatbot-text" 
                   placeholder="<?php echo Text::_('MOD_BEARS_AICHATBOT_PLACEHOLDER'); ?>"
                   aria-describedby="chat-help-<?php echo $moduleId; ?>"
                   aria-required="false" />
            <div id="chat-help-<?php echo $moduleId; ?>" class="bears-sr-only">
                <?php echo Text::_('MOD_BEARS_AICHATBOT_INPUT_HELP'); ?>
            </div>
            <button class="bears-aichatbot-send btn btn-primary" 
                    id="bears-aichatbot-send-<?php echo $moduleId; ?>"
                    aria-describedby="chat-help-<?php echo $moduleId; ?>"
                    aria-busy="false">
                <?php echo Text::_('MOD_BEARS_AICHATBOT_SEND'); ?>
            </button>
        </div>
    </div>
</div>
