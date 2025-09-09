<?php
/**
 * Front-end template for Bears AI Chatbot
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
<div class="bears-aichatbot" data-module-id="<?php echo $moduleId; ?>"
     data-ajax-url="<?php echo htmlspecialchars($ajaxEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
     data-position="<?php echo htmlspecialchars($position, ENT_QUOTES, 'UTF-8'); ?>"
     data-offset-bottom="<?php echo (int) $offsetBottom; ?>"
     data-offset-side="<?php echo (int) $offsetSide; ?>"
     data-open-width="<?php echo (int) $params->get('open_width', 400); ?>"
     data-open-height="<?php echo (int) $params->get('open_height', 500); ?>"
     data-button-label="<?php echo htmlspecialchars($params->get('button_label', 'Knowledgebase'), ENT_QUOTES, 'UTF-8'); ?>"
     data-dark-mode="<?php echo (int) $params->get('dark_mode', 0); ?>">
    <div class="bears-aichatbot-window">
        <div class="bears-aichatbot-header">
            <div class="bears-aichatbot-title"><?php echo Text::_('MOD_BEARS_AICHATBOT_TITLE'); ?></div>
        </div>
        <div class="bears-aichatbot-messages" id="bears-aichatbot-messages-<?php echo $moduleId; ?>">
            <?php if (!empty($introText)) : ?>
                <div class="message bot">
                    <div class="bubble"><?php echo $introText; ?></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="bears-aichatbot-input">
            <input type="text" class="bears-aichatbot-text" id="bears-aichatbot-input-<?php echo $moduleId; ?>" placeholder="<?php echo Text::_('MOD_BEARS_AICHATBOT_PLACEHOLDER'); ?>" />
            <button class="bears-aichatbot-send btn btn-primary" id="bears-aichatbot-send-<?php echo $moduleId; ?>"><?php echo Text::_('MOD_BEARS_AICHATBOT_SEND'); ?></button>
        </div>
    </div>
</div>
