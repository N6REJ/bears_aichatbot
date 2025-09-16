/**
 * UI Manager Module
 * Handles UI notifications and interactions
 */
export class UIManager {
  constructor() {
    this.notificationTimeout = 3000;
  }

  showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `bears-notification ${type}`;
    notification.textContent = message;
    notification.setAttribute('role', 'alert');
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Remove after timeout
    setTimeout(() => {
      notification.classList.remove('show');
      setTimeout(() => notification.remove(), 300);
    }, this.notificationTimeout);
  }

  announceToScreenReader(message, priority = 'polite') {
    const announcement = document.createElement('div');
    announcement.setAttribute('aria-live', priority);
    announcement.setAttribute('aria-atomic', 'true');
    announcement.className = 'bears-sr-only';
    announcement.textContent = message;
    document.body.appendChild(announcement);
    
    // Remove after announcement
    setTimeout(() => {
      if (announcement.parentNode) {
        announcement.parentNode.removeChild(announcement);
      }
    }, 1000);
  }

  copyConversation(messages) {
    try {
      // Validate messages container exists
      if (!messages || !messages.querySelectorAll) {
        console.warn('[UIManager] Invalid messages container for copy operation');
        this.showNotification(this.getLanguageString('MOD_BEARS_AICHATBOT_COPY_FAILED', 'Failed to copy conversation'), 'error');
        return;
      }
      
      // Try multiple selectors for better compatibility
      let messageElements = messages.querySelectorAll('.message');
      
      // Fallback selectors
      if (!messageElements || messageElements.length === 0) {
        messageElements = messages.querySelectorAll('[role="article"]');
      }
      
      if (!messageElements || messageElements.length === 0) {
        const allDivs = messages.querySelectorAll('div');
        const potentialMessages = [];
        allDivs.forEach(div => {
          if (div.querySelector('.bubble') || div.classList.contains('user') || div.classList.contains('bot')) {
            potentialMessages.push(div);
          }
        });
        messageElements = potentialMessages;
      }
      
      // If no messages found at all, notify user
      if (!messageElements || messageElements.length === 0) {
        this.showNotification(this.getLanguageString('MOD_BEARS_AICHATBOT_NO_MESSAGES', 'No messages to copy'), 'info');
        return;
      }
      
      // Generate a single timestamp for the entire conversation copy
      const copyTimestamp = new Date().toLocaleString();
      
      let conversationText = this.getLanguageString('MOD_BEARS_AICHATBOT_CONVERSATION_HEADER', 'Chat Conversation') + '\n';
      conversationText += `Copied: ${copyTimestamp}\n`;
      conversationText += '='.repeat(50) + '\n\n';
      
      let messageCount = 0;
      
      messageElements.forEach((msg, index) => {
        try {
          // Determine if it's a user or bot message
          const isUser = msg.classList && (msg.classList.contains('user') || 
                        msg.getAttribute('data-role') === 'user' ||
                        (msg.getAttribute('aria-label') && msg.getAttribute('aria-label').includes('Your')));
          
          // Try multiple ways to get the message text
          let text = '';
          
          const bubble = msg.querySelector('.bubble');
          if (bubble) {
            text = bubble.textContent || bubble.innerText || '';
          }
          
          if (!text && msg.textContent) {
            text = msg.textContent;
          }
          
          if (!text) {
            const textElements = msg.querySelectorAll('p, span, div');
            for (let elem of textElements) {
              if (elem.textContent && elem.textContent.trim()) {
                text = elem.textContent;
                break;
              }
            }
          }
          
          // Clean up the text
          text = text.trim();
          
          // Skip empty messages or thinking indicators
          if (text && !text.includes('Researching') && !text.includes('Processing')) {
            const role = isUser ? 
              this.getLanguageString('MOD_BEARS_AICHATBOT_YOU', 'You') : 
              this.getLanguageString('MOD_BEARS_AICHATBOT_AI', 'AI');
            // Use message number instead of misleading timestamp
            conversationText += `[Message ${messageCount + 1}] ${role}:\n${text}\n\n`;
            messageCount++;
          }
        } catch (msgError) {
          console.warn('[UIManager] Error processing message:', msgError);
        }
      });
      
      // Check if we actually captured any messages
      if (messageCount === 0) {
        this.showNotification(this.getLanguageString('MOD_BEARS_AICHATBOT_NO_MESSAGES', 'No messages to copy'), 'info');
        return;
      }
      
      // Add footer with message count
      conversationText += '='.repeat(50) + '\n';
      conversationText += `Total messages: ${messageCount}\n`;
      
      // Try modern clipboard API first
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(conversationText).then(() => {
          this.showNotification(
            this.getLanguageString('MOD_BEARS_AICHATBOT_CONVERSATION_COPIED', 'Conversation copied to clipboard!') + 
            ` (${messageCount} messages)`, 
            'success'
          );
        }).catch((err) => {
          console.warn('[UIManager] Clipboard API failed:', err);
          this.fallbackCopy(conversationText, messageCount);
        });
      } else {
        this.fallbackCopy(conversationText, messageCount);
      }
    } catch (error) {
      console.error('[UIManager] Error in copyConversation:', error);
      this.showNotification(this.getLanguageString('MOD_BEARS_AICHATBOT_COPY_FAILED', 'Failed to copy conversation'), 'error');
    }
  }

  fallbackCopy(text, messageCount) {
    try {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      textarea.style.pointerEvents = 'none';
      textarea.style.zIndex = '-1';
      document.body.appendChild(textarea);
      
      // For iOS compatibility
      if (navigator.userAgent.match(/ipad|iphone/i)) {
        const range = document.createRange();
        range.selectNodeContents(textarea);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        textarea.setSelectionRange(0, 999999);
      } else {
        textarea.select();
      }
      
      const successful = document.execCommand('copy');
      document.body.removeChild(textarea);
      
      if (successful) {
        const message = messageCount ? 
          this.getLanguageString('MOD_BEARS_AICHATBOT_CONVERSATION_COPIED', 'Conversation copied to clipboard!') + 
          ` (${messageCount} messages)` :
          this.getLanguageString('MOD_BEARS_AICHATBOT_CONVERSATION_COPIED', 'Conversation copied to clipboard!');
        this.showNotification(message, 'success');
      } else {
        throw new Error('execCommand failed');
      }
    } catch (e) {
      console.error('[UIManager] Fallback copy failed:', e);
      this.showNotification(this.getLanguageString('MOD_BEARS_AICHATBOT_COPY_FAILED', 'Failed to copy conversation'), 'error');
    }
  }

  addCopyButtonsToCodeBlocks(container) {
    const codeBlocks = container.querySelectorAll('pre');
    codeBlocks.forEach(pre => {
      const btn = document.createElement('button');
      btn.className = 'bears-copy-btn';
      btn.textContent = 'Copy';
      btn.type = 'button';
      btn.onclick = function() {
        const code = pre.querySelector('code');
        const text = code ? code.textContent : pre.textContent;
        navigator.clipboard.writeText(text).then(() => {
          btn.textContent = 'Copied!';
          btn.classList.add('copied');
          setTimeout(() => {
            btn.textContent = 'Copy';
            btn.classList.remove('copied');
          }, 2000);
        }).catch(() => {
          // Fallback for older browsers
          const textarea = document.createElement('textarea');
          textarea.value = text;
          textarea.style.position = 'fixed';
          textarea.style.opacity = '0';
          document.body.appendChild(textarea);
          textarea.select();
          try {
            document.execCommand('copy');
            btn.textContent = 'Copied!';
            btn.classList.add('copied');
            setTimeout(() => {
              btn.textContent = 'Copy';
              btn.classList.remove('copied');
            }, 2000);
          } catch (e) {
            btn.textContent = 'Failed';
          }
          document.body.removeChild(textarea);
        });
      };
      pre.appendChild(btn);
    });
  }

  getLanguageString(key, fallback) {
    if (typeof Joomla !== 'undefined' && Joomla.Text && Joomla.Text._(key)) {
      return Joomla.Text._(key);
    }
    return fallback || key;
  }

  ensureStyles() {
    try {
      if (document.getElementById('bears-aichatbot-inline-style')) return;
      const css = `
.bears-aichatbot--closed .bears-aichatbot-window{display:none}
.bears-aichatbot--open .bears-aichatbot-toggle{display:none}
.bears-aichatbot--open .bears-aichatbot-window{width:var(--bears-open-width, min(400px,90vw));height:var(--bears-open-height, 70vh);resize:both;overflow:auto}
.bears-aichatbot-toggle{display:flex;align-items:center;justify-content:center}
.bears-aichatbot-header{position:relative}
.bears-aichatbot-close{position:absolute;right:8px;top:8px;background:transparent;border:none;color:#fff;font-size:20px;cursor:pointer}
/* middle side positions */
.bears-aichatbot[data-position="middle-right"]{right:var(--bears-offset-side,20px);top:50%;transform:translateY(-50%)}
.bears-aichatbot[data-position="middle-left"]{left:var(--bears-offset-side,20px);top:50%;transform:translateY(-50%)}
/* vertical toggle for middle positions */
.bears-aichatbot[data-position="middle-right"] .bears-aichatbot-toggle,
.bears-aichatbot[data-position="middle-left"] .bears-aichatbot-toggle{writing-mode:vertical-rl;text-orientation:mixed}
@media (max-width: 767px){.bears-aichatbot{display:none !important}}
/* formatted message elements */
.bears-aichatbot .bubble a{color:#0b74de;text-decoration:underline;word-break:break-all}
.bears-aichatbot .bubble pre{background:#f6f8fa;padding:8px;border-radius:6px;overflow:auto}
.bears-aichatbot .bubble code{background:#f2f4f7;padding:2px 4px;border-radius:4px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
.bears-aichatbot .bubble ul{margin:0.5em 0;padding-left:1.25em}
.bears-aichatbot .bubble li{margin:0.25em 0}
`;
      const style = document.createElement('style');
      style.id = 'bears-aichatbot-inline-style';
      style.type = 'text/css';
      style.appendChild(document.createTextNode(css));
      document.head.appendChild(style);
    } catch(e) {}
  }
}

// Create singleton instance
export const uiManager = new UIManager();
