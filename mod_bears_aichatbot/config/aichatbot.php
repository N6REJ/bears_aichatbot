<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.09.19
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

/**
 * AI Chatbot Configuration Class
 */
class BearsAIChatbotConfig
{
    /**
     * API Endpoints
     * Complete list of all IONOS AI Model Hub endpoints
     */
    const ENDPOINTS = [
        'ionos' => [
            // Chat/Completion endpoints
            'chat' => 'https://openai.inference.de-txl.ionos.com/v1/chat/completions',
            'completions' => 'https://openai.inference.de-txl.ionos.com/v1/completions',
            
            // Model information
            'models' => 'https://openai.inference.de-txl.ionos.com/v1/models',
            
            // Document Collections endpoints
            'collections_base' => 'https://inference.de-txl.ionos.com',
            'collections_list' => 'https://inference.de-txl.ionos.com/collections',
            'collections_create' => 'https://inference.de-txl.ionos.com/collections',
            'collections_query' => 'https://inference.de-txl.ionos.com/collections/{collection_id}/query',
            'collections_documents' => 'https://inference.de-txl.ionos.com/collections/{collection_id}/documents',
            'collections_delete' => 'https://inference.de-txl.ionos.com/collections/{collection_id}',
            
            // Embeddings endpoint
            'embeddings' => 'https://openai.inference.de-txl.ionos.com/v1/embeddings',
            
            // Image generation endpoints (if available)
            'images_generations' => 'https://openai.inference.de-txl.ionos.com/v1/images/generations',
            'images_edits' => 'https://openai.inference.de-txl.ionos.com/v1/images/edits',
            'images_variations' => 'https://openai.inference.de-txl.ionos.com/v1/images/variations'
        ],
        'openai' => [
            'chat' => 'https://api.openai.com/v1/chat/completions',
            'completions' => 'https://api.openai.com/v1/completions',
            'models' => 'https://api.openai.com/v1/models',
            'embeddings' => 'https://api.openai.com/v1/embeddings',
            'images_generations' => 'https://api.openai.com/v1/images/generations'
        ]
    ];

    /**
     * IONOS AI Model Hub Pricing
     * Based on official pricing: https://cloud.ionos.com/managed/ai-model-hub
     * Prices in USD per 1M tokens (converted to per 1K for calculations)
     */
    const TOKEN_PRICING = [
        'ionos' => [
            // Standard Package - $10/month includes:
            // - 25M input tokens
            // - 25M output tokens
            // Overage: $0.40 per 1M input, $0.60 per 1M output
            'standard' => [
                'monthly_fee' => 10.00,
                'included_input_tokens' => 25000000,  // 25M tokens
                'included_output_tokens' => 25000000, // 25M tokens
                'overage_prompt' => 0.0004,      // $0.40 per 1M = $0.0004 per 1K
                'overage_completion' => 0.0006,   // $0.60 per 1M = $0.0006 per 1K
                'currency' => 'USD',
                'description' => 'Standard Package - $10/month with 25M input/output tokens included'
            ],
            
            // Premium Package - $50/month includes:
            // - 125M input tokens
            // - 125M output tokens
            // Overage: $0.40 per 1M input, $0.60 per 1M output
            'premium' => [
                'monthly_fee' => 50.00,
                'included_input_tokens' => 125000000,  // 125M tokens
                'included_output_tokens' => 125000000, // 125M tokens
                'overage_prompt' => 0.0004,      // $0.40 per 1M = $0.0004 per 1K
                'overage_completion' => 0.0006,   // $0.60 per 1M = $0.0006 per 1K
                'currency' => 'USD',
                'description' => 'Premium Package - $50/month with 125M input/output tokens included'
            ],
            
            // Ultimate Package - $250/month includes:
            // - 625M input tokens
            // - 625M output tokens
            // Overage: $0.40 per 1M input, $0.60 per 1M output
            'ultimate' => [
                'monthly_fee' => 250.00,
                'included_input_tokens' => 625000000,  // 625M tokens
                'included_output_tokens' => 625000000, // 625M tokens
                'overage_prompt' => 0.0004,      // $0.40 per 1M = $0.0004 per 1K
                'overage_completion' => 0.0006,   // $0.60 per 1M = $0.0006 per 1K
                'currency' => 'USD',
                'description' => 'Ultimate Package - $250/month with 625M input/output tokens included'
            ],
            
            // Pay as you go (no monthly fee)
            'payg' => [
                'monthly_fee' => 0.00,
                'included_input_tokens' => 0,
                'included_output_tokens' => 0,
                'prompt' => 0.0008,      // $0.80 per 1M = $0.0008 per 1K
                'completion' => 0.0012,   // $1.20 per 1M = $0.0012 per 1K
                'currency' => 'USD',
                'description' => 'Pay As You Go - No monthly fee, $0.80/1M input, $1.20/1M output'
            ]
        ],
        'openai' => [
            'gpt-3.5-turbo' => [
                'prompt' => 0.0005,
                'completion' => 0.0015,
                'currency' => 'USD'
            ],
            'gpt-4' => [
                'prompt' => 0.03,
                'completion' => 0.06,
                'currency' => 'USD'
            ],
            'gpt-4-turbo' => [
                'prompt' => 0.01,
                'completion' => 0.03,
                'currency' => 'USD'
            ]
        ],
        'custom' => [
            'default' => [
                'prompt' => 0.001,
                'completion' => 0.002,
                'currency' => 'USD'
            ]
        ]
    ];

