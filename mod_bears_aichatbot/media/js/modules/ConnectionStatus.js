/**
 * Connection Status Manager Module
 * Monitors and displays network connectivity status
 */
export class ConnectionStatus {
  constructor() {
    this.online = true;
    this.indicator = null;
    this.intervalId = null;
    this.onlineHandler = null;
    this.offlineHandler = null;
    this.checkInterval = 60000; // Default to 60 seconds
    this.lastCheckTime = 0;
    this.enabled = true;
    this.ajaxUrl = null;
    this.container = null;
  }

  init(container, ajaxUrl, intervalSeconds) {
    // Set check interval from config (convert seconds to milliseconds)
    if (typeof intervalSeconds === 'number') {
      if (intervalSeconds === 0) {
        this.enabled = false;
        return; // Don't initialize if disabled
      }
      this.checkInterval = intervalSeconds * 1000;
    }
    
    this.container = container;
    this.ajaxUrl = ajaxUrl;
    
    // Create status indicator
    const indicator = document.createElement('div');
    indicator.className = 'bears-connection-status';
    indicator.innerHTML = `
      <span class="bears-status-dot"></span>
      <span class="bears-status-text"></span>
    `;
    container.appendChild(indicator);
    this.indicator = indicator;
    
    // Initial status based on navigator.onLine
    this.updateStatus(navigator.onLine);
    
    // Create bound handlers for cleanup
    this.onlineHandler = () => this.handleOnline();
    this.offlineHandler = () => this.handleOffline();
    
    // Listen for online/offline events
    window.addEventListener('online', this.onlineHandler);
    window.addEventListener('offline', this.offlineHandler);
    
    // Only start periodic check if we're online
    if (navigator.onLine) {
      this.startPeriodicCheck();
    }
  }

  handleOnline() {
    this.updateStatus(true);
    // Resume periodic checking when back online
    this.startPeriodicCheck();
  }

  handleOffline() {
    this.updateStatus(false);
    // Stop periodic checking when offline to save resources
    this.stopPeriodicCheck();
  }

  startPeriodicCheck() {
    // Clear any existing interval
    this.stopPeriodicCheck();
    
    // Start new interval with smart checking
    this.intervalId = setInterval(() => {
      // Only check if tab is visible and enough time has passed
      if (!document.hidden && Date.now() - this.lastCheckTime > 30000) {
        this.checkConnection();
      }
    }, this.checkInterval);
  }

  stopPeriodicCheck() {
    if (this.intervalId) {
      clearInterval(this.intervalId);
      this.intervalId = null;
    }
  }

  updateStatus(isOnline) {
    this.online = isOnline;
    if (this.indicator) {
      const dot = this.indicator.querySelector('.bears-status-dot');
      const text = this.indicator.querySelector('.bears-status-text');
      
      if (isOnline) {
        dot.className = 'bears-status-dot online';
        text.textContent = this.getLanguageString('MOD_BEARS_AICHATBOT_STATUS_ONLINE', 'Connected');
        this.indicator.classList.remove('offline');
      } else {
        dot.className = 'bears-status-dot offline';
        text.textContent = this.getLanguageString('MOD_BEARS_AICHATBOT_STATUS_OFFLINE', 'Offline');
        this.indicator.classList.add('offline');
      }
    }
  }

  async checkConnection() {
    // Prevent too frequent checks
    const now = Date.now();
    if (now - this.lastCheckTime < 30000) {
      return;
    }
    this.lastCheckTime = now;
    
    try {
      // Use a lightweight approach - try multiple methods
      
      // Method 1: If we have the AJAX URL, use it with a lightweight request
      if (this.ajaxUrl) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout
        
        try {
          const response = await fetch(this.ajaxUrl, {
            method: 'HEAD',
            mode: 'no-cors', // Avoid CORS issues
            cache: 'no-store', // Prevent caching
            signal: controller.signal
          });
          clearTimeout(timeoutId);
          
          // With no-cors, we can't read the response but no error means success
          this.updateStatus(true);
          return;
        } catch (fetchError) {
          clearTimeout(timeoutId);
          // If aborted or network error, we're offline
          if (fetchError.name === 'AbortError' || !navigator.onLine) {
            this.updateStatus(false);
            return;
          }
        }
      }
      
      // Method 2: Fallback to a simple image load test
      const testUrl = window.location.origin + '/media/system/images/calendar.png';
      const img = new Image();
      
      const promise = new Promise((resolve, reject) => {
        img.onload = () => resolve(true);
        img.onerror = () => reject(false);
        setTimeout(() => reject(false), 5000); // 5 second timeout
      });
      
      img.src = testUrl + '?t=' + now; // Cache bust
      
      const result = await promise.catch(() => false);
      this.updateStatus(result);
      
    } catch (error) {
      // If all methods fail, rely on navigator.onLine
      this.updateStatus(navigator.onLine);
    }
  }

  // Helper method to get language strings
  getLanguageString(key, fallback) {
    if (typeof Joomla !== 'undefined' && Joomla.Text && Joomla.Text._(key)) {
      return Joomla.Text._(key);
    }
    return fallback || key;
  }

  // Cleanup method to be called when chat is destroyed
  destroy() {
    // Stop periodic checking
    this.stopPeriodicCheck();
    
    // Remove event listeners
    if (this.onlineHandler) {
      window.removeEventListener('online', this.onlineHandler);
    }
    if (this.offlineHandler) {
      window.removeEventListener('offline', this.offlineHandler);
    }
    
    // Remove indicator from DOM
    if (this.indicator && this.indicator.parentNode) {
      this.indicator.parentNode.removeChild(this.indicator);
    }
    
    // Clear references
    this.indicator = null;
    this.onlineHandler = null;
    this.offlineHandler = null;
    this.ajaxUrl = null;
    this.container = null;
  }

  isOnline() {
    return this.online;
  }

  isEnabled() {
    return this.enabled;
  }
}

// Create singleton instance
export const connectionStatus = new ConnectionStatus();
