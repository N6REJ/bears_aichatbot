# Configuration File Usage

The `aichatbot.php` configuration file centralizes settings that may change over time, making the extension more maintainable.

## How to Use

### 1. Include the Configuration File

```php
// At the top of helper.php or any file that needs config
require_once __DIR__ . '/config/aichatbot.php';
```

### 2. Access Configuration Values

```php
// Get specific endpoint
$chatEndpoint = BearsAIChatbotConfig::getEndpoint('ionos', 'chat');

// Get token pricing
$pricing = BearsAIChatbotConfig::getTokenPricing('ionos', 'standard');
$promptPrice = $pricing['prompt'];
$completionPrice = $pricing['completion'];

// Get available models
$models = BearsAIChatbotConfig::getAvailableModels('ionos');

// Get limits
$maxContext = BearsAIChatbotConfig::get('LIMITS.max_context_length');
$maxArticles = BearsAIChatbotConfig::get('LIMITS.max_article_fetch');

// Get collection settings
$chunkSize = BearsAIChatbotConfig::get('COLLECTION_SETTINGS.chunking.chunk_size');
```

## Configuration Sections

### ENDPOINTS
- API endpoints for different providers (IONOS, OpenAI, etc.)
- Easily add new providers without modifying code

### TOKEN_PRICING
- Token costs per 1K tokens for different providers and plans
- Update pricing without code changes

### AVAILABLE_MODELS
- List of available AI models per provider
- Add new models as they become available

### COLLECTION_SETTINGS
- Document collection configuration
- Chunking, embedding, and retrieval settings

### LIMITS
- System limits and constraints
- Max context length, fetch limits, timeouts

### KEYWORD_SETTINGS
- Keyword extraction configuration
- Common words to skip, length constraints

## Updating the Configuration

When prices change or new models become available:

1. Edit `config/aichatbot.php`
2. Update the relevant constant arrays
3. No need to modify the core helper code

## Future Enhancements

The configuration could be extended to:
- Load settings from a remote JSON file
- Cache configuration for performance
- Allow admin overrides via database
- Support multiple configuration profiles