    /**
     * Fallback Models
     * Used when API is unavailable or for offline reference
     * This list should be updated periodically based on actual API responses
     */
    const FALLBACK_MODELS = [
        'ionos' => [
            // These are from the ionosmodels.php fallback list
            'BAAI/bge-large-en-v1.5' => 'BGE Large English v1.5 (Embedding)',
            'BAAI/bge-m3' => 'BGE M3 (Multilingual Embedding)',
            'sentence-transformers/paraphrase-multilingual-mpnet-base-v2' => 'Paraphrase Multilingual MPNet',
            'openai/gpt-oss-120b' => 'GPT OSS 120B',
            'meta-llama/Meta-Llama-3.1-405B-Instruct-FP8' => 'Llama 3.1 405B Instruct FP8',
            'meta-llama/Meta-Llama-3.1-8B-Instruct' => 'Llama 3.1 8B Instruct',
            'meta-llama/Llama-3.3-70B-Instruct' => 'Llama 3.3 70B Instruct',
            'meta-llama/CodeLlama-13b-Instruct-hf' => 'CodeLlama 13B Instruct',
            'mistralai/Mistral-Small-24B-Instruct' => 'Mistral Small 24B Instruct',
            'mistralai/Mistral-Nemo-Instruct-2407' => 'Mistral Nemo Instruct (July 2024)',
            'mistralai/Mixtral-8x7B-Instruct-v0.1' => 'Mixtral 8x7B Instruct v0.1',
            'openGPT-X/Teuken-7B-instruct-commercial' => 'Teuken 7B Instruct Commercial',
            'black-forest-labs/FLUX.1-schnell' => 'FLUX.1 Schnell (Image)',
            'stabilityai/stable-diffusion-xl-base-1.0' => 'Stable Diffusion XL Base 1.0 (Image)'
        ],
        'openai' => [
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K',
            'gpt-4' => 'GPT-4',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4o' => 'GPT-4 Optimized',
            'gpt-4o-mini' => 'GPT-4 Optimized Mini'
        ]
    ];

    /**
     * Model categories for better organization
     */
    const MODEL_CATEGORIES = [
        'chat' => [
            'meta-llama/Meta-Llama-3.1-405B-Instruct-FP8',
            'meta-llama/Meta-Llama-3.1-8B-Instruct',
            'meta-llama/Llama-3.3-70B-Instruct',
            'mistralai/Mistral-Small-24B-Instruct',
            'mistralai/Mistral-Nemo-Instruct-2407',
            'mistralai/Mixtral-8x7B-Instruct-v0.1',
            'openai/gpt-oss-120b',
            'openGPT-X/Teuken-7B-instruct-commercial'
        ],
        'code' => [
            'meta-llama/CodeLlama-13b-Instruct-hf'
        ],
        'embedding' => [
            'BAAI/bge-large-en-v1.5',
            'BAAI/bge-m3',
            'sentence-transformers/paraphrase-multilingual-mpnet-base-v2'
        ],
        'image' => [
            'black-forest-labs/FLUX.1-schnell',
            'stabilityai/stable-diffusion-xl-base-1.0'
        ]
    ];

    /**
     * Cache for dynamically fetched models
     */
    protected static $modelsCache = [];
    protected static $modelsCacheTime = 0;
    protected static $modelsCacheTTL = 3600; // Cache for 1 hour

