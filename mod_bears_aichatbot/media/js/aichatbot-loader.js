/**
 * Bears AI Chatbot - Module Loader
 * Detects browser capabilities and loads appropriate version
 */
(function() {
  'use strict';
  
  // Check if browser supports ES6 modules
  function supportsES6Modules() {
    try {
      // Check for dynamic import support
      new Function('import("")');
      return true;
    } catch (e) {
      return false;
    }
  }
  
  // Check if browser supports required ES6 features
  function supportsRequiredFeatures() {
    // Check for essential features
    return (
      typeof Promise !== 'undefined' &&
      typeof fetch !== 'undefined' &&
      typeof WeakMap !== 'undefined' &&
      typeof Symbol !== 'undefined' &&
      Array.prototype.includes &&
      Object.assign
    );
  }
  
  // Load the appropriate version
  function loadChatbot() {
    const baseUrl = document.currentScript ? 
      document.currentScript.src.replace(/[^\/]*$/, '') : 
      '/media/mod_bears_aichatbot/js/';
    
    if (supportsES6Modules() && supportsRequiredFeatures()) {
      // Load modular version for modern browsers
      const script = document.createElement('script');
      script.type = 'module';
      script.src = baseUrl + 'aichatbot-modular.js';
      document.head.appendChild(script);
      
      console.log('[Bears AI Chatbot] Loading modular version');
    } else {
      // Load legacy version for older browsers
      const script = document.createElement('script');
      script.src = baseUrl + 'aichatbot.js';
      document.head.appendChild(script);
      
      console.log('[Bears AI Chatbot] Loading legacy version');
    }
  }
  
  // Initialize
  loadChatbot();
})();
