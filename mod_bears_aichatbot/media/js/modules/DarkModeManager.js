/**
 * Dark Mode Manager Module
 * Handles dark mode toggling and system preference detection
 */
export class DarkModeManager {
  constructor() {
    this.instance = null;
    this.enabled = false;
  }

  init(instance, adminDarkMode) {
    this.instance = instance;
    
    // Check for user's saved preference first (highest priority)
    const savedDarkMode = localStorage.getItem('bears_chat_dark_mode');
    
    if (savedDarkMode !== null) {
      // User has a saved preference - respect it above all else
      if (savedDarkMode === '1') {
        this.enable();
      } else {
        this.disable();
      }
    } else if (adminDarkMode) {
      // No user preference, but admin enabled dark mode
      this.enable();
    } else {
      // No user preference and admin disabled dark mode - apply auto detection
      this.applyAutoDetection();
    }
  }

  enable() {
    if (this.instance) {
      this.instance.classList.add('bears-dark-mode');
      this.enabled = true;
    }
  }

  disable() {
    if (this.instance) {
      this.instance.classList.remove('bears-dark-mode');
      this.enabled = false;
    }
  }

  toggle() {
    if (this.instance) {
      const isDark = this.instance.classList.toggle('bears-dark-mode');
      this.enabled = isDark;
      localStorage.setItem('bears_chat_dark_mode', isDark ? '1' : '0');
      return isDark;
    }
    return false;
  }

  applyAutoDetection() {
    const shouldUseDark = this.detectSystemDarkMode();
    if (shouldUseDark) {
      this.enable();
    }
    
    // Listen for system dark mode changes
    if (window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        // Only auto-switch if user hasn't manually set preference
        if (localStorage.getItem('bears_chat_dark_mode') === null) {
          if (e.matches) {
            this.enable();
          } else {
            this.disable();
          }
        }
      });
    }
  }

  detectSystemDarkMode() {
    // Check system preference
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return true;
    }
    return false;
  }

  isEnabled() {
    return this.enabled;
  }
}

// Create singleton instance
export const darkModeManager = new DarkModeManager();
