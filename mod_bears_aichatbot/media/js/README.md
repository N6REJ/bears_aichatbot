# Bears AI Chatbot - JavaScript Architecture

## Overview

The Bears AI Chatbot JavaScript has been refactored from a monolithic IIFE (Immediately Invoked Function Expression) into a modern, modular ES6 class-based architecture. This provides better maintainability, testability, and code organization.

## Architecture

### File Structure

```
media/js/
├── modules/                    # ES6 Modules (Modern browsers)
│   ├── ChatBot.js              # Main chatbot orchestrator class
│   ├── SoundManager.js         # Sound notifications management
│   ├── TTSManager.js           # Text-to-speech functionality
│   ├── ConnectionStatus.js     # Network connectivity monitoring
│   ├── DarkModeManager.js      # Dark mode and theme management
│   ├── MessageFormatter.js     # Markdown/HTML message formatting
│   └── UIManager.js            # UI notifications and interactions
├── aichatbot-modular.js        # ES6 module entry point
├── aichatbot-loader.js         # Browser capability detection and loader
├── aichatbot.js                # Legacy version (fallback for older browsers)
└── README.md                    # This file
```

### Loading Strategy

The system uses progressive enhancement to load the appropriate version based on browser capabilities:

1. **aichatbot-loader.js** - Loaded by Joomla, detects browser capabilities
2. **aichatbot-modular.js** - Loaded for modern browsers with ES6 module support
3. **aichatbot.js** - Fallback for older browsers without module support

## Modules

### ChatBot.js
The main orchestrator class that coordinates all other modules.

**Responsibilities:**
- Configuration extraction from DOM attributes
- Module initialization and coordination
- UI creation and event handling
- Message sending and receiving
- Chat state management

**Key Methods:**
- `init()` - Initialize the chatbot instance
- `openChat()` / `closeChat()` - Control chat visibility
- `sendMessage()` - Handle message sending to API
- `appendMessage()` - Add messages to chat UI
- `destroy()` - Cleanup on removal

### SoundManager.js
Manages sound notifications for chat events.

**Features:**
- Configurable default state (on/off)
- User preference persistence via localStorage
- Multiple sound types (sent, received, error)
- Singleton pattern to prevent multiple instances

**Key Methods:**
- `init(defaultSetting)` - Initialize with admin default
- `play(soundType)` - Play a specific sound
- `toggle()` - Toggle sound on/off
- `isEnabled()` - Check current state

### TTSManager.js
Handles text-to-speech functionality for AI responses.

**Features:**
- Browser capability detection
- Automatic text cleaning (removes HTML/Markdown)
- User preference persistence
- Language detection from document
- Speech interruption on new messages

**Key Methods:**
- `init(defaultSetting)` - Initialize with admin default
- `speak(text)` - Convert text to speech
- `stop()` - Stop current speech
- `toggle()` - Toggle TTS on/off
- `cleanTextForSpeech(text)` - Remove formatting from text

### ConnectionStatus.js
Monitors network connectivity and displays status.

**Features:**
- Configurable check interval (0 to disable)
- Multiple fallback detection methods
- Smart checking (pauses when tab hidden)
- Visual status indicator
- Resource cleanup on destroy

**Key Methods:**
- `init(container, ajaxUrl, intervalSeconds)` - Initialize monitoring
- `checkConnection()` - Perform connectivity check
- `updateStatus(isOnline)` - Update UI status
- `destroy()` - Cleanup resources

### DarkModeManager.js
Manages dark mode theme switching.

**Features:**
- User preference priority (highest)
- Admin default setting (medium priority)
- System preference detection (lowest priority)
- Automatic system theme following
- Preference persistence

**Key Methods:**
- `init(instance, adminDarkMode)` - Initialize with settings
- `enable()` / `disable()` - Control dark mode
- `toggle()` - Toggle dark mode
- `applyAutoDetection()` - Follow system preference

### MessageFormatter.js
Handles Markdown and HTML formatting for chat messages.

**Features:**
- Full Markdown support (headings, lists, links, etc.)
- Code block formatting with syntax highlighting
- Safe HTML escaping
- Custom knowledge tag handling
- Inline formatting (bold, italic, strikethrough)