    /**
     * Document Collection Settings
     * IONOS Document Collections pricing and settings
     */
    const COLLECTION_SETTINGS = [
        'pricing' => [
            // Document Collections: $5/month per collection
            'monthly_fee_per_collection' => 5.00,
            'included_documents' => 10000,  // 10K documents included
            'included_storage_gb' => 1,     // 1GB storage included
            'overage_per_1k_documents' => 0.50,  // $0.50 per 1K additional documents
            'overage_per_gb_storage' => 5.00,    // $5.00 per additional GB
            'currency' => 'USD'
        ],
        'chunking' => [
            'enabled' => true,
            'chunk_size' => 512,
            'chunk_overlap' => 50,
            'max_chunk_size' => 2048,
            'min_chunk_size' => 100
        ],
        'embedding' => [
            'model' => 'BAAI/bge-large-en-v1.5',
            'dimensions' => 1024,
            'max_batch_size' => 100
        ],
        'engine' => [
            'db_type' => 'pgvector',
            'index_type' => 'hnsw',
            'distance_metric' => 'cosine'
        ],
        'retrieval' => [
            'default_top_k' => 6,
            'default_min_score' => 0.2,
            'max_top_k' => 20,
            'search_type' => 'similarity'  // similarity, mmr, or threshold
        ],
        'limits' => [
            'max_collections_per_account' => 100,
            'max_documents_per_collection' => 1000000,  // 1M documents
            'max_document_size_mb' => 10,
            'max_query_length' => 1000,
            'rate_limit_per_minute' => 60
        ]
    ];

    /**
     * System Limits
     */
    const LIMITS = [
        'max_context_length' => 30000,      // Maximum characters for context
        'max_article_fetch' => 500,         // Maximum articles to fetch
        'max_kunena_fetch' => 100,          // Maximum Kunena posts to fetch
        'max_response_tokens' => 2048,      // Maximum tokens in response (increased from 512)
        'max_sitemap_urls' => 150,          // Maximum URLs in sitemap
        'max_keywords_track' => 10,         // Maximum keywords to track per message
        'request_timeout' => 30             // API request timeout in seconds
    ];

    /**
     * Keyword Extraction Settings
     */
    const KEYWORD_SETTINGS = [
        'min_length' => 3,
        'max_length' => 50,
        'skip_numeric' => true,
        'common_skip_words' => [
            // Articles and pronouns
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
            'will', 'would', 'could', 'should', 'may', 'might', 'can', 'must', 'shall',
            'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them',
            'my', 'your', 'his', 'her', 'its', 'our', 'their', 'this', 'that', 'these', 'those',
            // Question words
            'what', 'where', 'when', 'why', 'how', 'who', 'which', 'whose', 'whom',
            // Common verbs
            'tell', 'know', 'need', 'want', 'get', 'use', 'work', 'make', 'find', 'help'
        ]
    ];

    /**
     * Get endpoint URL by provider and type
     * 
     * @param string $provider Provider name (ionos, openai, etc.)
     * @param string $type Endpoint type (chat, models, collections)
     * @return string|null
     */
    public static function getEndpoint($provider = 'ionos', $type = 'chat')
    {
        return self::ENDPOINTS[$provider][$type] ?? null;
    }

    /**
     * Get token pricing for a specific provider and plan
     * 
     * @param string $provider Provider name
     * @param string $plan Plan name (standard, premium, ultimate, payg)
     * @param bool $overageOnly Return only overage rates (for packages with included tokens)
     * @return array|null
     */
    public static function getTokenPricing($provider = 'ionos', $plan = 'standard', $overageOnly = true)
    {
        $pricing = self::TOKEN_PRICING[$provider][$plan] ?? null;
        
        if (!$pricing) {
            return self::TOKEN_PRICING['custom']['default'] ?? null;
        }
        
        // For IONOS packages, return overage rates by default for cost calculations
        if ($provider === 'ionos' && $overageOnly && isset($pricing['overage_prompt'])) {
            return [
                'prompt' => $pricing['overage_prompt'],
                'completion' => $pricing['overage_completion'],
                'currency' => $pricing['currency'],
                'monthly_fee' => $pricing['monthly_fee'],
                'included_input_tokens' => $pricing['included_input_tokens'],
                'included_output_tokens' => $pricing['included_output_tokens']
            ];
        }
        
        // For pay-as-you-go, return the direct rates
        if ($provider === 'ionos' && $plan === 'payg') {
            return [
                'prompt' => $pricing['prompt'],
                'completion' => $pricing['completion'],
                'currency' => $pricing['currency'],
                'monthly_fee' => 0,
                'included_input_tokens' => 0,
                'included_output_tokens' => 0
            ];
        }
        
        return $pricing;
    }

