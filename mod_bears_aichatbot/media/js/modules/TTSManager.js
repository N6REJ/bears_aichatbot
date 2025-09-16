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
    this.rate = 0.9;  // Default slightly slower for clarity
    this.pitch = 1.0; // Default normal pitch
    this.volume = 0.8; // Default 80% volume
  }

  init(defaultSetting = false, rate = 0.9, pitch = 1.0, volume = 0.8) {
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
    this.rate = rate;
    this.pitch = pitch;
    this.volume = volume;
    
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
      // Ensure voices are loaded before speaking
      const speakText = () => {
        const utterance = new SpeechSynthesisUtterance(cleanText);
        utterance.rate = this.rate;   // Use configured rate (default 0.9 for slightly slower)
        utterance.pitch = this.pitch;  // Use configured pitch
        utterance.volume = this.volume; // Use configured volume
        
        // Set language if available
        utterance.lang = document.documentElement.lang || 'en-US';
        
        // Try to select a voice that matches the language
        const voices = window.speechSynthesis.getVoices();
        if (voices.length > 0) {
          const lang = utterance.lang.substring(0, 2); // Get language code (e.g., 'en' from 'en-US')
          const voice = voices.find(v => v.lang.startsWith(lang)) || voices[0];
          if (voice) {
            utterance.voice = voice;
          }
        }
        
        // Track speaking state
        utterance.onstart = () => {
          this.speaking = true;
          console.debug('[TTSManager] TTS started');
        };
        
        utterance.onend = () => {
          this.speaking = false;
          console.debug('[TTSManager] TTS ended');
        };
        
        utterance.onerror = (event) => {
          this.speaking = false;
          console.error('[TTSManager] TTS error:', event.error);
        };
        
        // Speak the text
        window.speechSynthesis.speak(utterance);
      };
      
      // Check if voices are already loaded
      if (window.speechSynthesis.getVoices().length > 0) {
        speakText();
      } else {
        // Wait for voices to load
        window.speechSynthesis.addEventListener('voiceschanged', function onVoicesChanged() {
          window.speechSynthesis.removeEventListener('voiceschanged', onVoicesChanged);
          speakText();
        });
        // Trigger voice loading
        window.speechSynthesis.getVoices();
      }
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
