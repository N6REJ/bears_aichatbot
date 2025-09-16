/**
 * Sound Manager Module
 * Handles all sound notifications for the chatbot
 */
export class SoundManager {
  constructor(defaultEnabled = false) {
    this.enabled = true;
    this.defaultEnabled = defaultEnabled;
    this.initialized = false;
    this.sounds = {
      messageSent: 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=',
      messageReceived: 'data:audio/wav;base64,UklGRjIAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ4AAAB/f39/f39/f39/f39/f38=',
      error: 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA='
    };
  }

  init(defaultSetting = false) {
    // Prevent multiple initializations
    if (this.initialized) {
      return;
    }
    
    this.initialized = true;
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

  play(soundType) {
    if (!this.enabled) return;
    
    try {
      const audio = new Audio(this.sounds[soundType] || this.sounds.messageSent);
      audio.volume = 0.3;
      audio.play().catch(() => {});
    } catch (e) {
      console.warn('[SoundManager] Failed to play sound:', e);
    }
  }

  toggle() {
    this.enabled = !this.enabled;
    localStorage.setItem('bears_chat_sounds', this.enabled ? '1' : '0');
    return this.enabled;
  }

  isEnabled() {
    return this.enabled;
  }
}

// Create singleton instance
export const soundManager = new SoundManager();