    /**
     * Get collection pricing information
     * 
     * @return array
     */
    public static function getCollectionPricing()
    {
        return self::COLLECTION_SETTINGS['pricing'] ?? [];
    }

    /**
     * Calculate estimated monthly cost based on usage
     * 
     * @param int $inputTokens Total input tokens used
     * @param int $outputTokens Total output tokens used
     * @param string $plan Pricing plan (standard, premium, ultimate, payg)
     * @param int $collections Number of document collections
     * @return array Cost breakdown
     */
    public static function calculateMonthlyCost($inputTokens, $outputTokens, $plan = 'standard', $collections = 0)
    {
        $pricing = self::getTokenPricing('ionos', $plan, false);
        $collectionPricing = self::getCollectionPricing();
        
        $cost = [
            'base_fee' => $pricing['monthly_fee'] ?? 0,
            'collection_fee' => $collections * ($collectionPricing['monthly_fee_per_collection'] ?? 0),
            'overage_input' => 0,
            'overage_output' => 0,
            'total' => 0
        ];
        
        // Calculate overage for packages with included tokens
        if (isset($pricing['included_input_tokens']) && $pricing['included_input_tokens'] > 0) {
            $overageInput = max(0, $inputTokens - $pricing['included_input_tokens']);
            $overageOutput = max(0, $outputTokens - $pricing['included_output_tokens']);
            
            $cost['overage_input'] = ($overageInput / 1000) * ($pricing['overage_prompt'] ?? 0);
            $cost['overage_output'] = ($overageOutput / 1000) * ($pricing['overage_completion'] ?? 0);
        } else {
            // Pay as you go - all tokens are charged
            $cost['overage_input'] = ($inputTokens / 1000) * ($pricing['prompt'] ?? 0);
            $cost['overage_output'] = ($outputTokens / 1000) * ($pricing['completion'] ?? 0);
        }
        
        $cost['total'] = $cost['base_fee'] + $cost['collection_fee'] + 
                        $cost['overage_input'] + $cost['overage_output'];
        
        return $cost;
    }

    /**
     * Get available models for a provider
     * Attempts to fetch from API first, falls back to static list
     * 
     * @param string $provider Provider name
     * @param string|null $token API token for authentication
     * @param bool $forceRefresh Force refresh from API
     * @return array
     */
    public static function getAvailableModels($provider = 'ionos', $token = null, $forceRefresh = false)
    {
        // Check cache first
        if (!$forceRefresh && 
            isset(self::$modelsCache[$provider]) && 
            (time() - self::$modelsCacheTime) < self::$modelsCacheTTL) {
            return self::$modelsCache[$provider];
        }

        // Try to fetch from API if token provided
        if ($provider === 'ionos' && $token !== null) {
            $models = self::fetchModelsFromAPI($token);
            if (!empty($models)) {
                self::$modelsCache[$provider] = $models;
                self::$modelsCacheTime = time();
                return $models;
            }
        }

        // Fall back to static list
        return self::FALLBACK_MODELS[$provider] ?? [];
    }

    /**
     * Fetch models from IONOS API
     * 
     * @param string $token Bearer token for authentication
     * @return array
     */
    protected static function fetchModelsFromAPI($token)
    {
        try {
            // Check if we're in a Joomla environment
            if (!class_exists('Joomla\CMS\Http\HttpFactory')) {
                return [];
            }

            $http = \Joomla\CMS\Http\HttpFactory::getHttp();
            $modelsUrl = self::getEndpoint('ionos', 'models');
            
            $headers = [
                'Authorization' => 'Bearer ' . trim($token),
                'Accept' => 'application/json',
            ];
            
            $response = $http->get($modelsUrl, $headers, 10); // 10 second timeout
            
            if ($response->code >= 200 && $response->code < 300) {
                $data = json_decode($response->body, true);
                if (isset($data['data']) && is_array($data['data'])) {
                    $models = [];
                    foreach ($data['data'] as $model) {
                        if (!isset($model['id'])) continue;
                        $id = (string) $model['id'];
                        $name = isset($model['name']) ? (string) $model['name'] : $id;
                        
                        // Try to make the name more readable
                        $displayName = self::formatModelName($id, $name);
                        $models[$id] = $displayName;
                    }
                    return $models;
                }
            }
        } catch (\Throwable $e) {
            // Log error if in Joomla environment
            if (class_exists('Joomla\CMS\Log\Log')) {
                \Joomla\CMS\Log\Log::add(
                    'Failed to fetch models from API: ' . $e->getMessage(),
                    \Joomla\CMS\Log\Log::WARNING,
                    'bears_aichatbot'
                );
            }
        }
        
        return [];
    }

