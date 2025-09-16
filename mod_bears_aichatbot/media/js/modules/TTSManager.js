/**
 * Text-to-Speech Manager Module
 * Handles text-to-speech functionality for AI responses
 */
export class TTSManager {
  constructor() {
    this.enabled = false;
    this.defaultEnabled = false;
    this.speaking = false;
    this.supported = false;
    this.initialized = false;
  }

  init(defaultSetting = false) {
    // Prevent multiple initializations
    if (this.initialized) {
      return;
    }
    
    this.initialized = true;
    
    // Check if TTS is supported
    this.supported = 'speechSynthesis' in window;
    
    if (!this.supported) {
      console.warn('[TTSManager] Text-to-speech not supported in this browser');
      return;
    }
    
    this.defaultEnabled = defaultSetting;
    const saved = localStorage.getItem('bears_chat_tts');
    
    if (saved !== null) {
      // User has a saved preference, use it
      this.enabled = saved === '1';
    } else {
      // No saved preference, use the module default
      this.enabled = this.defaultEnabled;
    }
  }

  speak(text) {
    if (!this.enabled || !this.supported) return;
    
    // Stop any current speech
    this.stop();
    
    // Clean the text for speech
    const cleanText = this.cleanTextForSpeech(text);
    if (!cleanText) return;
    
    try {
      const utterance = new SpeechSynthesisUtterance(cleanText);
      utterance.rate = 1.0;  // Normal speed
      utterance.pitch = 1.0; // Normal pitch
      utterance.volume = 0.8; // 80% volume
      
      // Set language if available
      utterance.lang = document.documentElement.lang || 'en-US';
      
      // Track speaking state
      utterance.onstart = () => {
        this.speaking = true;
      };
      
      utterance.onend = () => {
        this.speaking = false;
      };
      
      utterance.onerror = () => {
        this.speaking = false;
      };
      
      // Speak the text
      window.speechSynthesis.speak(utterance);
    } catch (e) {
      console.error('[TTSManager] TTS error:', e);
    }
  }

  stop() {
    if (this.supported && window.speechSynthesis.speaking) {
      window.speechSynthesis.cancel();
      this.speaking = false;
    }
  }

  toggle() {
    if (!this.supported) {
      return false;
    }
    
    this.enabled = !this.enabled;
    localStorage.setItem('bears_chat_tts', this.enabled ? '1' : '0');
    
    // Stop speaking if disabling
    if (!this.enabled) {
      this.stop();
    }
    
    return this.enabled;
  }

  cleanTextForSpeech(text) {
    if (!text) return '';
    
    // Create a temporary div to parse HTML
    const temp = document.createElement('div');
    temp.innerHTML = text;
    
    // Remove script and style elements
    const scripts = temp.querySelectorAll('script, style');
    scripts.forEach(el => el.remove());
    
    // Get text content
    let cleanText = temp.textContent || temp.innerText || '';
    
    // Remove markdown-style formatting
    cleanText = cleanText
      .replace(/#{1,6}\s+/g, '') // Remove heading markers
      .replace(/\*{1,2}([^*]+)\*{1,2}/g, '$1') // Remove bold/italic markers
      .replace(/`([^`]+)`/g, '$1') // Remove code markers
      .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1') // Convert links to text
      .replace(/^[-*+]\s+/gm, '') // Remove list markers
      .replace(/^\d+\.\s+/gm, '') // Remove numbered list markers
      .replace(/^>\s+/gm, '') // Remove blockquote markers
      .replace(/\n{3,}/g, '\n\n') // Normalize multiple newlines
      .trim();
    
    return cleanText;
  }

  isEnabled() {
    return this.enabled;
  }

  isSupported() {
    return this.supported;
  }
}

// Create singleton instance
export const ttsManager = new TTSManager();
