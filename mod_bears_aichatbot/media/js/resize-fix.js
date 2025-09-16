/**
 * Resize Handle Position Fix
 * Ensures resize handle moves to left side for right-positioned modules
 */
(function() {
    'use strict';
    
    // Function to check and fix resize handle position
    function fixResizeHandlePosition() {
        const chatbots = document.querySelectorAll('.bears-aichatbot');
        
        chatbots.forEach(chatbot => {
            const position = chatbot.getAttribute('data-position');
            const window = chatbot.querySelector('.bears-aichatbot-window');
            
            if (!window) return;
            
            // Check if position is on the right side
            if (position === 'bottom-right' || position === 'middle-right') {
                // Add a class to force left-side resize handle
                window.classList.add('resize-left');
            } else {
                // Remove the class for left-side positions
                window.classList.remove('resize-left');
            }
        });
    }
    
    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fixResizeHandlePosition);
    } else {
        fixResizeHandlePosition();
    }
    
    // Also run when chatbot opens (in case it's dynamically created)
    document.addEventListener('click', function(e) {
        if (e.target.closest('.bears-aichatbot-toggle')) {
            setTimeout(fixResizeHandlePosition, 100);
        }
    });
    
    // Watch for attribute changes
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'data-position') {
                fixResizeHandlePosition();
            }
        });
    });
    
    // Start observing
    document.querySelectorAll('.bears-aichatbot').forEach(chatbot => {
        observer.observe(chatbot, { attributes: true });
    });
})();