    /**
     * Format model name for display
     * 
     * @param string $id Model ID
     * @param string $name Model name (if available)
     * @return string
     */
    protected static function formatModelName($id, $name = '')
    {
        if ($name !== '' && $name !== $id) {
            return $name . ' (' . $id . ')';
        }
        
        // Try to make the ID more readable
        $parts = explode('/', $id);
        if (count($parts) === 2) {
            $provider = $parts[0];
            $model = $parts[1];
            
            // Format provider name
            $providerMap = [
                'meta-llama' => 'Meta Llama',
                'mistralai' => 'Mistral AI',
                'openai' => 'OpenAI',
                'BAAI' => 'BAAI',
                'stabilityai' => 'Stability AI',
                'black-forest-labs' => 'Black Forest Labs',
                'openGPT-X' => 'OpenGPT-X',
                'sentence-transformers' => 'Sentence Transformers'
            ];
            
            $providerName = $providerMap[$provider] ?? ucfirst($provider);
            
            // Clean up model name
            $modelName = str_replace(['-', '_'], ' ', $model);
            $modelName = preg_replace('/\b(\w)/e', 'strtoupper("$1")', $modelName);
            
            return $providerName . ' - ' . $modelName;
        }
        
        return $id;
    }

    /**
     * Get models by category
     * 
     * @param string $category Category name (chat, code, embedding, image)
     * @param string|null $token API token for authentication
     * @return array
     */
    public static function getModelsByCategory($category, $token = null)
    {
        $allModels = self::getAvailableModels('ionos', $token);
        $categoryModels = self::MODEL_CATEGORIES[$category] ?? [];
        
        $result = [];
        foreach ($categoryModels as $modelId) {
            if (isset($allModels[$modelId])) {
                $result[$modelId] = $allModels[$modelId];
            }
        }
        
        return $result;
    }

    /**
     * Check if a model supports a specific capability
     * 
     * @param string $modelId Model ID
     * @param string $capability Capability (chat, embedding, image, code)
     * @return bool
     */
    public static function modelSupports($modelId, $capability)
    {
        foreach (self::MODEL_CATEGORIES as $cat => $models) {
            if (in_array($modelId, $models)) {
                return $cat === $capability;
            }
        }
        
        // Default assumptions based on model ID patterns
        if (strpos($modelId, 'embed') !== false || strpos($modelId, 'bge') !== false) {
            return $capability === 'embedding';
        }
        if (strpos($modelId, 'stable-diffusion') !== false || strpos($modelId, 'FLUX') !== false) {
            return $capability === 'image';
        }
        if (strpos($modelId, 'CodeLlama') !== false || strpos($modelId, 'Coder') !== false) {
            return $capability === 'code';
        }
        
        // Default to chat capability
        return $capability === 'chat';
    }

    /**
     * Get a specific configuration value
     * 
     * @param string $path Dot-separated path (e.g., 'LIMITS.max_context_length')
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get($path, $default = null)
    {
        $parts = explode('.', $path);
        $value = null;
        
        // Map first part to constant
        switch ($parts[0]) {
            case 'ENDPOINTS':
                $value = self::ENDPOINTS;
                break;
            case 'TOKEN_PRICING':
                $value = self::TOKEN_PRICING;
                break;
            case 'AVAILABLE_MODELS':
                $value = self::AVAILABLE_MODELS;
                break;
            case 'COLLECTION_SETTINGS':
                $value = self::COLLECTION_SETTINGS;
                break;
            case 'LIMITS':
                $value = self::LIMITS;
                break;
            case 'KEYWORD_SETTINGS':
                $value = self::KEYWORD_SETTINGS;
                break;
            default:
                return $default;
        }
        
        // Navigate through the array
        array_shift($parts);
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }
        
        return $value;
    }

    /**
     * Check if the configuration file needs updating
     * This could check against a remote version or timestamp
     * 
     * @return bool
     */
    public static function needsUpdate()
    {
        // For now, return false
        // In the future, this could check against a remote configuration version
        return false;
    }
}
