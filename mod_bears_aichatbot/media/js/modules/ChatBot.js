/**
 * ChatBot Main Class
 * Orchestrates all chatbot functionality using modular components
 */
import { soundManager } from './SoundManager.js';
import { ttsManager } from './TTSManager.js';
import { connectionStatus } from './ConnectionStatus.js';
import { darkModeManager } from './DarkModeManager.js';
import { messageFormatter } from './MessageFormatter.js';
import { uiManager } from './UIManager.js';

export class ChatBot {
  constructor(instance) {
    this.instance = instance;
    this.config = this.extractConfig(instance);
    this.elements = {};
    this.thinkingIndicator = null;
    this.isOpen = false;
    
    // Initialize managers
    this.soundManager = soundManager;
    this.ttsManager = ttsManager;
    this.connectionStatus = connectionStatus;
    this.darkModeManager = darkModeManager;
    this.messageFormatter = messageFormatter;
    this.uiManager = uiManager;
  }

  extractConfig(instance) {
    return {
      ajaxUrl: instance.getAttribute('data-ajax-url'),
      moduleId: instance.getAttribute('data-module-id'),
      position: instance.getAttribute('data-position') || 'bottom-right',
      offsetBottom: parseInt(instance.getAttribute('data-offset-bottom') || '20', 10),
      offsetSide: parseInt(instance.getAttribute('data-offset-side') || '20', 10),
      openWidth: parseInt(instance.getAttribute('data-open-width') || '400', 10),
      openHeight: parseInt(instance.getAttribute('data-open-height') || '500', 10),
      buttonLabel: instance.getAttribute('data-button-label') || 'Knowledgebase',
      darkMode: instance.getAttribute('data-dark-mode') === '1',
      soundNotifications: instance.getAttribute('data-sound-notifications') === '1',
      connectionCheckInterval: parseInt(instance.getAttribute('data-connection-check-interval') || '60', 10),
      textToSpeech: instance.getAttribute('data-text-to-speech') === '1'
    };
  }

  init() {
    try {
      console.debug('[ChatBot] Initializing', this.config);
    } catch (e) {}
    
    // Initialize managers
    this.soundManager.init(this.config.soundNotifications);
    this.ttsManager.init(this.config.textToSpeech);
    this.darkModeManager.init(this.instance, this.config.darkMode);
    
    // Apply styles and configuration
    this.applyStyles();
    this.createUI();
    this.attachEventListeners();
    
    // Initialize connection status if enabled
    if (this.config.connectionCheckInterval > 0) {
      const windowEl = this.instance.querySelector('.bears-aichatbot-window');
      if (windowEl) {
        this.connectionStatus.init(windowEl, this.config.ajaxUrl, this.config.connectionCheckInterval);
        this.instance._connectionStatus = this.connectionStatus;
      }
    }
  }

  applyStyles() {
    // Ensure styles are loaded
    this.uiManager.ensureStyles();
    
    // Apply offsets via CSS variables
    this.instance.style.setProperty('--bears-offset-bottom', this.config.offsetBottom + 'px');
    this.instance.style.setProperty('--bears-offset-side', this.config.offsetSide + 'px');
    this.instance.setAttribute('data-position', this.config.position);
    
    // Apply open width/height as CSS variables
    this.instance.style.setProperty('--bears-open-width', `min(${this.config.openWidth}px, 90vw)`);
    this.instance.style.setProperty('--bears-open-height', `min(${this.config.openHeight}px, 90vh)`);
    
    // Set initial state
    this.instance.classList.add('bears-aichatbot--closed');
  }

