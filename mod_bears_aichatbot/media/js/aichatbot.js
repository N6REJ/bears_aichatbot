(function () {
  // Helper function to get language strings (fallback to English if not available)
  function getLanguageString(key, fallback) {
    // Try to get from Joomla language system if available
    if (typeof Joomla !== 'undefined' && Joomla.Text && Joomla.Text._(key)) {
      return Joomla.Text._(key);
    }
    return fallback || key;
  }

  // Sound notification system
  const SoundManager = {
    enabled: true,
    defaultEnabled: false,
    sounds: {
      messageSent: 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=',
      messageReceived: 'data:audio/wav;base64,UklGRjIAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ4AAAB/f39/f39/f39/f39/f38=',
      error: 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA='
    },
    
    play: function(soundType) {
      if (!this.enabled) return;
      try {
        const audio = new Audio(this.sounds[soundType] || this.sounds.messageSent);
        audio.volume = 0.3;
        audio.play().catch(() => {});
      } catch (e) {}
    },
    
    toggle: function() {
      this.enabled = !this.enabled;
      localStorage.setItem('bears_chat_sounds', this.enabled ? '1' : '0');
      return this.enabled;
    },
    
    init: function(defaultSetting = false) {
      this.defaultEnabled = defaultSetting;
      const saved = localStorage.getItem('bears_chat_sounds');
      if (saved !== null) {
        // User has a saved preference, use it
        this.enabled = saved === '1';
      } else {
        // No saved preference, use the module default
        this.enabled = this.defaultEnabled;
      }
    }
  };

  // Auto dark mode detection
  function detectDarkMode() {
    // Check system preference
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return true;
    }
    // Check if user has saved preference
    const saved = localStorage.getItem('bears_chat_dark_mode');
    if (saved !== null) {
      return saved === '1';
    }
    return false;
  }

  // Apply dark mode based on system preference or saved setting
  function applyAutoDarkMode(instance) {
    const shouldUseDark = detectDarkMode();
    if (shouldUseDark) {
      instance.classList.add('bears-dark-mode');
    }
    
    // Listen for system dark mode changes
    if (window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (localStorage.getItem('bears_chat_dark_mode') === null) {
          // Only auto-switch if user hasn't manually set preference
          if (e.matches) {
            instance.classList.add('bears-dark-mode');
          } else {
            instance.classList.remove('bears-dark-mode');
          }
        }
      });
    }
  }

  // Connection status manager
  const ConnectionStatus = {
    online: true,
    indicator: null,
    
    init: function(container) {
      // Create status indicator
      const indicator = document.createElement('div');
      indicator.className = 'bears-connection-status';
      indicator.innerHTML = `
        <span class="bears-status-dot"></span>
        <span class="bears-status-text"></span>
      `;
      container.appendChild(indicator);
      this.indicator = indicator;
      
      // Initial status
      this.updateStatus(navigator.onLine);
      
      // Listen for online/offline events
      window.addEventListener('online', () => this.updateStatus(true));
      window.addEventListener('offline', () => this.updateStatus(false));
      
      // Periodic connection check
      setInterval(() => this.checkConnection(), 30000);
    },
    
    updateStatus: function(isOnline) {
      this.online = isOnline;
      if (this.indicator) {
        const dot = this.indicator.querySelector('.bears-status-dot');
        const text = this.indicator.querySelector('.bears-status-text');
        
        if (isOnline) {
          dot.className = 'bears-status-dot online';
          text.textContent = getLanguageString('MOD_BEARS_AICHATBOT_STATUS_ONLINE', 'Connected');
          this.indicator.classList.remove('offline');
        } else {
          dot.className = 'bears-status-dot offline';
          text.textContent = getLanguageString('MOD_BEARS_AICHATBOT_STATUS_OFFLINE', 'Offline');
          this.indicator.classList.add('offline');
        }
      }
    },
    
    checkConnection: async function() {
      try {
        const response = await fetch(window.location.origin + '/favicon.ico', {
          method: 'HEAD',
          cache: 'no-cache'
        });
        this.updateStatus(response.ok);
      } catch {
        this.updateStatus(false);
      }
    }
  };

  // Copy conversation functionality
  function copyConversation(messages) {
    const messageElements = messages.querySelectorAll('.message');
    let conversationText = getLanguageString('MOD_BEARS_AICHATBOT_CONVERSATION_HEADER', 'Chat Conversation') + '\n';
    conversationText += '=' .repeat(50) + '\n\n';
    
    messageElements.forEach((msg) => {
      const isUser = msg.classList.contains('user');
      const bubble = msg.querySelector('.bubble');
      const text = bubble ? bubble.textContent.trim() : '';
      
      if (text) {
        const role = isUser ? getLanguageString('MOD_BEARS_AICHATBOT_YOU', 'You') : getLanguageString('MOD_BEARS_AICHATBOT_AI', 'AI');
        const timestamp = new Date().toLocaleTimeString();
        conversationText += `[${timestamp}] ${role}:\n${text}\n\n`;
      }
    });
    
    // Try modern clipboard API first
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(conversationText).then(() => {
        showNotification(getLanguageString('MOD_BEARS_AICHATBOT_CONVERSATION_COPIED', 'Conversation copied to clipboard!'), 'success');
      }).catch(() => {
        fallbackCopy(conversationText);
      });
    } else {
      fallbackCopy(conversationText);
    }
  }

  function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      showNotification(getLanguageString('MOD_BEARS_AICHATBOT_CONVERSATION_COPIED', 'Conversation copied to clipboard!'), 'success');
    } catch (e) {
      showNotification(getLanguageString('MOD_BEARS_AICHATBOT_COPY_FAILED', 'Failed to copy conversation'), 'error');
    }
    document.body.removeChild(textarea);
  }

  function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `bears-notification ${type}`;
    notification.textContent = message;
    notification.setAttribute('role', 'alert');
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Remove after 3 seconds
    setTimeout(() => {
      notification.classList.remove('show');
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }

  // Announce message to screen readers
  function announceToScreenReader(message, priority = 'polite') {
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

  function ensureStyles() {
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
  // Formatting helpers to render AI responses with full Markdown support
  function formatAnswer(text) {
    if (!text) return '';
    let placeholderIndex = 0;
    const blocks = [];
    // Extract fenced code blocks and replace with placeholders
    let out = String(text).replace(/```([\s\S]*?)```/g, function (_, code) {
      const token = '[[[CODEBLOCK_' + (placeholderIndex++) + ']]]';
      blocks.push(code);
      return token;
    });

    // Convert Markdown autolinks <url> to placeholders (outside code)
    const autoLinks = [];
    out = out.replace(/<((?:https?|mailto):[^\s>]+)>/g, function (_, url) {
      const token = '[[[AUTOLINK_' + (autoLinks.length) + ']]]';
      autoLinks.push(url);
      return token;
    });

    // Handle custom knowledge tags: keep inner content, drop the <kb> wrappers
    // Do this BEFORE escaping to preserve the content for Markdown processing
    out = out.replace(/<kb>([\s\S]*?)<\/kb>/gi, function (_, inner) { return inner; });
    // Remove any stray self-closing or unmatched kb tags
    out = out.replace(/<\/?kb\s*\/?>(?=\s|$)/gi, '');

    // Escape HTML for remaining text
    out = escapeHtml(out);

    // Inline code `...` - protect with placeholders
    const inlineCodes = [];
    out = out.replace(/`([^`]+)`/g, function (_, code) {
      const token = '[[[INLINECODE_' + inlineCodes.length + ']]]';
      inlineCodes.push(code);
      return token;
    });

    // Markdown links [text](url)
    out = out.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1<\/a>');

    // Autolink any remaining plain URLs
    out = autolink(out);

    // Restore autolink placeholders
    out = out.replace(/\[\[\[AUTOLINK_(\d+)\]\]\]/g, function (_, idx) {
      const url = autoLinks[Number(idx)] || '';
      const safeHref = url.replace(/\"/g, '&quot;');
      return '<a href="' + safeHref + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(url) + '<\/a>';
    });

    // Extended Markdown conversions
    // Unescape common Markdown escapes
    out = unescapeMarkdownEscapes(out);
    
    // Horizontal rules (---, ***, ___)
    out = out.replace(/^(?:\s*)(?:[-*_]){3,}\s*$/gm, '<hr>');
    
    // Images ![alt](url)
    out = out.replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)]+)\)/g, '<img src="$2" alt="$1">');
    
    // Strikethrough ~~text~~
    out = out.replace(/~~([^~]+)~~/g, '<del>$1<\/del>');
    
    // Headings and blockquotes
    out = headingify(out);
    out = blockquoteify(out);
    
    // Apply inline emphasis (bold/italic) - but protect placeholders
    out = applyInlineMd(out);

    // Restore code blocks BEFORE list processing
    // Restore inline code first
    out = out.replace(/\[\[\[INLINECODE_(\d+)\]\]\]/g, function (_, idx) {
      const code = inlineCodes[Number(idx)] || '';
      return '<code>' + escapeHtml(code) + '<\/code>';
    });

    // Restore fenced code blocks
    out = out.replace(/\[\[\[CODEBLOCK_(\d+)\]\]\]/g, function (_, idx) {
      const code = blocks[Number(idx)] || '';
      return '<pre><code>' + escapeHtml(code) + '<\/code><\/pre>';
    });

    // Normalize escaped ordered list markers like "1\."
    out = unescapeOrderedListMarkers(out);

    // Convert numbered lists 1. 2. ... into <ol>
    out = orderedListify(out);

    // Convert bullet lists
    out = listify(out);

    // Convert remaining text blocks/newlines into paragraphs and <br>
    out = paragraphify(out);

    return out;
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function (s) {
      switch (s) {
        case '&': return '&amp;';
        case '<': return '&lt;';
        case '>': return '&gt;';
        case '"': return '&quot;';
        case "'": return '&#39;';
        default: return s;
      }
    });
  }

  function autolink(str) {
    return String(str).replace(/(https?:\/\/[^\s<]+)(?![^<]*>)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1<\/a>');
  }

  function listify(html) {
    const lines = String(html).split(/\r?\n/);
    const out = [];
    let inList = false;
    for (let i = 0; i < lines.length; i++) {
      const ln = lines[i];
      if (/^\s*[-*]\s+/.test(ln)) {
        if (!inList) { out.push('<ul>'); inList = true; }
        out.push('<li>' + ln.replace(/^\s*[-*]\s+/, '') + '</li>');
      } else {
        if (inList) { out.push('</ul>'); inList = false; }
        out.push(ln);
      }
    }
    if (inList) out.push('</ul>');
    return out.join('\n');
  }

  function orderedListify(html) {
    const lines = String(html).split(/\r?\n/);
    const out = [];
    let inList = false;
    for (let i = 0; i < lines.length; i++) {
      const ln = lines[i];
      const m = ln.match(/^\s*(\d+)[\.)]\s+(.*)$/);
      if (m) {
        if (!inList) { out.push('<ol>'); inList = true; }
        out.push('<li>' + m[2] + '</li>');
      } else {
        if (inList) { out.push('</ol>'); inList = false; }
        out.push(ln);
      }
    }
    if (inList) out.push('</ol>');
    return out.join('\n');
  }

  function unescapeOrderedListMarkers(str) {
    // Turn patterns like "1\. " into "1. " produced by LLM escaping
    return String(str).replace(/(\d+)\\\./g, '$1.');
  }

  // Unescape common Markdown escapes like \* \_ \~ \[ \] etc.
  function unescapeMarkdownEscapes(str) {
    return String(str).replace(/\\([\\`*_~\[\](){}#+.!-])/g, '$1');
  }

  // Convert ATX-style headings (# .. ###### ..) per line
  function headingify(html) {
    return String(html).replace(/^\s{0,3}(#{1,6})\s+(.+)$/gm, function (_, hashes, txt) {
      const lvl = Math.min(Math.max(hashes.length, 1), 6);
      return '<h' + lvl + '>' + txt.trim() + '</h' + lvl + '>';
    });
  }

  // Convert blockquotes; lines starting with '>' or '&gt;' are grouped
  function blockquoteify(html) {
    const lines = String(html).split(/\r?\n/);
    const out = [];
    let inBQ = false; let buf = [];
    function flush() { 
      if (inBQ) { 
        out.push('<blockquote>' + buf.join('<br>') + '</blockquote>'); 
        buf = []; 
        inBQ = false; 
      } 
    }
    for (let i = 0; i < lines.length; i++) {
      const ln = lines[i];
      const t = ln.trim();
      // Check for both > and &gt; (escaped version)
      if (/^(?:&gt;|>)\s?/.test(t)) { 
        inBQ = true; 
        buf.push(t.replace(/^(?:&gt;|>)\s?/, '')); 
      } else { 
        flush(); 
        out.push(ln); 
      }
    }
    flush();
    return out.join('\n');
  }

  // Inline emphasis: bold (**, __) and italic (*, _) with simple rules
  function applyInlineMd(str) {
    let s = String(str);
    
    // Protect placeholders from being processed
    const placeholders = [];
    s = s.replace(/\[\[\[(?:CODEBLOCK|INLINECODE|AUTOLINK)_\d+\]\]\]/g, function(match) {
      placeholders.push(match);
      return '%%%PLACEHOLDER_' + (placeholders.length - 1) + '%%%';
    });
    
    // Bold - match ** or __ 
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    
    // Italic - only match when it's clearly intended as emphasis
    // Don't match underscores in the middle of words (like INLINECODE_0)
    s = s.replace(/(\s|^)\*([^*\n]+)\*(\s|$|[.,!?;:])/g, '$1<em>$2</em>$3');
    s = s.replace(/(\s|^)_([^_\n]+)_(\s|$|[.,!?;:])/g, '$1<em>$2</em>$3');
    
    // Restore placeholders
    s = s.replace(/%%%PLACEHOLDER_(\d+)%%%/g, function(_, idx) {
      return placeholders[Number(idx)] || '';
    });
    
    return s;
  }

  function paragraphify(html) {
    const lines = String(html).split(/\r?\n/);
    const out = [];
    let para = [];
    const isBlockLine = (ln) => /^(<\s*\/?\s*(ul|ol|li|pre|code|table|thead|tbody|tr|td|th|blockquote|h[1-6]|hr)\b|<\s*\/\s*(ul|ol|pre|table|blockquote|h[1-6]|hr))/i.test(ln.trim());

    function flushPara() {
      if (!para.length) return;
      const content = para.join('<br>');
      out.push('<p>' + content + '</p>');
      para = [];
    }

    for (let i = 0; i < lines.length; i++) {
      const ln = lines[i];
      const trimmed = ln.trim();
      if (!trimmed) {
        // Blank line: paragraph break
        flushPara();
        continue;
      }
      if (isBlockLine(trimmed)) {
        // Block element: break paragraph and output as-is
        flushPara();
        out.push(trimmed);
      } else {
        para.push(trimmed);
      }
    }
    flushPara();
    return out.join('\n');
  }

  function init(instance) {
    const ajaxUrl = instance.getAttribute('data-ajax-url');
    const moduleId = instance.getAttribute('data-module-id');
    const position = instance.getAttribute('data-position') || 'bottom-right';
    const offsetBottom = parseInt(instance.getAttribute('data-offset-bottom') || '20', 10);
    const offsetSide = parseInt(instance.getAttribute('data-offset-side') || '20', 10);
    const openWidth = parseInt(instance.getAttribute('data-open-width') || '400', 10);
    const openHeight = parseInt(instance.getAttribute('data-open-height') || '500', 10);
    const buttonLabel = instance.getAttribute('data-button-label') || 'Knowledgebase';
    const darkMode = instance.getAttribute('data-dark-mode') === '1';
    const soundNotifications = instance.getAttribute('data-sound-notifications') === '1';
    
    // Initialize sound manager with module default setting
    SoundManager.init(soundNotifications);
    
    try {
      console.debug('[Bears AI Chatbot] init', { moduleId, ajaxUrl, position, offsetBottom, offsetSide, darkMode });
    } catch (e) {}
    
    // Apply dark mode if enabled or auto-detected
    if (darkMode) {
      instance.classList.add('bears-dark-mode');
      localStorage.setItem('bears_chat_dark_mode', '1');
    } else {
      // Apply auto dark mode detection
      applyAutoDarkMode(instance);
    }

    // Apply offsets via CSS variables
    instance.style.setProperty('--bears-offset-bottom', offsetBottom + 'px');
    instance.style.setProperty('--bears-offset-side', offsetSide + 'px');

    instance.setAttribute('data-position', position);

    // Apply open width/height as CSS variables for dynamic sizing
    instance.style.setProperty('--bears-open-width', `min(${openWidth}px, 90vw)`);
    // Use pixel height but cap at 90% of viewport for mobile
    instance.style.setProperty('--bears-open-height', `min(${openHeight}px, 90vh)`);

    // Inject styles and set initial state (closed)
    ensureStyles();
    instance.classList.add('bears-aichatbot--closed');

    // Create toggle (bubble) and header close button
    const toggle = document.createElement('button');
    toggle.className = 'bears-aichatbot-toggle btn btn-primary';
    toggle.setAttribute('aria-label', getLanguageString('MOD_BEARS_AICHATBOT_OPEN_CHAT', 'Open AI chatbot'));
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-describedby', `chat-description-${moduleId}`);
    toggle.title = getLanguageString('MOD_BEARS_AICHATBOT_OPEN_CHAT', 'Open AI chatbot');
    // Vertical labeled toggle for middle positions
    if (position === 'middle-right' || position === 'middle-left') {
      toggle.textContent = buttonLabel;
    } else {
      toggle.textContent = '💬';
      toggle.setAttribute('aria-label', `${getLanguageString('MOD_BEARS_AICHATBOT_OPEN_CHAT', 'Open AI chatbot')} (Chat icon)`);
    }
    instance.appendChild(toggle);

    const headerEl = instance.querySelector('.bears-aichatbot-header');
    let closeBtn = headerEl && headerEl.querySelector('.bears-aichatbot-close');
    if (!closeBtn && headerEl) {
      closeBtn = document.createElement('button');
      closeBtn.className = 'bears-aichatbot-close';
      closeBtn.setAttribute('aria-label', getLanguageString('MOD_BEARS_AICHATBOT_CLOSE_CHAT', 'Close chatbot'));
      closeBtn.type = 'button';
      closeBtn.textContent = '×';
      headerEl.appendChild(closeBtn);
    }

    // Add toolbar buttons (copy conversation, sound toggle)
    if (headerEl) {
      const toolbar = document.createElement('div');
      toolbar.className = 'bears-chat-toolbar';
      
      // Copy conversation button
      const copyBtn = document.createElement('button');
      copyBtn.className = 'bears-toolbar-btn copy-btn';
      copyBtn.setAttribute('aria-label', getLanguageString('MOD_BEARS_AICHATBOT_COPY_CONVERSATION', 'Copy conversation'));
      copyBtn.title = getLanguageString('MOD_BEARS_AICHATBOT_COPY_CONVERSATION', 'Copy conversation');
      copyBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
      
      // Sound toggle button
      const soundBtn = document.createElement('button');
      soundBtn.className = 'bears-toolbar-btn sound-btn' + (SoundManager.enabled ? ' enabled' : '');
      soundBtn.setAttribute('aria-label', getLanguageString('MOD_BEARS_AICHATBOT_TOGGLE_SOUND', 'Toggle sound notifications'));
      soundBtn.title = getLanguageString('MOD_BEARS_AICHATBOT_TOGGLE_SOUND', 'Toggle sound notifications');
      soundBtn.innerHTML = SoundManager.enabled ? 
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>' :
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>';
      
      // Dark mode toggle button
      const darkBtn = document.createElement('button');
      darkBtn.className = 'bears-toolbar-btn dark-btn';
      darkBtn.setAttribute('aria-label', getLanguageString('MOD_BEARS_AICHATBOT_TOGGLE_DARK', 'Toggle dark mode'));
      darkBtn.title = getLanguageString('MOD_BEARS_AICHATBOT_TOGGLE_DARK', 'Toggle dark mode');
      darkBtn.innerHTML = instance.classList.contains('bears-dark-mode') ?
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>' :
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
      
      toolbar.appendChild(copyBtn);
      toolbar.appendChild(soundBtn);
      toolbar.appendChild(darkBtn);
      headerEl.insertBefore(toolbar, closeBtn);
      
      // Event listeners for toolbar buttons
      copyBtn.addEventListener('click', () => {
        const messages = instance.querySelector('.bears-aichatbot-messages');
        copyConversation(messages);
      });
      
      soundBtn.addEventListener('click', () => {
        const enabled = SoundManager.toggle();
        soundBtn.classList.toggle('enabled', enabled);
        soundBtn.innerHTML = enabled ? 
          '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>' :
          '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>';
        showNotification(enabled ? 
          getLanguageString('MOD_BEARS_AICHATBOT_SOUND_ON', 'Sound notifications enabled') : 
          getLanguageString('MOD_BEARS_AICHATBOT_SOUND_OFF', 'Sound notifications disabled'), 'info');
      });
      
      darkBtn.addEventListener('click', () => {
        const isDark = instance.classList.toggle('bears-dark-mode');
        localStorage.setItem('bears_chat_dark_mode', isDark ? '1' : '0');
        darkBtn.innerHTML = isDark ?
          '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>' :
          '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
      });
    }

    // Initialize connection status indicator
    const windowEl = instance.querySelector('.bears-aichatbot-window');
    if (windowEl) {
      ConnectionStatus.init(windowEl);
    }

    function openChat() {
      instance.classList.add('bears-aichatbot--open');
      instance.classList.remove('bears-aichatbot--closed');
      // Update ARIA attributes
      toggle.setAttribute('aria-expanded', 'true');
      const window = instance.querySelector('.bears-aichatbot-window');
      if (window) {
        window.setAttribute('aria-modal', 'true');
      }
      // Anchor to bottom when opened
      instance.style.removeProperty('top');
      instance.style.removeProperty('transform');
      instance.style.setProperty('bottom', `var(--bears-offset-bottom, 20px)`);
      // Announce to screen readers
      announceToScreenReader(getLanguageString('MOD_BEARS_AICHATBOT_CHAT_OPENED', 'Chat opened'));
      try { input && input.focus(); } catch(e) {}
    }
    function closeChat() {
      instance.classList.remove('bears-aichatbot--open');
      instance.classList.add('bears-aichatbot--closed');
      // Update ARIA attributes
      toggle.setAttribute('aria-expanded', 'false');
      const window = instance.querySelector('.bears-aichatbot-window');
      if (window) {
        window.setAttribute('aria-modal', 'false');
      }
      // Announce to screen readers
      announceToScreenReader(getLanguageString('MOD_BEARS_AICHATBOT_CHAT_CLOSED', 'Chat closed'));
    }
    toggle.addEventListener('click', openChat);
    if (closeBtn) closeBtn.addEventListener('click', closeChat);

    const messages = instance.querySelector('.bears-aichatbot-messages');
    const input = instance.querySelector('.bears-aichatbot-text');
    const sendBtn = instance.querySelector('.bears-aichatbot-send');

    function appendMessage(role, text, isError = false) {
      const wrap = document.createElement('div');
      wrap.className = 'message ' + (role === 'user' ? 'user' : 'bot') + (isError ? ' error' : '');
      wrap.setAttribute('role', 'article');
      
      // Set appropriate ARIA label
      if (role === 'user') {
        wrap.setAttribute('aria-label', getLanguageString('MOD_BEARS_AICHATBOT_USER_MESSAGE', 'Your message'));
      } else if (isError) {
        wrap.setAttribute('aria-label', getLanguageString('MOD_BEARS_AICHATBOT_ERROR_MESSAGE', 'Error message'));
      } else {
        wrap.setAttribute('aria-label', getLanguageString('MOD_BEARS_AICHATBOT_BOT_MESSAGE', 'AI assistant response'));
      }
      
      const bubble = document.createElement('div');
      bubble.className = 'bubble';
      if (role === 'bot') {
        bubble.innerHTML = formatAnswer(String(text || ''));
        // Add copy buttons to code blocks
        addCopyButtons(bubble);
      } else {
        bubble.textContent = String(text || '');
      }
      wrap.appendChild(bubble);
      messages.appendChild(wrap);
      
      // Auto-scroll to bottom with slight delay for animation
      setTimeout(() => {
        messages.scrollTop = messages.scrollHeight;
      }, 100);
    }

    // Add copy functionality to code blocks
    function addCopyButtons(container) {
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

    let thinkingIndicator = null;

    function setLoading(loading, status) {
      const statusEl = document.getElementById(`chat-status-${moduleId}`);
      
      if (loading) {
        // Update ARIA attributes for loading state
        sendBtn.setAttribute('disabled', 'disabled');
        sendBtn.setAttribute('aria-busy', 'true');
        sendBtn.classList.add('loading');
        if (!sendBtn.dataset.prevText) {
          sendBtn.dataset.prevText = sendBtn.textContent;
        }
        sendBtn.innerHTML = '<span style="opacity: 0.8;">Sending...</span>';
        
        // Announce loading status to screen readers
        const statusText = status || getLanguageString('MOD_BEARS_AICHATBOT_THINKING', 'AI is researching your question');
        if (statusEl) {
          statusEl.textContent = statusText;
        }
        
        // Add or update thinking indicator in the chat
        if (!thinkingIndicator) {
          thinkingIndicator = document.createElement('div');
          thinkingIndicator.className = 'message bot';
          thinkingIndicator.setAttribute('role', 'status');
          thinkingIndicator.setAttribute('aria-label', statusText);
          thinkingIndicator.innerHTML = `
            <div class="bears-thinking-indicator">
              <span class="bears-thinking-text">Researching</span>
              <div class="bears-thinking-dots">
                <span></span>
                <span></span>
                <span></span>
              </div>
            </div>
          `;
          messages.appendChild(thinkingIndicator);
        }
        
        // Update status text if provided
        if (status) {
          const textElement = thinkingIndicator.querySelector('.bears-thinking-text');
          if (textElement) {
            textElement.textContent = status;
          }
          thinkingIndicator.setAttribute('aria-label', status);
        }
        
        messages.scrollTop = messages.scrollHeight;
      } else {
        // Remove loading state from button
        sendBtn.removeAttribute('disabled');
        sendBtn.setAttribute('aria-busy', 'false');
        sendBtn.classList.remove('loading');
        if (sendBtn.dataset.prevText) {
          sendBtn.textContent = sendBtn.dataset.prevText;
        }
        
        // Clear status announcements
        if (statusEl) {
          statusEl.textContent = '';
        }
        
        // Remove thinking indicator
        if (thinkingIndicator && thinkingIndicator.parentNode) {
          thinkingIndicator.remove();
          thinkingIndicator = null;
        }
      }
    }

    async function sendMessage() {
      const text = (input.value || '').trim();
      if (!text) return;
      
      // Check connection status
      if (!ConnectionStatus.online) {
        showNotification(getLanguageString('MOD_BEARS_AICHATBOT_OFFLINE_ERROR', 'You are offline. Please check your connection.'), 'error');
        SoundManager.play('error');
        return;
      }
      
      openChat();
      try { console.debug('[Bears AI Chatbot] sending message', { moduleId, text }); } catch (e) {}
      appendMessage('user', text);
      SoundManager.play('messageSent');
      input.value = '';
      setLoading(true);

      try {
        const body = new URLSearchParams();
        body.set('message', text);
        body.set('module_id', moduleId);

        try { console.debug('[Bears AI Chatbot] fetch', ajaxUrl, { body: body.toString() }); } catch (e) {}
        const res = await fetch(ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: body.toString()
        });

        try { console.debug('[Bears AI Chatbot] response', { status: res.status, ok: res.ok }); } catch (e) {}
        
        // Update indicator to show we're processing the response
        setLoading(true, 'Processing');
        
        // Joomla com_ajax wraps responses in an envelope: { success, data, messages }
        // Our helper returns an object inside data. Unwrap it here.
        const raw = await res.json();
        const payload = (raw && typeof raw === 'object' && 'data' in raw && raw.data !== null) ? raw.data : raw;
        try { console.debug('[Bears AI Chatbot] raw', raw); console.debug('[Bears AI Chatbot] payload', payload); } catch (e) {}

        if (payload && typeof payload === 'object') {
          if (payload.success && (payload.answer || payload.answer === '')) {
            try { console.info('[Bears AI Chatbot] answer', { length: (payload.answer || '').length }); } catch (e) {}
            // Small delay to show "Processing" before displaying the answer
            await new Promise(resolve => setTimeout(resolve, 300));
            appendMessage('bot', payload.answer);
            SoundManager.play('messageReceived');
          } else if (payload.error) {
            let err = 'Error: ' + payload.error;
            if (payload.status && !/status\s+\d+/.test(err)) {
              err += ' (status ' + payload.status + ')';
            }
            if (payload.body) {
              const bodyTxt = typeof payload.body === 'string' ? payload.body : JSON.stringify(payload.body);
              err += '\nDetails: ' + bodyTxt.substring(0, 2000);
            }
            try { console.warn('[Bears AI Chatbot] error payload', payload); } catch (e) {}
            appendMessage('bot', err);
            SoundManager.play('error');
          } else if ('message' in payload) {
            appendMessage('bot', String(payload.message));
            SoundManager.play('messageReceived');
          } else {
            appendMessage('bot', 'No response.');
            SoundManager.play('error');
          }
        } else if (typeof payload === 'string') {
          appendMessage('bot', payload);
          SoundManager.play('messageReceived');
        } else {
          appendMessage('bot', 'Unexpected response.');
          SoundManager.play('error');
        }
      } catch (e) {
        try { console.error('[Bears AI Chatbot] fetch error', e); } catch (ignored) {}
        appendMessage('bot', 'Error: ' + (e && e.message ? e.message : 'Network error'));
        SoundManager.play('error');
      } finally {
        setLoading(false);
      }
    }

    sendBtn.addEventListener('click', sendMessage);
    
    // Handle Enter key and auto-resize
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
    
    // Auto-resize textarea as user types
    input.addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    
    // Keyboard shortcut to open chat (Ctrl/Cmd + /)
    document.addEventListener('keydown', function(e) {
      if ((e.ctrlKey || e.metaKey) && e.key === '/') {
        e.preventDefault();
        if (instance.classList.contains('bears-aichatbot--closed')) {
          openChat();
        } else {
          input.focus();
        }
      }
      // ESC to close
      if (e.key === 'Escape' && instance.classList.contains('bears-aichatbot--open')) {
        closeChat();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    const nodes = document.querySelectorAll('.bears-aichatbot');
    try { console.debug('[Bears AI Chatbot] found instances', nodes.length); } catch (e) {}
    nodes.forEach(init);
  });
})();