**Key Methods:**
- `formatAnswer(text)` - Main formatting method
- `escapeHtml(str)` - Safely escape HTML
- `headingify()`, `listify()`, etc. - Specific formatters

### UIManager.js
Manages UI notifications and user interactions.

**Features:**
- Toast notifications
- Screen reader announcements
- Conversation copying to clipboard
- Code block copy buttons
- Fallback clipboard support

**Key Methods:**
- `showNotification(message, type)` - Display toast notification
- `announceToScreenReader(message, priority)` - ARIA announcements
- `copyConversation(messages)` - Copy chat to clipboard
- `addCopyButtonsToCodeBlocks(container)` - Add copy buttons

## Browser Support

### Modern Version (ES6 Modules)
Requires:
- ES6 module support
- Promises
- Fetch API
- WeakMap
- Template literals
- Arrow functions
- Classes

Supported browsers:
- Chrome 61+
- Firefox 60+
- Safari 11+
- Edge 79+

### Legacy Version (Fallback)
Supports older browsers with:
- ES5 compatibility
- Polyfills for missing features
- IIFE pattern
- No module dependencies

## Usage

### Initialization
The system automatically initializes when the DOM is ready:

```javascript
// Automatic initialization via aichatbot-loader.js
// No manual initialization required
```

### Accessing Instances (Advanced)
For advanced use cases, chatbot instances can be accessed:

```javascript
// In modern browsers with module support
import { chatbotInstances } from './aichatbot-modular.js';

// Get instance for a specific DOM element
const element = document.querySelector('.bears-aichatbot');
const chatbot = chatbotInstances.get(element);
```

## Configuration

Configuration is passed via data attributes on the container element:

```html
<div class="bears-aichatbot"
     data-module-id="123"
     data-ajax-url="/api/endpoint"
     data-position="bottom-right"
     data-dark-mode="1"
     data-sound-notifications="1"
     data-text-to-speech="0"
     data-connection-check-interval="60">
</div>
```

## Development

### Adding New Features
1. Create a new module in `modules/` directory
2. Export as singleton or class as appropriate
3. Import in `ChatBot.js`
4. Initialize in `ChatBot.init()`

### Testing
The modular architecture makes unit testing easier:

```javascript
// Example test for SoundManager
import { SoundManager } from './modules/SoundManager.js';

describe('SoundManager', () => {
  it('should initialize with default setting', () => {
    const manager = new SoundManager();
    manager.init(true);
    expect(manager.isEnabled()).toBe(true);
  });
});
```

## Migration from Legacy

### Key Changes
1. **Modular Structure**: Code split into focused modules
2. **Class-Based**: OOP approach with ES6 classes
3. **Singleton Managers**: Shared state via singleton instances
4. **Import/Export**: ES6 module system
5. **Better Encapsulation**: Private methods and properties

### Backwards Compatibility
The legacy version (`aichatbot.js`) is maintained for older browsers and loaded automatically when ES6 modules are not supported.

## Performance Considerations

### Lazy Loading
Modules are loaded on demand, reducing initial bundle size.

### Resource Cleanup
All modules implement proper cleanup methods to prevent memory leaks:
- Event listener removal
- Timer cancellation
- DOM element cleanup
- State reset

### Optimization Tips
1. Disable connection checking if not needed (set interval to 0)
2. Use appropriate chunk sizes for content
3. Limit token counts for faster responses
4. Enable caching where appropriate

## Troubleshooting

### Module Loading Issues
Check browser console for:
- CORS errors (ensure same-origin or proper headers)
- 404 errors (verify file paths)
- Syntax errors (check browser compatibility)

### Feature Detection
The loader automatically detects and falls back:
```javascript
// Check which version loaded
if (window.ChatBot) {
  console.log('Modular version loaded');
} else {
  console.log('Legacy version loaded');
}
```

## Future Enhancements

Potential improvements:
1. **TypeScript**: Add type definitions
2. **Web Components**: Convert to custom elements
3. **Service Worker**: Offline support
4. **WebSocket**: Real-time communication
5. **Bundling**: Webpack/Rollup for optimization
6. **Testing**: Comprehensive test suite
7. **Documentation**: JSDoc comments

## License

GNU General Public License v3.0 or later