  createUI() {
    // Create toggle button
    const toggle = document.createElement('button');
    toggle.className = 'bears-aichatbot-toggle btn btn-primary';
    toggle.setAttribute('aria-label', this.getLanguageString('MOD_BEARS_AICHATBOT_OPEN_CHAT', 'Open AI chatbot'));
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-describedby', `chat-description-${this.config.moduleId}`);
    toggle.title = this.getLanguageString('MOD_BEARS_AICHATBOT_OPEN_CHAT', 'Open AI chatbot');
    
    // Vertical labeled toggle for middle positions
    if (this.config.position === 'middle-right' || this.config.position === 'middle-left') {
      toggle.textContent = this.config.buttonLabel;
    } else {
      toggle.textContent = '💬';
      toggle.setAttribute('aria-label', `${this.getLanguageString('MOD_BEARS_AICHATBOT_OPEN_CHAT', 'Open AI chatbot')} (Chat icon)`);
    }
    this.instance.appendChild(toggle);
    this.elements.toggle = toggle;
    
    // Create close button
    const headerEl = this.instance.querySelector('.bears-aichatbot-header');
    if (headerEl) {
      let closeBtn = headerEl.querySelector('.bears-aichatbot-close');
      if (!closeBtn) {
        closeBtn = document.createElement('button');
        closeBtn.className = 'bears-aichatbot-close';
        closeBtn.setAttribute('aria-label', this.getLanguageString('MOD_BEARS_AICHATBOT_CLOSE_CHAT', 'Close chatbot'));
        closeBtn.type = 'button';
        closeBtn.textContent = '×';
        headerEl.appendChild(closeBtn);
      }
      this.elements.closeBtn = closeBtn;
      
      // Create toolbar
      this.createToolbar(headerEl);
    }
    
    // Get other elements
    this.elements.messages = this.instance.querySelector('.bears-aichatbot-messages');
    this.elements.input = this.instance.querySelector('.bears-aichatbot-text');
    this.elements.sendBtn = this.instance.querySelector('.bears-aichatbot-send');
  }

  createToolbar(headerEl) {
    const toolbar = document.createElement('div');
    toolbar.className = 'bears-chat-toolbar';
    
    // TTS button (if supported)
    if (this.ttsManager.isSupported()) {
      const ttsBtn = this.createToolbarButton('tts', 
        this.getLanguageString('MOD_BEARS_AICHATBOT_TOGGLE_TTS', 'Toggle text-to-speech'),
        this.ttsManager.isEnabled());
      toolbar.appendChild(ttsBtn);
    }
    
    // Copy button
    const copyBtn = this.createToolbarButton('copy', 
      this.getLanguageString('MOD_BEARS_AICHATBOT_COPY_CONVERSATION', 'Copy conversation'));
    toolbar.appendChild(copyBtn);
    
    // Sound button
    const soundBtn = this.createToolbarButton('sound', 
      this.getLanguageString('MOD_BEARS_AICHATBOT_TOGGLE_SOUND', 'Toggle sound notifications'),
      this.soundManager.isEnabled());
    toolbar.appendChild(soundBtn);
    
    // Dark mode button
    const darkBtn = this.createToolbarButton('dark', 
      this.getLanguageString('MOD_BEARS_AICHATBOT_TOGGLE_DARK', 'Toggle dark mode'));
    toolbar.appendChild(darkBtn);
    
    headerEl.insertBefore(toolbar, this.elements.closeBtn);
    this.elements.toolbar = toolbar;
  }

  createToolbarButton(type, label, enabled = false) {
    const btn = document.createElement('button');
    btn.className = `bears-toolbar-btn ${type}-btn${enabled ? ' enabled' : ''}`;
    btn.setAttribute('aria-label', label);
    btn.title = label;
    btn.innerHTML = this.getButtonIcon(type, enabled);
    return btn;
  }

  getButtonIcon(type, enabled = false) {
    const icons = {
      copy: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>',
      sound: enabled ? 
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>' :
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>',
      dark: this.darkModeManager.isEnabled() ?
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>' :
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>',
      tts: enabled ? 
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6L8 10H4v4h4l4 4V6z"/><path d="M16 8.5a4.5 4.5 0 0 1 0 7M19 5.5a8.5 8.5 0 0 1 0 13"/></svg>' :
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6L8 10H4v4h4l4 4V6z"/><line x1="20" y1="9" x2="14" y2="15"/><line x1="14" y1="9" x2="20" y2="15"/></svg>'
    };
    return icons[type] || '';
  }

