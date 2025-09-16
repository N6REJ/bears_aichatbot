/**
 * Bears AI Chatbot - Modular Entry Point
 * Modern ES6 module-based architecture
 */
import { ChatBot } from './modules/ChatBot.js';

// Track all chatbot instances
const chatbotInstances = new WeakMap();

// Initialize chatbots when DOM is ready
function initializeChatbots() {
  const nodes = document.querySelectorAll('.bears-aichatbot');
  
  try {
    console.debug('[Bears AI Chatbot] Found instances:', nodes.length);
  } catch (e) {}
  
  nodes.forEach(node => {
    // Create and initialize chatbot instance
    const chatbot = new ChatBot(node);
    chatbot.init();
    
    // Track instance for cleanup
    chatbotInstances.set(node, chatbot);
  });
}

// Cleanup function
function cleanupChatbots() {
  document.querySelectorAll('.bears-aichatbot').forEach(node => {
    const chatbot = chatbotInstances.get(node);
    if (chatbot) {
      chatbot.destroy();
      chatbotInstances.delete(node);
    }
  });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeChatbots);
} else {
  // DOM is already loaded
  initializeChatbots();
}

// Cleanup on page unload
window.addEventListener('beforeunload', cleanupChatbots);

// Also cleanup when visibility changes (tab switching)
document.addEventListener('visibilitychange', function() {
  if (document.hidden) {
    // Page is hidden, pause connection checks
    document.querySelectorAll('.bears-aichatbot').forEach(node => {
      const chatbot = chatbotInstances.get(node);
      if (chatbot && chatbot.connectionStatus.isEnabled()) {
        chatbot.connectionStatus.stopPeriodicCheck();
      }
    });
  } else {
    // Page is visible again, resume if online
    document.querySelectorAll('.bears-aichatbot').forEach(node => {
      const chatbot = chatbotInstances.get(node);
      if (chatbot && chatbot.connectionStatus.isEnabled() && navigator.onLine) {
        chatbot.connectionStatus.startPeriodicCheck();
      }
    });
  }
});

// Export for potential external use
export { ChatBot, chatbotInstances };
