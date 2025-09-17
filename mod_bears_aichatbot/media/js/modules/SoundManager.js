/**
 * Sound Manager Module
 * Handles all sound notifications for the chatbot
 */
export class SoundManager {
  constructor(defaultEnabled = false) {
    this.enabled = true;
    this.defaultEnabled = defaultEnabled;
    this.initialized = false;
    // Using Web Audio API to generate sounds instead of base64 data
    this.audioContext = null;
    this.volume = 0.1; // Default volume
    this.sounds = {
      messageSent: { frequency: 800, duration: 100, type: 'sine' },
      messageReceived: { frequency: 600, duration: 150, type: 'sine' },
      error: { frequency: 300, duration: 200, type: 'square' }
    };
  }

  init(defaultSetting = false, soundConfig = {}) {
    // Prevent multiple initializations
    if (this.initialized) {
      return;
    }
    
    this.initialized = true;
    this.defaultEnabled = defaultSetting;
    
    // Apply sound configuration from module settings
    if (soundConfig.volume !== undefined) {
      this.volume = parseFloat(soundConfig.volume) || 0.1;
    }
    
    if (soundConfig.sentFrequency !== undefined) {
      this.sounds.messageSent.frequency = parseInt(soundConfig.sentFrequency) || 800;
    }
    if (soundConfig.sentDuration !== undefined) {
      this.sounds.messageSent.duration = parseInt(soundConfig.sentDuration) || 100;
    }
    
    if (soundConfig.receivedFrequency !== undefined) {
      this.sounds.messageReceived.frequency = parseInt(soundConfig.receivedFrequency) || 600;
    }
    if (soundConfig.receivedDuration !== undefined) {
      this.sounds.messageReceived.duration = parseInt(soundConfig.receivedDuration) || 150;
    }
    
    if (soundConfig.errorFrequency !== undefined) {
      this.sounds.error.frequency = parseInt(soundConfig.errorFrequency) || 300;
    }
    if (soundConfig.errorDuration !== undefined) {
      this.sounds.error.duration = parseInt(soundConfig.errorDuration) || 200;
    }
    
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
      // Initialize AudioContext on first use (required for browser compatibility)
      if (!this.audioContext) {
        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
      }
      
      const sound = this.sounds[soundType] || this.sounds.messageSent;
      this.playTone(sound.frequency, sound.duration, sound.type);
    } catch (e) {
      console.warn('[SoundManager] Failed to play sound:', e);
    }
  }
  
  playTone(frequency, duration, type = 'sine') {
    if (!this.audioContext) return;
    
    const oscillator = this.audioContext.createOscillator();
    const gainNode = this.audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(this.audioContext.destination);
    
    oscillator.frequency.value = frequency;
    oscillator.type = type;
    
    // Set volume from configuration
    gainNode.gain.value = this.volume;
    
    // Fade out to prevent clicking
    gainNode.gain.exponentialRampToValueAtTime(
      0.01, 
      this.audioContext.currentTime + duration / 1000
    );
    
    oscillator.start(this.audioContext.currentTime);
    oscillator.stop(this.audioContext.currentTime + duration / 1000);
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