  attachEventListeners() {
    // Toggle button
    this.elements.toggle.addEventListener('click', () => this.openChat());
    
    // Close button
    if (this.elements.closeBtn) {
      this.elements.closeBtn.addEventListener('click', () => this.closeChat());
    }
    
    // Toolbar buttons
    if (this.elements.toolbar) {
      // Copy button
      const copyBtn = this.elements.toolbar.querySelector('.copy-btn');
      if (copyBtn) {
        copyBtn.addEventListener('click', () => {
          this.uiManager.copyConversation(this.elements.messages);
        });
      }
      
      // Sound button
      const soundBtn = this.elements.toolbar.querySelector('.sound-btn');
      if (soundBtn) {
        soundBtn.addEventListener('click', () => {
          const enabled = this.soundManager.toggle();
          soundBtn.classList.toggle('enabled', enabled);
          soundBtn.innerHTML = this.getButtonIcon('sound', enabled);
          this.uiManager.showNotification(enabled ? 
            this.getLanguageString('MOD_BEARS_AICHATBOT_SOUND_ON', 'Sound notifications enabled') : 
            this.getLanguageString('MOD_BEARS_AICHATBOT_SOUND_OFF', 'Sound notifications disabled'), 'info');
        });
      }
      
      // Dark mode button
      const darkBtn = this.elements.toolbar.querySelector('.dark-btn');
      if (darkBtn) {
        darkBtn.addEventListener('click', () => {
          const isDark = this.darkModeManager.toggle();
          darkBtn.innerHTML = this.getButtonIcon('dark');
        });
      }
      
      // TTS button
      const ttsBtn = this.elements.toolbar.querySelector('.tts-btn');
      if (ttsBtn) {
        ttsBtn.addEventListener('click', () => {
          const enabled = this.ttsManager.toggle();
          ttsBtn.classList.toggle('enabled', enabled);
          ttsBtn.innerHTML = this.getButtonIcon('tts', enabled);
          this.uiManager.showNotification(enabled ? 
            this.getLanguageString('MOD_BEARS_AICHATBOT_TTS_ON', 'Text-to-speech enabled') : 
            this.getLanguageString('MOD_BEARS_AICHATBOT_TTS_OFF', 'Text-to-speech disabled'), 'info');
        });
      }
    }
    
    // Send button
    if (this.elements.sendBtn) {
      this.elements.sendBtn.addEventListener('click', () => this.sendMessage());
    }
    
    // Input field
    if (this.elements.input) {
      // Handle Enter key
      this.elements.input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          this.sendMessage();
        }
      });
      
      // Auto-resize textarea
      this.elements.input.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
      });
    }
    
    // Global keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      // Ctrl/Cmd + / to open chat
      if ((e.ctrlKey || e.metaKey) && e.key === '/') {
        e.preventDefault();
        if (!this.isOpen) {
          this.openChat();
        } else {
          this.elements.input?.focus();
        }
      }
      // ESC to close
      if (e.key === 'Escape' && this.isOpen) {
        this.closeChat();
      }
    });
  }

  openChat() {
    this.instance.classList.add('bears-aichatbot--open');
    this.instance.classList.remove('bears-aichatbot--closed');
    this.isOpen = true;
    
    // Update ARIA attributes
    this.elements.toggle.setAttribute('aria-expanded', 'true');
    const window = this.instance.querySelector('.bears-aichatbot-window');
    if (window) {
      window.setAttribute('aria-modal', 'true');
    }
    
    // Anchor to bottom when opened
    this.instance.style.removeProperty('top');
    this.instance.style.removeProperty('transform');
    this.instance.style.setProperty('bottom', `var(--bears-offset-bottom, 20px)`);
    
    // Announce to screen readers
    this.uiManager.announceToScreenReader(this.getLanguageString('MOD_BEARS_AICHATBOT_CHAT_OPENED', 'Chat opened'));
    
    // Focus input
    try { 
      this.elements.input?.focus(); 
    } catch(e) {}
  }

  closeChat() {
    this.instance.classList.remove('bears-aichatbot--open');
    this.instance.classList.add('bears-aichatbot--closed');
    this.isOpen = false;
    
    // Update ARIA attributes
    this.elements.toggle.setAttribute('aria-expanded', 'false');
    const window = this.instance.querySelector('.bears-aichatbot-window');
    if (window) {
      window.setAttribute('aria-modal', 'false');
    }
    
    // Announce to screen readers
    this.uiManager.announceToScreenReader(this.getLanguageString('MOD_BEARS_AICHATBOT_CHAT_CLOSED', 'Chat closed'));
  }

  appendMessage(role, text, isError = false) {
    const wrap = document.createElement('div');
    wrap.className = 'message ' + (role === 'user' ? 'user' : 'bot') + (isError ? ' error' : '');
    wrap.setAttribute('role', 'article');
    
    // Set appropriate ARIA label
    if (role === 'user') {
      wrap.setAttribute('aria-label', this.getLanguageString('MOD_BEARS_AICHATBOT_USER_MESSAGE', 'Your message'));
    } else if (isError) {
      wrap.setAttribute('aria-label', this.getLanguageString('MOD_BEARS_AICHATBOT_ERROR_MESSAGE', 'Error message'));
    } else {
      wrap.setAttribute('aria-label', this.getLanguageString('MOD_BEARS_AICHATBOT_BOT_MESSAGE', 'AI assistant response'));
    }
    
    const bubble = document.createElement('div');
    bubble.className = 'bubble';
    
    if (role === 'bot') {
      bubble.innerHTML = this.messageFormatter.formatAnswer(String(text || ''));
      // Add copy buttons to code blocks
      this.uiManager.addCopyButtonsToCodeBlocks(bubble);
      
      // Speak bot messages if TTS is enabled and not an error
      if (!isError && this.ttsManager.isEnabled()) {
        this.ttsManager.speak(text);
      }
    } else {
      bubble.textContent = String(text || '');
    }
    
    wrap.appendChild(bubble);
    this.elements.messages.appendChild(wrap);
    
    // Auto-scroll to bottom with slight delay for animation
    setTimeout(() => {
      this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
    }, 100);
  }

  setLoading(loading, status) {
    const statusEl = document.getElementById(`chat-status-${this.config.moduleId}`);
    
    if (loading) {
      // Update ARIA attributes for loading state
      this.elements.sendBtn.setAttribute('disabled', 'disabled');
      this.elements.sendBtn.setAttribute('aria-busy', 'true');
      this.elements.sendBtn.classList.add('loading');
      
      if (!this.elements.sendBtn.dataset.prevText) {
        this.elements.sendBtn.dataset.prevText = this.elements.sendBtn.textContent;
      }
      this.elements.sendBtn.innerHTML = '<span style="opacity: 0.8;">Sending...</span>';
      
      // Announce loading status to screen readers
      const statusText = status || this.getLanguageString('MOD_BEARS_AICHATBOT_THINKING', 'AI is researching your question');
      if (statusEl) {
        statusEl.textContent = statusText;
      }
      
      // Add or update thinking indicator in the chat
      if (!this.thinkingIndicator) {
        this.thinkingIndicator = document.createElement('div');
        this.thinkingIndicator.className = 'message bot';
        this.thinkingIndicator.setAttribute('role', 'status');
        this.thinkingIndicator.setAttribute('aria-label', statusText);
        this.thinkingIndicator.innerHTML = `
          <div class="bears-thinking-indicator">
            <span class="bears-thinking-text">Researching</span>
            <div class="bears-thinking-dots">
              <span></span>
              <span></span>
              <span></span>
            </div>
          </div>
        `;
        this.elements.messages.appendChild(this.thinkingIndicator);
      }
      
      // Update status text if provided
      if (status) {
        const textElement = this.thinkingIndicator.querySelector('.bears-thinking-text');
        if (textElement) {
          textElement.textContent = status;
        }
        this.thinkingIndicator.setAttribute('aria-label', status);
      }
      
      this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
    } else {
      // Remove loading state from button
      this.elements.sendBtn.removeAttribute('disabled');
      this.elements.sendBtn.setAttribute('aria-busy', 'false');
      this.elements.sendBtn.classList.remove('loading');
      
      if (this.elements.sendBtn.dataset.prevText) {
        this.elements.sendBtn.textContent = this.elements.sendBtn.dataset.prevText;
      }
      
      // Clear status announcements
      if (statusEl) {
        statusEl.textContent = '';
      }
      
      // Remove thinking indicator
      if (this.thinkingIndicator && this.thinkingIndicator.parentNode) {
        this.thinkingIndicator.remove();
        this.thinkingIndicator = null;
      }
    }
  }

  async sendMessage() {
    const text = (this.elements.input.value || '').trim();
    if (!text) return;
    
    // Check connection status only if enabled
    if (this.connectionStatus.isEnabled() && !this.connectionStatus.isOnline()) {
      this.uiManager.showNotification(
        this.getLanguageString('MOD_BEARS_AICHATBOT_OFFLINE_ERROR', 'You are offline. Please check your connection.'), 
        'error'
      );
      this.soundManager.play('error');
      return;
    }
    
    this.openChat();
    
    try { 
      console.debug('[ChatBot] sending message', { moduleId: this.config.moduleId, text }); 
    } catch (e) {}
    
    // Stop any ongoing TTS when user sends a new message
    this.ttsManager.stop();
    
    this.appendMessage('user', text);
    this.soundManager.play('messageSent');
    this.elements.input.value = '';
    this.setLoading(true);

    try {
      const body = new URLSearchParams();
      body.set('message', text);
      body.set('module_id', this.config.moduleId);

      const res = await fetch(this.config.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      });

      // Update indicator to show we're processing the response
      this.setLoading(true, 'Processing');
      
      // Joomla com_ajax wraps responses in an envelope
      const raw = await res.json();
      const payload = (raw && typeof raw === 'object' && 'data' in raw && raw.data !== null) ? raw.data : raw;

      if (payload && typeof payload === 'object') {
        if (payload.success && (payload.answer || payload.answer === '')) {
          // Small delay to show "Processing" before displaying the answer
          await new Promise(resolve => setTimeout(resolve, 300));
          this.appendMessage('bot', payload.answer);
          this.soundManager.play('messageReceived');
        } else if (payload.error) {
          let err = 'Error: ' + payload.error;
          if (payload.status && !/status\s+\d+/.test(err)) {
            err += ' (status ' + payload.status + ')';
          }
          if (payload.body) {
            const bodyTxt = typeof payload.body === 'string' ? payload.body : JSON.stringify(payload.body);
            err += '\nDetails: ' + bodyTxt.substring(0, 2000);
          }
          this.appendMessage('bot', err, true);
          this.soundManager.play('error');
        } else if ('message' in payload) {
          this.appendMessage('bot', String(payload.message));
          this.soundManager.play('messageReceived');
        } else {
          this.appendMessage('bot', 'No response.');
          this.soundManager.play('error');
        }
      } else if (typeof payload === 'string') {
        this.appendMessage('bot', payload);
        this.soundManager.play('messageReceived');
      } else {
        this.appendMessage('bot', 'Unexpected response.');
        this.soundManager.play('error');
      }
    } catch (e) {
      try { 
        console.error('[ChatBot] fetch error', e); 
      } catch (ignored) {}
      this.appendMessage('bot', 'Error: ' + (e && e.message ? e.message : 'Network error'), true);
      this.soundManager.play('error');
    } finally {
      this.setLoading(false);
    }
  }

  getLanguageString(key, fallback) {
    if (typeof Joomla !== 'undefined' && Joomla.Text && Joomla.Text._(key)) {
      return Joomla.Text._(key);
    }
    return fallback || key;
  }

  destroy() {
    // Clean up connection status
    if (this.connectionStatus.isEnabled()) {
      this.connectionStatus.destroy();
    }
    
    // Stop any ongoing TTS
    this.ttsManager.stop();
  }
}
