<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.09.15
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;

// Define Joomla constants for IDE support (these are normally defined by Joomla core)
if (!defined('JPATH_ADMINISTRATOR')) {
    define('JPATH_ADMINISTRATOR', JPATH_ROOT . '/administrator');
}
if (!defined('JPATH_COMPONENT_ADMINISTRATOR')) {
    define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_bears_aichatbot');
}

/**
 * Check collection status in database and IONOS API
 */
function checkCollectionStatus(string $token, string $tokenId, string $endpoint): string
{
    $info = [];
    
    try {
        $db = Factory::getContainer()->get('DatabaseDriver');
        
        // Check if state table exists
        $tables = $db->getTableList();
        $prefix = $db->getPrefix();
        $stateTable = $prefix . 'aichatbot_state';
        
        if (in_array($stateTable, $tables)) {
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__aichatbot_state'))
                ->where($db->quoteName('id') . ' = 1');
            $db->setQuery($query);
            $state = $db->loadObject();
            
            if ($state) {
                $info[] = '✅ State table exists';
                $info[] = 'Collection ID in DB: ' . ($state->collection_id ?: 'NULL');
                $info[] = 'Last queue run: ' . ($state->last_run_queue ?: 'Never');
                $info[] = 'Last reconcile: ' . ($state->last_run_reconcile ?: 'Never');
            } else {
                $info[] = '⚠️ State table exists but no data';
            }
        } else {
            $info[] = '❌ State table does not exist';
        }
        
        // Check usage table
        $usageTable = $prefix . 'aichatbot_usage';
        if (in_array($usageTable, $tables)) {
            $countQuery = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__aichatbot_usage'));
            $db->setQuery($countQuery);
            $count = (int)$db->loadResult();
            $info[] = "✅ Usage table exists ({$count} records)";
        } else {
            $info[] = '❌ Usage table does not exist';
        }
        
        // Check docs table
        $docsTable = $prefix . 'aichatbot_docs';
        if (in_array($docsTable, $tables)) {
            $countQuery = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__aichatbot_docs'));
            $db->setQuery($countQuery);
            $count = (int)$db->loadResult();
            $info[] = "✅ Docs table exists ({$count} records)";
        } else {
            $info[] = '❌ Docs table does not exist';
        }
        
    } catch (\Throwable $e) {
        $info[] = '❌ DB Error: ' . $e->getMessage();
    }
    
    // Query IONOS API for collections
    if ($token && $tokenId) {
        try {
            // Test the correct IONOS Inference API endpoint for document collections
            // Based on collections.ipynb: https://inference.de-txl.ionos.com/collections
            // CONFIRMED WORKING: HTTP 200 response verified
            $endpoints = [
                'https://inference.de-txl.ionos.com/collections'
            ];
            
            // Enhanced debugging info
            $info[] = "🔍 Debug Info:";
            $info[] = "Token length: " . strlen($token) . " chars";
            $info[] = "Token ID: " . substr($tokenId, 0, 8) . "...";
            $info[] = "Token starts with: " . substr($token, 0, 10) . "...";
            
            $http = HttpFactory::getHttp();
            $headers = [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ];
            if ($tokenId) {
                $headers['X-IONOS-Token-Id'] = $tokenId;
            }
            
            // First test basic connectivity to verify token works
            $info[] = "🔍 Testing basic API connectivity...";
            $basicTests = [
                'https://openai.inference.de-txl.ionos.com/v1/models',
                'https://inference.de-txl.ionos.com/v1/models',
                'https://api.ionos.com/cloudapi/v6',
                'https://api.ionos.com/cloudapi/v6/ai/modelhub'
            ];
            
            $tokenWorks = false;
            foreach ($basicTests as $testUrl) {
                try {
                    $testResponse = $http->get($testUrl, $headers, 5);
                    if ($testResponse->code < 400) {
                        $tokenWorks = true;
                        $info[] = "✅ Token works with: {$testUrl} (HTTP {$testResponse->code})";
                        break;
                    } elseif ($testResponse->code === 401) {
                        $info[] = "❌ Token authentication failed: {$testUrl} (HTTP 401)";
                    }
                } catch (\Throwable $e) {
                    // Continue testing
                }
            }
            
            if (!$tokenWorks) {
                $info[] = "❌ Token doesn't work with any basic endpoints - check token validity";
                return implode('<br>', $info);
            }
            
            $info[] = "Headers: Authorization=Bearer [HIDDEN], X-IONOS-Token-Id=" . substr($tokenId, 0, 8) . "...";
            $info[] = "Note: Testing multiple IONOS API endpoints";
            
            $response = null;
            $workingEndpoint = null;
            
            foreach ($endpoints as $testEndpoint) {
                $info[] = "Testing: {$testEndpoint}";
                try {
                    $response = $http->get($testEndpoint, $headers, 10);
                    if ($response->code < 400) {
                        $workingEndpoint = $testEndpoint;
                        $info[] = "✅ Working endpoint found: {$testEndpoint}";
                        break;
                    } else {
                        $info[] = "❌ HTTP {$response->code}: " . substr($response->body, 0, 100);
                    }
                } catch (\Throwable $e) {
                    $info[] = "❌ Error: " . $e->getMessage();
                }
            }
            
            if (!$workingEndpoint) {
                $info[] = "❌ No working endpoint found";
                $info[] = "🔧 Troubleshooting suggestions:";
                $info[] = "- Verify your IONOS token has AI Model Hub permissions";
                $info[] = "- Check if your IONOS account has access to document collections";
                $info[] = "- Try regenerating your API token in the IONOS console";
                $info[] = "- Ensure you're using the correct data center region";
                $info[] = "- Contact IONOS support if the issue persists";
                return implode('<br>', $info);
            }
            
            // Try to create a test collection to verify write permissions
            $info[] = "🧪 Testing collection creation...";
            try {
                $testPayload = [
                    'properties' => [
                        'name' => 'bears-test-' . time(),
                        'description' => 'Test collection for Bears AI Chatbot - can be deleted',
                        'chunking' => [
                            'enabled' => true,
                            'strategy' => [
                                'config' => [
                                    'chunk_overlap' => 50,
                                    'chunk_size' => 512
                                ]
                            ]
                        ],
                        'embedding' => [
                            'model' => 'BAAI/bge-large-en-v1.5'
                        ],
                        'engine' => [
                            'db_type' => 'pgvector'
                        ]
                    ]
                ];
                
                $createHeaders = $headers;
                $createHeaders['Content-Type'] = 'application/json';
                
                $createResponse = $http->post($workingEndpoint, json_encode($testPayload), $createHeaders, 10);
                
                if ($createResponse->code >= 200 && $createResponse->code < 300) {
                    $createData = json_decode($createResponse->body, true);
                    $testCollectionId = $createData['id'] ?? $createData['collection_id'] ?? null;
                    
                    if ($testCollectionId) {
                        $info[] = "✅ Collection creation successful (ID: " . substr($testCollectionId, 0, 12) . "...)";
                        
                        // Clean up test collection
                        try {
                            // Use correct delete endpoint: /collections/{id}
                            $deleteUrl = $workingEndpoint . '/' . rawurlencode($testCollectionId);
                            $deleteResponse = $http->delete($deleteUrl, [], $headers, 10);
                            if ($deleteResponse->code >= 200 && $deleteResponse->code < 300) {
                                $info[] = "🧹 Test collection cleaned up successfully";
                            }
                        } catch (\Throwable $e) {
                            $info[] = "⚠️ Test collection created but cleanup failed - you may need to delete it manually";
                        }
                    } else {
                        $info[] = "⚠️ Collection created but no ID returned";
                    }
                } else {
                    $info[] = "❌ Collection creation failed (HTTP {$createResponse->code})";
                    $createError = substr($createResponse->body, 0, 200);
                    if ($createError) {
                        $info[] = "Create error: {$createError}";
                    }
                }
            } catch (\Throwable $e) {
                $info[] = "❌ Collection creation test failed: " . $e->getMessage();
            }
            
            if ($response->code >= 200 && $response->code < 300) {
                $data = json_decode($response->body, true);
                if (is_array($data)) {
                    $collections = $data['collections'] ?? $data['data'] ?? $data;
                    if (is_array($collections)) {
                        $count = count($collections);
                        $info[] = "✅ IONOS API accessible ({$count} collections found)";
                        
                        foreach ($collections as $i => $collection) {
                            if ($i >= 10) { // Show up to 10 collections
                                $info[] = "... and " . ($count - 10) . " more";
                                break;
                            }
                            $id = $collection['id'] ?? $collection['collection_id'] ?? 'unknown';
                            $name = $collection['name'] ?? $collection['properties']['name'] ?? 'unnamed';
                            $info[] = "  - {$name} (ID: " . substr($id, 0, 12) . "...)";
                        }
                    } else {
                        $info[] = '⚠️ IONOS API returned unexpected format';
                    }
                } else {
                    $info[] = '⚠️ IONOS API returned non-JSON response';
                }
            } else {
                $info[] = "❌ IONOS API error (HTTP {$response->code})";
                $errorBody = substr($response->body, 0, 500);
                if ($errorBody) {
                    $info[] = "Error: {$errorBody}";
                }
                
                // Additional debugging for 401 errors
                if ($response->code === 401) {
                    $info[] = "🔧 401 Troubleshooting:";
                    $info[] = "- Check if token is valid and not expired";
                    $info[] = "- Verify Token ID matches the token";
                    $info[] = "- Ensure token has document-collections permissions";
                    $info[] = "- Try regenerating the token in IONOS console";
                }
            }
        } catch (\Throwable $e) {
            $info[] = '❌ IONOS API Error: ' . $e->getMessage();
        }
    } else {
        $info[] = '⚠️ No token/token_id for IONOS API check';
        if (!$token) $info[] = "Missing: IONOS Token";
        if (!$tokenId) $info[] = "Missing: IONOS Token ID";
    }
    
    return implode('<br>', $info);
}

/**
 * Fetch collections from IONOS API with detailed metadata
 */
function fetchCollectionsFromIONOS(string $token, string $tokenId, string $endpoint): array
{
    try {
        // Use the correct IONOS Inference API endpoint for document collections
        // Based on collections.ipynb: https://inference.de-txl.ionos.com/collections
        // This is the working endpoint confirmed by checkCollectionStatus function
        $apiBase = 'https://inference.de-txl.ionos.com';
        
        $http = HttpFactory::getHttp();
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ];
        
        // Add X-IONOS-Token-Id if provided
        if ($tokenId) {
            $headers['X-IONOS-Token-Id'] = $tokenId;
        }
        
        // GET /collections endpoint (correct path for IONOS Inference API)
        $collectionsUrl = $apiBase . '/collections';
        
        // Debug: Log the URL being called
        Log::add('Fetching collections from: ' . $collectionsUrl, Log::DEBUG, 'bears_aichatbot');
        
        $response = $http->get($collectionsUrl, $headers, 30);
        
        // Debug: Log response code
        Log::add('Collections API response code: ' . $response->code, Log::DEBUG, 'bears_aichatbot');
        
        if ($response->code >= 200 && $response->code < 300) {
            // Debug: Log raw response body (first 500 chars)
            Log::add('Collections API response body: ' . substr($response->body, 0, 500), Log::DEBUG, 'bears_aichatbot');
            
            $data = json_decode($response->body, true);
            if (is_array($data)) {
                // Debug: Log the structure of the response
                Log::add('Response structure keys: ' . json_encode(array_keys($data)), Log::DEBUG, 'bears_aichatbot');
                

                // The API returns collections in 'items' array according to the logs
                // The response structure is: {"href":"...", "id":"...", "items":[...], "type":"..."}
                $collections = $data['items'] ?? $data['collections'] ?? $data['data'] ?? $data['properties'] ?? [];
                
                // If the response is directly an array of collections
                if (!empty($data) && isset($data[0]) && (isset($data[0]['id']) || isset($data[0]['properties']))) {
                    $collections = $data;
                }
                
                // Log what we found for debugging
                Log::add('Collections extracted from items: ' . json_encode(is_array($collections) ? count($collections) : 'not an array'), Log::DEBUG, 'bears_aichatbot');
                
                // Debug: Log number of collections found
                Log::add('Collections found: ' . (is_array($collections) ? count($collections) : 'not an array'), Log::DEBUG, 'bears_aichatbot');
                
                if (is_array($collections)) {
                    // Process collections to extract properties if needed
                    $processedCollections = [];
                    foreach ($collections as $collection) {
                        // Handle both direct properties and nested properties structure
                        if (isset($collection['properties'])) {
                            // Extract properties to top level for easier access
                            $processed = array_merge(
                                ['id' => $collection['id'] ?? ''],
                                $collection['properties']
                            );
                            
                            // Map metadata fields to expected format
                            if (isset($collection['metadata'])) {
                                $processed['metadata'] = $collection['metadata'];
                                // Map IONOS metadata fields to our expected fields
                                $processed['created_at'] = $collection['metadata']['createdDate'] ?? null;
                                $processed['updated_at'] = $collection['metadata']['lastModifiedDate'] ?? null;
                            }
                            
                            // Map other fields from properties
                            $processed['document_count'] = $collection['properties']['documentsCount'] ?? 0;
                            $processed['size_bytes'] = $collection['properties']['sizeBytes'] ?? 0;
                            
                            // Extract embedding model if available
                            if (isset($collection['properties']['embedding']['model'])) {
                                $processed['embedding_model'] = $collection['properties']['embedding']['model'];
                            }
                            
                            // Set status if available
                            if (isset($collection['properties']['status'])) {
                                $processed['status'] = $collection['properties']['status'];
                            }
                            
                            $processedCollections[] = $processed;
                        } else {
                            // Already in flat structure or different format
                            $processedCollections[] = $collection;
                        }
                    }
                    return ['collections' => $processedCollections, 'error' => ''];
                } else {
                    return ['collections' => [], 'error' => 'IONOS API returned unexpected format'];
                }
            } else {
                return ['collections' => [], 'error' => 'IONOS API returned non-JSON response'];
            }
        } elseif ($response->code === 404) {
            // No collections found is not an error
            return ['collections' => [], 'error' => ''];
        } else {
            $errorBody = substr($response->body, 0, 500);
            $errorMsg = "IONOS API error (HTTP {$response->code})";
            if ($errorBody) {
                $errorData = json_decode($errorBody, true);
                if (is_array($errorData)) {
                    if (isset($errorData['message'])) {
                        $errorMsg .= ': ' . $errorData['message'];
                    } elseif (isset($errorData['error'])) {
                        $errorMsg .= ': ' . (is_string($errorData['error']) ? $errorData['error'] : json_encode($errorData['error']));
                    } else {
                        $errorMsg .= ': ' . $errorBody;
                    }
                } else {
                    $errorMsg .= ': ' . $errorBody;
                }
            }
            return ['collections' => [], 'error' => $errorMsg];
        }
    } catch (\Throwable $e) {
        return ['collections' => [], 'error' => 'Failed to connect to IONOS API: ' . $e->getMessage()];
    }
}

/**
 * Delete a collection from IONOS API and clean up local database
 */
function deleteCollection(string $collectionId, string $token, string $tokenId, string $endpoint = ''): array
{
    try {
        // Use the correct IONOS Inference API endpoint for document collections
        // Based on collections.ipynb: https://inference.de-txl.ionos.com/collections
        $apiBase = 'https://inference.de-txl.ionos.com';
        
        $http = HttpFactory::getHttp();
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ];
        
        // Add X-IONOS-Token-Id if provided
        if ($tokenId) {
            $headers['X-IONOS-Token-Id'] = $tokenId;
        }
        
        // DELETE /collections/{collectionId}
        $deleteUrl = $apiBase . '/collections/' . rawurlencode($collectionId);
        
        // Log the delete attempt
        Log::add('Attempting to delete collection: ' . $collectionId . ' at URL: ' . $deleteUrl, Log::INFO, 'bears_aichatbot');
        Log::add('Using Token ID: ' . substr($tokenId, 0, 8) . '...', Log::DEBUG, 'bears_aichatbot');
        Log::add('Using Token (first 20 chars): ' . substr($token, 0, 20) . '...', Log::DEBUG, 'bears_aichatbot');
        
        // The delete method signature is: delete($url, $headers = [], $timeout = null, $userAgent = null)
        // So headers should be the second parameter, not third
        $response = $http->delete($deleteUrl, $headers, 30);
        
        // Log the response
        Log::add('Delete response code: ' . $response->code, Log::INFO, 'bears_aichatbot');
        
        if ($response->code >= 200 && $response->code < 300) {
            // Successfully deleted from IONOS, now clean up local database
            try {
                $db = Factory::getContainer()->get('DatabaseDriver');
                
                // Clear collection ID from state table if it matches
                $stateQuery = $db->getQuery(true)
                    ->select($db->quoteName('collection_id'))
                    ->from($db->quoteName('#__aichatbot_state'))
                    ->where($db->quoteName('id') . ' = 1')
                    ->setLimit(1);
                $db->setQuery($stateQuery);
                $currentCollectionId = (string)($db->loadResult() ?? '');
                
                if ($currentCollectionId === $collectionId) {
                    $updateQuery = $db->getQuery(true)
                        ->update($db->quoteName('#__aichatbot_state'))
                        ->set($db->quoteName('collection_id') . ' = NULL')
                        ->where($db->quoteName('id') . ' = 1');
                    $db->setQuery($updateQuery)->execute();
                }
                
                // Delete all document mappings for this collection
                $deleteDocsQuery = $db->getQuery(true)
                    ->delete($db->quoteName('#__aichatbot_docs'));
                $db->setQuery($deleteDocsQuery)->execute();
                
                // Delete any pending jobs
                $deleteJobsQuery = $db->getQuery(true)
                    ->delete($db->quoteName('#__aichatbot_jobs'));
                $db->setQuery($deleteJobsQuery)->execute();
                
            } catch (\Throwable $e) {
                // Log database cleanup error but don't fail the operation
                Log::add('Database cleanup error after collection deletion: ' . $e->getMessage(), Log::WARNING, 'bears_aichatbot');
            }
            
            return ['success' => true, 'message' => 'Collection deleted successfully'];
            
        } elseif ($response->code === 404) {
            // Collection not found, consider it already deleted
            return ['success' => true, 'message' => 'Collection was already deleted'];
            
        } else {
            $errorBody = substr($response->body, 0, 500);
            $errorMsg = "Failed to delete collection (HTTP {$response->code})";
            
            // Log the full error for debugging
            Log::add('Delete failed - HTTP ' . $response->code . ', Body: ' . $errorBody, Log::ERROR, 'bears_aichatbot');
            
            if ($errorBody) {
                $errorData = json_decode($errorBody, true);
                if (is_array($errorData)) {
                    if (isset($errorData['message'])) {
                        $errorMsg .= ': ' . $errorData['message'];
                    } elseif (isset($errorData['error'])) {
                        $errorMsg .= ': ' . (is_string($errorData['error']) ? $errorData['error'] : json_encode($errorData['error']));
                    } else {
                        $errorMsg .= ': ' . $errorBody;
                    }
                } else {
                    $errorMsg .= ': ' . $errorBody;
                }
            }
            
            // Special handling for 401 errors
            if ($response->code === 401) {
                $errorMsg .= '. Please check your IONOS API credentials and permissions.';
                Log::add('401 Unauthorized - Token may not have delete permissions or may be invalid', Log::ERROR, 'bears_aichatbot');
            } elseif ($response->code === 403) {
                $errorMsg .= '. You do not have permission to delete this collection.';
                Log::add('403 Forbidden - Collection may be protected or owned by another account', Log::ERROR, 'bears_aichatbot');
            }
            
            return ['success' => false, 'message' => $errorMsg];
        }
        
    } catch (\Throwable $e) {
        return ['success' => false, 'message' => 'Failed to connect to IONOS API: ' . $e->getMessage()];
    }
}

/**
 * Sync articles to a collection
 */
function syncArticlesToCollection(string $collectionId, string $token, string $tokenId): array
{
    $synced = 0;
    $failed = 0;
    
    try {
        $db = Factory::getContainer()->get('DatabaseDriver');
        
        // Get selected categories from module config
        $moduleQuery = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_bears_aichatbot'))
            ->where($db->quoteName('published') . ' = 1')
            ->setLimit(1);
        $db->setQuery($moduleQuery);
        $moduleParams = new Registry($db->loadResult());
        
        $selectedCategories = $moduleParams->get('selected_categories', []);
        if (is_string($selectedCategories)) {
            $selectedCategories = array_filter(array_map('intval', explode(',', $selectedCategories)));
        }
        
        // Get articles from selected categories - properly quote column names including reserved words
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('introtext'),
                $db->quoteName('fulltext'),  // fulltext is a MySQL reserved word, must be quoted
                $db->quoteName('catid'),
                $db->quoteName('created'),
                $db->quoteName('modified')
            ])
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1');
        
        if (!empty($selectedCategories)) {
            $query->where($db->quoteName('catid') . ' IN (' . implode(',', $selectedCategories) . ')');
        }
        
        $db->setQuery($query);
        $articles = $db->loadObjectList();
        
        // Use the IONOS Inference API for documents
        $apiBase = 'https://inference.de-txl.ionos.com';
        $http = HttpFactory::getHttp();
        
        foreach ($articles as $article) {
            try {
                // Prepare document content
                $content = strip_tags($article->title . "\n\n" . $article->introtext . "\n\n" . $article->fulltext);
                $content = preg_replace('/\s+/', ' ', $content);
                $content = trim($content);
                
                // Skip empty articles
                if (empty($content) || strlen($content) < 50) {
                    continue;
                }
                
                // Prepare document payload for IONOS Inference API
                // According to API docs: https://docs.ionos.com/cloud/ai/ai-model-hub/tutorials/document-collections
                // IMPORTANT: Content must be base64 encoded and limited to 65535 characters
                
                // Limit content to 65535 characters before encoding
                if (strlen($content) > 65535) {
                    $content = substr($content, 0, 65535);
                    Log::add('Article ID ' . $article->id . ' content truncated to 65535 characters', Log::DEBUG, 'bears_aichatbot');
                }
                
                // Base64 encode the content as required by IONOS API
                $encodedContent = base64_encode($content);
                
                $documentPayload = [
                    'content' => $encodedContent,
                    'metadata' => [
                        'article_id' => (string)$article->id,
                        'title' => $article->title,
                        'category_id' => (string)$article->catid,
                        'created' => $article->created,
                        'modified' => $article->modified,
                        'source' => 'joomla_article'
                    ]
                ];
                
                $headers = [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ];
                
                if ($tokenId) {
                    $headers['X-IONOS-Token-Id'] = $tokenId;
                }
                
                // POST document to collection
                // API endpoint: POST /collections/{collectionId}/documents
                $documentUrl = $apiBase . '/collections/' . rawurlencode($collectionId) . '/documents';
                
                Log::add('Syncing article ID ' . $article->id . ' to collection ' . $collectionId, Log::DEBUG, 'bears_aichatbot');
                Log::add('Document payload: ' . json_encode($documentPayload), Log::DEBUG, 'bears_aichatbot');
                
                $response = $http->post($documentUrl, json_encode($documentPayload), $headers, 30);
                
                if ($response->code >= 200 && $response->code < 300) {
                    $synced++;
                    
                    // Store document mapping in database
                    $docData = json_decode($response->body, true);
                    $documentId = $docData['id'] ?? $docData['document_id'] ?? 'doc-' . $article->id;
                    
                    // Ensure docs table exists
                    $createTableQuery = "CREATE TABLE IF NOT EXISTS `#__aichatbot_docs` (
                        `content_id` INT NOT NULL PRIMARY KEY,
                        `remote_id` VARCHAR(255) NOT NULL,
                        `content_hash` VARCHAR(64) NOT NULL,
                        `last_synced` DATETIME NOT NULL,
                        `state` TINYINT NOT NULL DEFAULT 1,
                        KEY `idx_remote_id` (`remote_id`),
                        KEY `idx_state` (`state`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    $db->setQuery($createTableQuery)->execute();
                    
                    // Check if mapping exists
                    $checkQuery = $db->getQuery(true)
                        ->select('content_id')
                        ->from($db->quoteName('#__aichatbot_docs'))
                        ->where($db->quoteName('content_id') . ' = ' . (int)$article->id)
                        ->setLimit(1);
                    $db->setQuery($checkQuery);
                    $exists = $db->loadResult();
                    
                    if (!$exists) {
                        // Insert new mapping
                        $insertQuery = $db->getQuery(true)
                            ->insert($db->quoteName('#__aichatbot_docs'))
                            ->columns(['content_id', 'remote_id', 'content_hash', 'last_synced', 'state'])
                            ->values(implode(',', [
                                (int)$article->id,
                                $db->quote($documentId),
                                $db->quote(hash('sha256', $content)),
                                $db->quote(date('Y-m-d H:i:s')),
                                1
                            ]));
                        $db->setQuery($insertQuery)->execute();
                    } else {
                        // Update existing mapping
                        $updateQuery = $db->getQuery(true)
                            ->update($db->quoteName('#__aichatbot_docs'))
                            ->set($db->quoteName('remote_id') . ' = ' . $db->quote($documentId))
                            ->set($db->quoteName('content_hash') . ' = ' . $db->quote(hash('sha256', $content)))
                            ->set($db->quoteName('last_synced') . ' = ' . $db->quote(date('Y-m-d H:i:s')))
                            ->where($db->quoteName('content_id') . ' = ' . (int)$article->id);
                        $db->setQuery($updateQuery)->execute();
                    }
                } else {
                    $failed++;
                    $errorBody = substr($response->body, 0, 500);
                    Log::add('Failed to sync article ID ' . $article->id . ': HTTP ' . $response->code . ' - ' . $errorBody, Log::WARNING, 'bears_aichatbot');
                    
                    // Log more details for 401 errors
                    if ($response->code === 401) {
                        Log::add('401 Unauthorized for document sync. Check if token has document write permissions.', Log::ERROR, 'bears_aichatbot');
                        Log::add('Collection ID: ' . $collectionId, Log::ERROR, 'bears_aichatbot');
                        Log::add('Document URL: ' . $documentUrl, Log::ERROR, 'bears_aichatbot');
                        
                        // Try to parse error response
                        $errorData = json_decode($response->body, true);
                        if ($errorData) {
                            Log::add('Error details: ' . json_encode($errorData), Log::ERROR, 'bears_aichatbot');
                        }
                    }
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::add('Error syncing article ID ' . $article->id . ': ' . $e->getMessage(), Log::ERROR, 'bears_aichatbot');
            }
        }
        
    } catch (\Throwable $e) {
        Log::add('Error in syncArticlesToCollection: ' . $e->getMessage(), Log::ERROR, 'bears_aichatbot');
    }
    
    return ['synced' => $synced, 'failed' => $failed];
}

/**
 * Get IONOS configuration from the first published Bears AI Chatbot module
 */
function getModuleConfig(): array
{
    try {
        $db = Factory::getContainer()->get('DatabaseDriver');
        
        // Find the first published Bears AI Chatbot module
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'params']))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_bears_aichatbot'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('id'))
            ->setLimit(1);
        
        $db->setQuery($query);
        $module = $db->loadObject();
        
        if (!$module) {
            return [];
        }
        
        $params = new Registry($module->params);
        
        // Get collection ID from centralized state table
        $collectionId = '';
        try {
            $stateQuery = $db->getQuery(true)
                ->select($db->quoteName('collection_id'))
                ->from($db->quoteName('#__aichatbot_state'))
                ->where($db->quoteName('id') . ' = 1')
                ->setLimit(1);
            $db->setQuery($stateQuery);
            $collectionId = (string)($db->loadResult() ?? '');
        } catch (\Throwable $e) {
            // State table might not exist yet
            $collectionId = '';
        }
        
        return [
            'token' => trim((string)$params->get('ionos_token', '')),
            'token_id' => trim((string)$params->get('ionos_token_id', '')),
            'collection_id' => $collectionId,
            'model' => trim((string)$params->get('ionos_model', '')),
            'endpoint' => trim((string)$params->get('ionos_endpoint', '')),
            'module_id' => $module->id,
        ];
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * Get keyword usage statistics from the database with time period filtering
 */
function getKeywordStats(string $period = 'all'): array
{
    try {
        $db = Factory::getContainer()->get('DatabaseDriver');
        
        // Ensure keywords table exists
        $ddl = "CREATE TABLE IF NOT EXISTS `#__aichatbot_keywords` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `keyword` VARCHAR(100) NOT NULL,
  `usage_count` INT DEFAULT 1,
  `first_used` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `avg_tokens` DECIMAL(8,2) DEFAULT 0,
  `total_tokens` INT DEFAULT 0,
  `success_rate` DECIMAL(5,2) DEFAULT 0,
  `answered_count` INT DEFAULT 0,
  `refused_count` INT DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_keyword` (`keyword`),
  KEY `idx_usage_count` (`usage_count`),
  KEY `idx_last_used` (`last_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->setQuery($ddl)->execute();
        
        // Build time period filter
        $timeFilter = '';
        if ($period !== 'all') {
            if ($period === 'ytd') {
                $timeFilter = $db->quoteName('last_used') . ' >= ' . $db->quote(date('Y') . '-01-01 00:00:00');
            } elseif (is_numeric($period)) {
                $days = (int)$period;
                $timeFilter = $db->quoteName('last_used') . ' >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';
            }
        }
        
        // Get top keywords by usage with time filtering
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('keyword'),
                $db->quoteName('usage_count'),
                $db->quoteName('first_used'),
                $db->quoteName('last_used'),
                $db->quoteName('avg_tokens'),
                $db->quoteName('total_tokens'),
                $db->quoteName('success_rate'),
                $db->quoteName('answered_count'),
                $db->quoteName('refused_count')
            ])
            ->from($db->quoteName('#__aichatbot_keywords'));
        
        if ($timeFilter) {
            $query->where($timeFilter);
        }
        
        $query->order($db->quoteName('usage_count') . ' DESC')
              ->setLimit(50);
        
        $db->setQuery($query);
        $keywords = $db->loadAssocList();
        
        // Get total stats with time filtering
        $totalQuery = $db->getQuery(true)
            ->select([
                'COUNT(*) AS total_keywords',
                'SUM(' . $db->quoteName('usage_count') . ') AS total_queries',
                'AVG(' . $db->quoteName('success_rate') . ') AS avg_success_rate'
            ])
            ->from($db->quoteName('#__aichatbot_keywords'));
        
        if ($timeFilter) {
            $totalQuery->where($timeFilter);
        }
        
        $db->setQuery($totalQuery);
        $totals = $db->loadObject();
        
        // Get recent trending keywords (always last 7 days for trending section)
        $recentQuery = $db->getQuery(true)
            ->select([
                $db->quoteName('keyword'),
                $db->quoteName('usage_count'),
                $db->quoteName('success_rate')
            ])
            ->from($db->quoteName('#__aichatbot_keywords'))
            ->where($db->quoteName('last_used') . ' >= DATE_SUB(NOW(), INTERVAL 7 DAY)')
            ->order($db->quoteName('usage_count') . ' DESC')
            ->setLimit(10);
        
        $db->setQuery($recentQuery);
        $trending = $db->loadAssocList();
        
        return [
            'keywords' => $keywords ?: [],
            'totals' => [
                'total_keywords' => (int)($totals->total_keywords ?? 0),
                'total_queries' => (int)($totals->total_queries ?? 0),
                'avg_success_rate' => round((float)($totals->avg_success_rate ?? 0), 1)
            ],
            'trending' => $trending ?: [],
            'period' => $period
        ];
        
    } catch (\Throwable $e) {
        return [
            'keywords' => [],
            'totals' => ['total_keywords' => 0, 'total_queries' => 0, 'avg_success_rate' => 0],
            'trending' => [],
            'period' => $period
        ];
    }
}

/**
 * Extract and normalize keywords from a message using configurable settings
 */
function extractKeywords(string $message, ?Registry $params = null): array
{
    // Get configuration from module params or use defaults
    $minLength = 3;
    $maxLength = 50;
    $ignoreWords = [];
    
    if ($params) {
        $minLength = (int)$params->get('keyword_min_length', 3);
        $maxLength = (int)$params->get('keyword_max_length', 50);
        $ignoreWordsString = trim((string)$params->get('ignore_words', ''));
        
        if ($ignoreWordsString !== '') {
            $ignoreWords = array_map('trim', explode(',', mb_strtolower($ignoreWordsString, 'UTF-8')));
            $ignoreWords = array_filter($ignoreWords); // Remove empty strings
        }
    } else {
        // Fallback to default English ignore words if no params provided
        $ignoreWords = [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
            'will', 'would', 'could', 'should', 'may', 'might', 'can', 'must', 'shall',
            'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them',
            'my', 'your', 'his', 'her', 'its', 'our', 'their', 'this', 'that', 'these', 'those',
            'what', 'where', 'when', 'why', 'how', 'who', 'which', 'whose', 'whom',
            'about', 'above', 'across', 'after', 'against', 'along', 'among', 'around', 'as',
            'before', 'behind', 'below', 'beneath', 'beside', 'between', 'beyond', 'during',
            'except', 'from', 'inside', 'into', 'like', 'near', 'off', 'outside', 'over',
            'since', 'through', 'throughout', 'till', 'toward', 'under', 'until', 'up', 'upon',
            'within', 'without', 'please', 'thanks', 'thank', 'hello', 'hi', 'hey'
        ];
    }
    
    // Debug logging
    Log::add('Starting keyword extraction for message: "' . $message . '"', Log::DEBUG, 'bears_aichatbot');
    Log::add('Using minLength=' . $minLength . ', maxLength=' . $maxLength . ', ignoreWords=' . count($ignoreWords), Log::DEBUG, 'bears_aichatbot');
    
    // Convert to lowercase and remove special characters
    $originalMessage = $message;
    $message = mb_strtolower($message, 'UTF-8');
    Log::add('After lowercase: "' . $message . '"', Log::DEBUG, 'bears_aichatbot');
    
    $message = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $message);
    Log::add('After removing special chars: "' . $message . '"', Log::DEBUG, 'bears_aichatbot');
    
    // Split into words
    $words = preg_split('/\s+/', trim($message));
    Log::add('Split into words: ' . json_encode($words), Log::DEBUG, 'bears_aichatbot');
    
    // Filter and process words
    $keywords = [];
    Log::add('Starting word filtering...', Log::DEBUG, 'bears_aichatbot');
    
    foreach ($words as $word) {
        $word = trim($word);
        Log::add('Processing word "' . $word . '"', Log::DEBUG, 'bears_aichatbot');
        
        // Skip if too short, too long, or is a ignore word
        if (mb_strlen($word) < $minLength) {
            Log::add('Skipping "' . $word . '" - too short (< ' . $minLength . ')', Log::DEBUG, 'bears_aichatbot');
            continue;
        }
        
        if (mb_strlen($word) > $maxLength) {
            Log::add('Skipping "' . $word . '" - too long (> ' . $maxLength . ')', Log::DEBUG, 'bears_aichatbot');
            continue;
        }
        
        if (in_array($word, $ignoreWords)) {
            Log::add('Skipping "' . $word . '" - ignore word', Log::DEBUG, 'bears_aichatbot');
            continue;
        }
        
        // Skip if it's just numbers
        if (is_numeric($word)) {
            Log::add('Skipping "' . $word . '" - numeric', Log::DEBUG, 'bears_aichatbot');
            continue;
        }
        
        Log::add('Keeping "' . $word . '" as keyword', Log::DEBUG, 'bears_aichatbot');
        $keywords[] = $word;
    }
    
    // Return unique keywords, limited to top 10 by frequency in this message
    $keywordCounts = array_count_values($keywords);
    arsort($keywordCounts);
    
    $finalKeywords = array_slice(array_keys($keywordCounts), 0, 10);
    Log::add('Final keywords for "' . $originalMessage . '": ' . json_encode($finalKeywords), Log::DEBUG, 'bears_aichatbot');
    
    return $finalKeywords;
}

/**
 * Update keyword statistics based on a chat interaction
 */
function updateKeywordStats(string $message, int $totalTokens, string $outcome): void
{
    try {
        $db = Factory::getContainer()->get('DatabaseDriver');
        
        // Get module configuration for keyword settings
        $moduleConfig = getModuleConfig();
        $moduleId = $moduleConfig['module_id'] ?? 0;
        $params = null;
        
        if ($moduleId > 0) {
            try {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('params'))
                    ->from($db->quoteName('#__modules'))
                    ->where($db->quoteName('id') . ' = ' . (int)$moduleId)
                    ->setLimit(1);
                $db->setQuery($query);
                $rawParams = (string)$db->loadResult();
                if ($rawParams !== '') {
                    $params = new Registry($rawParams);
                }
            } catch (\Throwable $e) {
                // Ignore error, will use defaults
            }
        }
        
        // Extract keywords from the message using configurable settings
        $keywords = extractKeywords($message, $params);
        
        if (empty($keywords)) {
            return;
        }
        
        // Determine if this was a successful interaction
        $wasAnswered = ($outcome === 'answered') ? 1 : 0;
        $wasRefused = ($outcome === 'refused') ? 1 : 0;
        
        foreach ($keywords as $keyword) {
            // Check if keyword exists
            $checkQuery = $db->getQuery(true)
                ->select(['id', 'usage_count', 'total_tokens', 'answered_count', 'refused_count'])
                ->from($db->quoteName('#__aichatbot_keywords'))
                ->where($db->quoteName('keyword') . ' = ' . $db->quote($keyword))
                ->setLimit(1);
            
            $db->setQuery($checkQuery);
            $existing = $db->loadObject();
            
            if ($existing) {
                // Update existing keyword
                $newUsageCount = $existing->usage_count + 1;
                $newTotalTokens = $existing->total_tokens + $totalTokens;
                $newAnsweredCount = $existing->answered_count + $wasAnswered;
                $newRefusedCount = $existing->refused_count + $wasRefused;
                $newAvgTokens = $newTotalTokens / $newUsageCount;
                $newSuccessRate = ($newAnsweredCount / $newUsageCount) * 100;
                
                $updateQuery = $db->getQuery(true)
                    ->update($db->quoteName('#__aichatbot_keywords'))
                    ->set($db->quoteName('usage_count') . ' = ' . (int)$newUsageCount)
                    ->set($db->quoteName('total_tokens') . ' = ' . (int)$newTotalTokens)
                    ->set($db->quoteName('avg_tokens') . ' = ' . number_format($newAvgTokens, 2))
                    ->set($db->quoteName('success_rate') . ' = ' . number_format($newSuccessRate, 2))
                    ->set($db->quoteName('answered_count') . ' = ' . (int)$newAnsweredCount)
                    ->set($db->quoteName('refused_count') . ' = ' . (int)$newRefusedCount)
                    ->set($db->quoteName('last_used') . ' = NOW()')
                    ->where($db->quoteName('id') . ' = ' . (int)$existing->id);
                
                $db->setQuery($updateQuery)->execute();
                
            } else {
                // Insert new keyword
                $avgTokens = $totalTokens;
                $successRate = $wasAnswered * 100; // 100% if answered, 0% if refused
                
                $insertQuery = $db->getQuery(true)
                    ->insert($db->quoteName('#__aichatbot_keywords'))
                    ->columns([
                        $db->quoteName('keyword'),
                        $db->quoteName('usage_count'),
                        $db->quoteName('avg_tokens'),
                        $db->quoteName('total_tokens'),
                        $db->quoteName('success_rate'),
                        $db->quoteName('answered_count'),
                        $db->quoteName('refused_count')
                    ])
                    ->values(implode(',', [
                        $db->quote($keyword),
                        1,
                        number_format($avgTokens, 2),
                        (int)$totalTokens,
                        number_format($successRate, 2),
                        (int)$wasAnswered,
                        (int)$wasRefused
                    ]));
                
                $db->setQuery($insertQuery)->execute();
            }
        }
        
    } catch (\Throwable $e) {
        // Silently fail - keyword tracking shouldn't break the main functionality
        Log::add('Keyword tracking error: ' . $e->getMessage(), Log::WARNING, 'bears_aichatbot');
    }
}

/**
 * Get token usage data from the database for different time periods
 */
function getTokenUsageData(): array
{
    try {
        $db = Factory::getContainer()->get('DatabaseDriver');
        
        // Check if usage table exists
        $tables = $db->getTableList();
        $prefix = $db->getPrefix();
        $usageTable = $prefix . 'aichatbot_usage';
        
        if (!in_array($usageTable, $tables)) {
            // Log for debugging
            Log::add('Usage table does not exist: ' . $usageTable, Log::WARNING, 'bears_aichatbot');
            // Return empty data if table doesn't exist yet
            return [
                'today' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                '7day' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                '30day' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                '6mo' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'ytd' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            ];
        }
        
        // Log table exists
        Log::add('Usage table exists: ' . $usageTable, Log::DEBUG, 'bears_aichatbot');
        
        // First, let's check if there's any data at all
        $countQuery = $db->getQuery(true)
            ->select('COUNT(*) as total')
            ->from($db->quoteName('#__aichatbot_usage'));
        $db->setQuery($countQuery);
        $totalRecords = (int)$db->loadResult();
        
        Log::add('Total usage records in database: ' . $totalRecords, Log::DEBUG, 'bears_aichatbot');
        
        if ($totalRecords === 0) {
            // No data yet
            return [
                'today' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                '7day' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                '30day' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                '6mo' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'ytd' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            ];
        }
        
        // Get the date range of existing data
        $rangeQuery = $db->getQuery(true)
            ->select([
                'MIN(' . $db->quoteName('created_at') . ') as min_date',
                'MAX(' . $db->quoteName('created_at') . ') as max_date'
            ])
            ->from($db->quoteName('#__aichatbot_usage'));
        $db->setQuery($rangeQuery);
        $dateRange = $db->loadObject();
        
        Log::add('Data date range: ' . $dateRange->min_date . ' to ' . $dateRange->max_date, Log::DEBUG, 'bears_aichatbot');
        
        $now = new DateTime();
        
        // Fix the date calculations - clone $now for each modification
        $periods = [
            'today' => $now->format('Y-m-d') . ' 00:00:00',
            '7day' => (clone $now)->modify('-7 days')->format('Y-m-d H:i:s'),
            '30day' => (clone $now)->modify('-30 days')->format('Y-m-d H:i:s'),
            '6mo' => (clone $now)->modify('-6 months')->format('Y-m-d H:i:s'),
            'ytd' => $now->format('Y') . '-01-01 00:00:00',
        ];
        
        $usage = [];
        
        foreach ($periods as $period => $startDate) {
            $query = $db->getQuery(true)
                ->select([
                    'COALESCE(SUM(' . $db->quoteName('prompt_tokens') . '), 0) AS prompt_tokens',
                    'COALESCE(SUM(' . $db->quoteName('completion_tokens') . '), 0) AS completion_tokens',
                    'COALESCE(SUM(' . $db->quoteName('total_tokens') . '), 0) AS total_tokens',
                    'COUNT(*) as record_count'
                ])
                ->from($db->quoteName('#__aichatbot_usage'))
                ->where($db->quoteName('created_at') . ' >= ' . $db->quote($startDate));
            
            $db->setQuery($query);
            $result = $db->loadObject();
            
            Log::add('Period ' . $period . ' (>= ' . $startDate . '): ' . 
                     'records=' . ($result->record_count ?? 0) . ', ' .
                     'prompt=' . ($result->prompt_tokens ?? 0) . ', ' .
                     'completion=' . ($result->completion_tokens ?? 0) . ', ' .
                     'total=' . ($result->total_tokens ?? 0), Log::DEBUG, 'bears_aichatbot');
            
            $usage[$period] = [
                'prompt_tokens' => (int)($result->prompt_tokens ?? 0),
                'completion_tokens' => (int)($result->completion_tokens ?? 0),
                'total_tokens' => (int)($result->total_tokens ?? 0),
            ];
        }
        
        return $usage;
        
    } catch (\Throwable $e) {
        Log::add('Error getting token usage data: ' . $e->getMessage(), Log::ERROR, 'bears_aichatbot');
        // Return empty data on error
        return [
            'today' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            '7day' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            '30day' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            '6mo' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            'ytd' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
        ];
    }
}

/**
 * Calculate usage trends (percentage change from previous period)
 */
function calculateUsageTrends(array $tokenUsage): array
{
    try {
        $db = Factory::getContainer()->get('DatabaseDriver');
        
        // Check if usage table exists
        $tables = $db->getTableList();
        $prefix = $db->getPrefix();
        $usageTable = $prefix . 'aichatbot_usage';
        
        if (!in_array($usageTable, $tables)) {
            return [
                'today_change' => '0%',
                '7day_change' => '0%',
                '30day_change' => '0%',
                '6mo_change' => '0%',
                'ytd_change' => '0%',
            ];
        }
        
        $now = new DateTime();
        $trends = [];
        
        // Calculate trends by comparing with previous periods
        $comparisons = [
            'today' => [
                'current_start' => $now->format('Y-m-d') . ' 00:00:00',
                'previous_start' => $now->modify('-1 day')->format('Y-m-d') . ' 00:00:00',
                'previous_end' => $now->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
            ],
            '7day' => [
                'current_start' => $now->modify('-7 days')->format('Y-m-d H:i:s'),
                'previous_start' => $now->modify('-7 days')->format('Y-m-d H:i:s'),
                'previous_end' => $now->modify('+7 days')->format('Y-m-d H:i:s'),
            ],
        ];
        
        foreach (['today', '7day'] as $period) {
            $current = $tokenUsage[$period]['total_tokens'] ?? 0;
            
            if (isset($comparisons[$period])) {
                $comp = $comparisons[$period];
                $query = $db->getQuery(true)
                    ->select('SUM(' . $db->quoteName('total_tokens') . ') AS total_tokens')
                    ->from($db->quoteName('#__aichatbot_usage'))
                    ->where($db->quoteName('created_at') . ' >= ' . $db->quote($comp['previous_start']))
                    ->where($db->quoteName('created_at') . ' < ' . $db->quote($comp['previous_end']));
                
                $db->setQuery($query);
                $previous = (int)($db->loadResult() ?? 0);
                
                if ($previous > 0) {
                    $change = (($current - $previous) / $previous) * 100;
                    $trends[$period . '_change'] = ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
                } else {
                    $trends[$period . '_change'] = $current > 0 ? '+100%' : '0%';
                }
            } else {
                $trends[$period . '_change'] = '0%';
            }
        }
        
        // For longer periods, use simple indicators
        $trends['30day_change'] = $tokenUsage['30day']['total_tokens'] > 0 ? '+' . number_format($tokenUsage['30day']['total_tokens'] / 1000, 1) . 'K' : '0%';
        $trends['6mo_change'] = $tokenUsage['6mo']['total_tokens'] > 0 ? '+' . number_format($tokenUsage['6mo']['total_tokens'] / 1000, 1) . 'K' : '0%';
        $trends['ytd_change'] = $tokenUsage['ytd']['total_tokens'] > 0 ? '+' . number_format($tokenUsage['ytd']['total_tokens'] / 1000, 1) . 'K' : '0%';
        
        return $trends;
        
    } catch (\Throwable $e) {
        return [
            'today_change' => '0%',
            '7day_change' => '0%',
            '30day_change' => '0%',
            '6mo_change' => '0%',
            'ytd_change' => '0%',
        ];
    }
}

// Set up Joomla logging for this component
Log::addLogger(
    ['text_file' => 'bears_aichatbot.php'],
    Log::ALL,
    ['bears_aichatbot']
);

// Load component language
$lang = Factory::getLanguage();
$lang->load('com_bears_aichatbot', JPATH_ADMINISTRATOR);

// Load component CSS
HTMLHelper::_('stylesheet', 'com_bears_aichatbot/admin.css', ['version' => 'auto', 'relative' => true]);

// Load Bootstrap for modals (Joomla 4/5)
HTMLHelper::_('bootstrap.modal');
HTMLHelper::_('bootstrap.dropdown');

// Handle AJAX requests
$input = Factory::getApplication()->input;
$task = $input->getCmd('task', '');

if ($task === 'createCollection') {
    // Handle AJAX create collection request
    $name = $input->getString('name', '');
    $description = $input->getString('description', '');
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Collection name is required']);
        exit;
    }
    
    // Get IONOS configuration
    $moduleConfig = getModuleConfig();
    $ionosToken = $moduleConfig['token'] ?? '';
    $ionosTokenId = $moduleConfig['token_id'] ?? '';
    
    if (empty($ionosToken)) {
        echo json_encode(['success' => false, 'message' => 'IONOS API credentials not configured']);
        exit;
    }
    
    // Create the collection
    try {
        // Use the correct IONOS Inference API endpoint for document collections
        // Based on collections.ipynb: https://inference.de-txl.ionos.com/collections
        $apiBase = 'https://inference.de-txl.ionos.com';
        
        $http = HttpFactory::getHttp();
        $headers = [
            'Authorization' => 'Bearer ' . $ionosToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
        
        // Add X-IONOS-Token-Id if provided
        if ($ionosTokenId) {
            $headers['X-IONOS-Token-Id'] = $ionosTokenId;
        }
        
        $payload = [
            'properties' => [
                'name' => $name,
                'description' => $description ?: 'Created via Bears AI Chatbot admin',
                'chunking' => [
                    'enabled' => true,
                    'strategy' => [
                        'config' => [
                            'chunk_overlap' => 50,
                            'chunk_size' => 512
                        ]
                    ]
                ],
                'embedding' => [
                    'model' => 'BAAI/bge-large-en-v1.5'
                ],
                'engine' => [
                    'db_type' => 'pgvector'
                ]
            ]
        ];
        
        // POST /collections
        $response = $http->post($apiBase . '/collections', json_encode($payload), $headers, 30);
        
        if ($response->code >= 200 && $response->code < 300) {
            $data = json_decode($response->body, true);
            $collectionId = $data['id'] ?? $data['collection_id'] ?? null;
            
            if ($collectionId) {
                // Save as active collection
                $db = Factory::getContainer()->get('DatabaseDriver');
                $updateQuery = $db->getQuery(true)
                    ->update($db->quoteName('#__aichatbot_state'))
                    ->set($db->quoteName('collection_id') . ' = ' . $db->quote($collectionId))
                    ->where($db->quoteName('id') . ' = 1');
                $db->setQuery($updateQuery)->execute();
                
                // Automatically sync articles to the new collection
                $syncResult = syncArticlesToCollection($collectionId, $ionosToken, $ionosTokenId);
                
                $message = 'Collection created successfully';
                if ($syncResult['synced'] > 0) {
                    $message .= ' and populated with ' . $syncResult['synced'] . ' articles';
                }
                if ($syncResult['failed'] > 0) {
                    $message .= ' (' . $syncResult['failed'] . ' articles failed to sync)';
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => $message, 
                    'collection_id' => $collectionId,
                    'synced' => $syncResult['synced'],
                    'failed' => $syncResult['failed']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Collection created but no ID returned']);
            }
        } else {
            $errorBody = json_decode($response->body, true);
            $errorMsg = $errorBody['message'] ?? $errorBody['error'] ?? 'Failed to create collection';
            echo json_encode(['success' => false, 'message' => $errorMsg]);
        }
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    exit;
}

if ($task === 'syncDocuments') {
    // Handle sync documents request with Server-Sent Events for real-time progress
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Disable Nginx buffering
    
    // Enable output buffering with immediate flush for progress updates
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', false);
    @ini_set('implicit_flush', true);
    @ob_implicit_flush(true);
    
    // Clear any existing buffers
    while (@ob_end_clean());
    
    // Function to send SSE message
    function sendSSEMessage($event, $data) {
        echo "event: $event\n";
        echo "data: " . json_encode($data) . "\n\n";
        @ob_flush();
        @flush();
    }
    
    // Get IONOS configuration
    $moduleConfig = getModuleConfig();
    $ionosToken = $moduleConfig['token'] ?? '';
    $ionosTokenId = $moduleConfig['token_id'] ?? '';
    $collectionId = $moduleConfig['collection_id'] ?? '';
    
    if (empty($ionosToken)) {
        sendSSEMessage('error', ['success' => false, 'message' => 'IONOS API credentials not configured']);
        exit;
    }
    
    // If no collection exists, create one automatically
    if (empty($collectionId)) {
        try {
            $apiBase = 'https://inference.de-txl.ionos.com';
            $http = HttpFactory::getHttp();
            $headers = [
                'Authorization' => 'Bearer ' . $ionosToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ];
            
            if ($ionosTokenId) {
                $headers['X-IONOS-Token-Id'] = $ionosTokenId;
            }
            
            // Create a new collection
            $collectionName = 'bears-aichatbot-' . date('YmdHis');
            $payload = [
                'properties' => [
                    'name' => $collectionName,
                    'description' => 'Auto-created collection for Bears AI Chatbot',
                    'chunking' => [
                        'enabled' => true,
                        'strategy' => [
                            'config' => [
                                'chunk_overlap' => 50,
                                'chunk_size' => 512
                            ]
                        ]
                    ],
                    'embedding' => [
                        'model' => 'BAAI/bge-large-en-v1.5'
                    ],
                    'engine' => [
                        'db_type' => 'pgvector'
                    ]
                ]
            ];
            
            $response = $http->post($apiBase . '/collections', json_encode($payload), $headers, 30);
            
            if ($response->code >= 200 && $response->code < 300) {
                $data = json_decode($response->body, true);
                $collectionId = $data['id'] ?? null;
                
                if ($collectionId) {
                    // Save the new collection ID to state table
                    $db = Factory::getContainer()->get('DatabaseDriver');
                    
                    // Ensure state table exists
                    $createStateTable = "CREATE TABLE IF NOT EXISTS `#__aichatbot_state` (
                        `id` INT NOT NULL PRIMARY KEY,
                        `collection_id` VARCHAR(255) DEFAULT NULL,
                        `last_run_queue` DATETIME DEFAULT NULL,
                        `last_run_reconcile` DATETIME DEFAULT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    $db->setQuery($createStateTable)->execute();
                    
                    // Check if state record exists
                    $checkQuery = $db->getQuery(true)
                        ->select('id')
                        ->from($db->quoteName('#__aichatbot_state'))
                        ->where($db->quoteName('id') . ' = 1');
                    $db->setQuery($checkQuery);
                    $exists = $db->loadResult();
                    
                    if (!$exists) {
                        // Insert new state record
                        $insertQuery = $db->getQuery(true)
                            ->insert($db->quoteName('#__aichatbot_state'))
                            ->columns(['id', 'collection_id'])
                            ->values('1, ' . $db->quote($collectionId));
                        $db->setQuery($insertQuery)->execute();
                    } else {
                        // Update existing state record
                        $updateQuery = $db->getQuery(true)
                            ->update($db->quoteName('#__aichatbot_state'))
                            ->set($db->quoteName('collection_id') . ' = ' . $db->quote($collectionId))
                            ->where($db->quoteName('id') . ' = 1');
                        $db->setQuery($updateQuery)->execute();
                    }
                    
                    Log::add('Auto-created collection: ' . $collectionId, Log::INFO, 'bears_aichatbot');
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to create collection - no ID returned']);
                    exit;
                }
            } else {
                $errorBody = json_decode($response->body, true);
                $errorMsg = $errorBody['message'] ?? $errorBody['error'] ?? 'Failed to create collection';
                echo json_encode(['success' => false, 'message' => 'Failed to auto-create collection: ' . $errorMsg]);
                exit;
            }
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Error creating collection: ' . $e->getMessage()]);
            exit;
        }
    }
    
    try {
        $db = Factory::getContainer()->get('DatabaseDriver');
        
        // Get selected categories from module config
        $moduleQuery = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_bears_aichatbot'))
            ->where($db->quoteName('published') . ' = 1')
            ->setLimit(1);
        $db->setQuery($moduleQuery);
        $moduleParams = new Registry($db->loadResult());
        
        $selectedCategories = $moduleParams->get('selected_categories', []);
        if (is_string($selectedCategories)) {
            $selectedCategories = array_filter(array_map('intval', explode(',', $selectedCategories)));
        }
        
        // Get articles from selected categories - properly quote column names including reserved words
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('introtext'),
                $db->quoteName('fulltext'),  // fulltext is a MySQL reserved word, must be quoted
                $db->quoteName('catid'),
                $db->quoteName('created'),
                $db->quoteName('modified')
            ])
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1');
        
        if (!empty($selectedCategories)) {
            $query->where($db->quoteName('catid') . ' IN (' . implode(',', $selectedCategories) . ')');
        }
        
        $db->setQuery($query);
        $articles = $db->loadObjectList();
        
        $synced = 0;
        $failed = 0;
        
        // Use the new IONOS Inference API for documents
        $apiBase = 'https://inference.de-txl.ionos.com';
        $http = HttpFactory::getHttp();
        
        foreach ($articles as $article) {
            try {
                // Prepare document content
                $content = strip_tags($article->title . "\n\n" . $article->introtext . "\n\n" . $article->fulltext);
                $content = preg_replace('/\s+/', ' ', $content);
                $content = trim($content);
                
                // Skip empty articles
                if (empty($content)) {
                    continue;
                }
                
                // Prepare document payload for IONOS Inference API
                $documentPayload = [
                    'content' => $content,
                    'metadata' => [
                        'article_id' => $article->id,
                        'title' => $article->title,
                        'category_id' => $article->catid,
                        'created' => $article->created,
                        'modified' => $article->modified,
                        'source' => 'joomla_article'
                    ]
                ];
                
                $headers = [
                    'Authorization' => 'Bearer ' . $ionosToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ];
                
                if ($ionosTokenId) {
                    $headers['X-IONOS-Token-Id'] = $ionosTokenId;
                }
                
                // POST document to collection
                $documentUrl = $apiBase . '/collections/' . $collectionId . '/documents';
                $response = $http->post($documentUrl, json_encode($documentPayload), $headers, 30);
                
                if ($response->code >= 200 && $response->code < 300) {
                    $synced++;
                    
                    // Store document mapping in database
                    $docData = json_decode($response->body, true);
                    $documentId = $docData['id'] ?? $docData['document_id'] ?? 'doc-' . $article->id;
                    
                    // Check if mapping exists
                    $checkQuery = $db->getQuery(true)
                        ->select('content_id')
                        ->from($db->quoteName('#__aichatbot_docs'))
                        ->where($db->quoteName('content_id') . ' = ' . (int)$article->id)
                        ->setLimit(1);
                    $db->setQuery($checkQuery);
                    $exists = $db->loadResult();
                    
                    if (!$exists) {
                        // Insert new mapping
                        $insertQuery = $db->getQuery(true)
                            ->insert($db->quoteName('#__aichatbot_docs'))
                            ->columns(['content_id', 'remote_id', 'content_hash', 'last_synced', 'state'])
                            ->values(implode(',', [
                                (int)$article->id,
                                $db->quote($documentId),
                                $db->quote(hash('sha256', $content)),
                                $db->quote(date('Y-m-d H:i:s')),
                                1
                            ]));
                        $db->setQuery($insertQuery)->execute();
                    } else {
                        // Update existing mapping
                        $updateQuery = $db->getQuery(true)
                            ->update($db->quoteName('#__aichatbot_docs'))
                            ->set($db->quoteName('remote_id') . ' = ' . $db->quote($documentId))
                            ->set($db->quoteName('content_hash') . ' = ' . $db->quote(hash('sha256', $content)))
                            ->set($db->quoteName('last_synced') . ' = ' . $db->quote(date('Y-m-d H:i:s')))
                            ->where($db->quoteName('content_id') . ' = ' . (int)$article->id);
                        $db->setQuery($updateQuery)->execute();
                    }
                } else {
                    $failed++;
                    Log::add('Failed to sync article ID ' . $article->id . ': HTTP ' . $response->code, Log::WARNING, 'bears_aichatbot');
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::add('Error syncing article ID ' . $article->id . ': ' . $e->getMessage(), Log::ERROR, 'bears_aichatbot');
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Synced $synced articles successfully" . ($failed > 0 ? ", $failed failed" : ""),
            'synced' => $synced,
            'failed' => $failed,
            'total' => count($articles)
        ]);
        
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    exit;
}

if ($task === 'getDocuments') {
    // Handle AJAX get documents request
    header('Content-Type: application/json');
    
    $collectionId = $input->getString('collection_id', '');
    
    if (empty($collectionId)) {
        echo json_encode(['success' => false, 'message' => 'Collection ID is required']);
        exit;
    }
    
    // Get IONOS configuration
    $moduleConfig = getModuleConfig();
    $ionosToken = $moduleConfig['token'] ?? '';
    $ionosTokenId = $moduleConfig['token_id'] ?? '';
    
    if (empty($ionosToken)) {
        echo json_encode(['success' => false, 'message' => 'IONOS API credentials not configured']);
        exit;
    }
    
    try {
        $apiBase = 'https://inference.de-txl.ionos.com';
        $http = HttpFactory::getHttp();
        
        $headers = [
            'Authorization' => 'Bearer ' . $ionosToken,
            'Accept' => 'application/json'
        ];
        
        if ($ionosTokenId) {
            $headers['X-IONOS-Token-Id'] = $ionosTokenId;
        }
        
        // GET documents from collection
        $documentsUrl = $apiBase . '/collections/' . $collectionId . '/documents';
        $response = $http->get($documentsUrl, $headers, 30);
        
        if ($response->code >= 200 && $response->code < 300) {
            $data = json_decode($response->body, true);
            $documents = $data['items'] ?? $data['documents'] ?? [];
            
            echo json_encode([
                'success' => true,
                'documents' => $documents,
                'count' => count($documents)
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to fetch documents (HTTP ' . $response->code . ')'
            ]);
        }
        
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    exit;
}

if ($task === 'testQuery') {
    // Handle AJAX test query request
    header('Content-Type: application/json');
    
    $collectionId = $input->getString('collection_id', '');
    $query = $input->getString('query', '');
    
    if (empty($collectionId) || empty($query)) {
        echo json_encode(['success' => false, 'message' => 'Collection ID and query are required']);
        exit;
    }
    
    // Get IONOS configuration
    $moduleConfig = getModuleConfig();
    $ionosToken = $moduleConfig['token'] ?? '';
    $ionosTokenId = $moduleConfig['token_id'] ?? '';
    
    if (empty($ionosToken)) {
        echo json_encode(['success' => false, 'message' => 'IONOS API credentials not configured']);
        exit;
    }
    
    try {
        $apiBase = 'https://inference.de-txl.ionos.com';
        $http = HttpFactory::getHttp();
        
        $headers = [
            'Authorization' => 'Bearer ' . $ionosToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
        
        if ($ionosTokenId) {
            $headers['X-IONOS-Token-Id'] = $ionosTokenId;
        }
        
        // Search documents in collection
        $searchPayload = [
            'query' => $query,
            'limit' => 5
        ];
        
        $searchUrl = $apiBase . '/collections/' . $collectionId . '/search';
        $response = $http->post($searchUrl, json_encode($searchPayload), $headers, 30);
        
        if ($response->code >= 200 && $response->code < 300) {
            $data = json_decode($response->body, true);
            $results = $data['results'] ?? $data['items'] ?? [];
            
            echo json_encode([
                'success' => true,
                'results' => $results,
                'count' => count($results)
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to search documents (HTTP ' . $response->code . ')'
            ]);
        }
        
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    exit;
}

if ($task === 'deleteCollection') {
    // Handle AJAX delete collection request
    $collectionId = $input->getString('collection_id', '');
    
    // Log the delete request
    Log::add('Delete collection request received for ID: ' . $collectionId, Log::INFO, 'bears_aichatbot');
    
    if (empty($collectionId)) {
        echo json_encode(['success' => false, 'message' => 'Collection ID is required']);
        exit;
    }
    
    // Get IONOS configuration
    $moduleConfig = getModuleConfig();
    $ionosToken = $moduleConfig['token'] ?? '';
    $ionosTokenId = $moduleConfig['token_id'] ?? '';
    
    if (empty($ionosToken)) {
        echo json_encode(['success' => false, 'message' => 'IONOS API credentials not configured']);
        exit;
    }
    
    // Use the deleteCollection function which has proper error handling
    $result = deleteCollection($collectionId, $ionosToken, $ionosTokenId);
    
    // Return the result as JSON
    echo json_encode($result);
    exit;
    
    /* OLD CODE - replaced with function call above
    // Delete the collection using the correct endpoint
    try {
        // Use the correct IONOS Inference API endpoint for document collections
        // Based on collections.ipynb: https://inference.de-txl.ionos.com/collections
        $apiBase = 'https://inference.de-txl.ionos.com';
        
        $http = HttpFactory::getHttp();
        $headers = [
            'Authorization' => 'Bearer ' . $ionosToken,
            'Accept' => 'application/json'
        ];
        
        // Add X-IONOS-Token-Id if provided
        if ($ionosTokenId) {
            $headers['X-IONOS-Token-Id'] = $ionosTokenId;
        }
        
        // DELETE /collections/{collectionId}
        $response = $http->delete($apiBase . '/collections/' . rawurlencode($collectionId), [], $headers, 30);
        
        if ($response->code >= 200 && $response->code < 300) {
            // Clear from database if it was the active collection
            $db = Factory::getContainer()->get('DatabaseDriver');
            $stateQuery = $db->getQuery(true)
                ->select($db->quoteName('collection_id'))
                ->from($db->quoteName('#__aichatbot_state'))
                ->where($db->quoteName('id') . ' = 1')
                ->setLimit(1);
            $db->setQuery($stateQuery);
            $currentCollectionId = (string)($db->loadResult() ?? '');
            
            if ($currentCollectionId === $collectionId) {
                $updateQuery = $db->getQuery(true)
                    ->update($db->quoteName('#__aichatbot_state'))
                    ->set($db->quoteName('collection_id') . ' = NULL')
                    ->where($db->quoteName('id') . ' = 1');
                $db->setQuery($updateQuery)->execute();
            }
            
            echo json_encode(['success' => true, 'message' => 'Collection deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete collection (HTTP ' . $response->code . ')']);
        }
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    exit;
    */
}

// Get the requested view
$view = $input->getCmd('view', 'status');

// Validate view
$allowedViews = ['status', 'usage', 'collections', 'keywords'];
if (!in_array($view, $allowedViews)) {
    $view = 'status';
}

// Add navigation tabs
$app = Factory::getApplication();
$currentView = $view;
$tabs = [
    'status' => [
        'title' => Text::_('COM_BEARS_AICHATBOT_TAB_STATUS'),
        'icon' => 'fas fa-heartbeat',
        'url' => 'index.php?option=com_bears_aichatbot&view=status'
    ],
    'usage' => [
        'title' => Text::_('COM_BEARS_AICHATBOT_TAB_USAGE'),
        'icon' => 'fas fa-chart-line',
        'url' => 'index.php?option=com_bears_aichatbot&view=usage'
    ],
    'collections' => [
        'title' => Text::_('COM_BEARS_AICHATBOT_TAB_COLLECTIONS'),
        'icon' => 'fas fa-database',
        'url' => 'index.php?option=com_bears_aichatbot&view=collections'
    ],
    'keywords' => [
        'title' => Text::_('COM_BEARS_AICHATBOT_TAB_KEYWORDS'),
        'icon' => 'fas fa-tags',
        'url' => 'index.php?option=com_bears_aichatbot&view=keywords'
    ]
];

// Add navigation HTML to document head
$document = Factory::getDocument();
$navHtml = '<div class="com-bears-aichatbot-nav mb-4">
    <ul class="nav nav-tabs" role="tablist">';

foreach ($tabs as $tabView => $tab) {
    $activeClass = ($tabView === $currentView) ? ' active' : '';
    $navHtml .= '<li class="nav-item" role="presentation">
        <a class="nav-link' . $activeClass . '" href="' . \Joomla\CMS\Router\Route::_($tab['url']) . '" role="tab">
            <i class="' . $tab['icon'] . '"></i> ' . $tab['title'] . '
        </a>
    </li>';
}

$navHtml .= '</ul></div>';

// Store navigation for templates
$navigationHtml = $navHtml;

// Get IONOS configuration from the module (needed for all views)
$moduleConfig = getModuleConfig();
$ionosToken = $moduleConfig['token'] ?? '';
$ionosTokenId = $moduleConfig['token_id'] ?? '';
$ionosCollectionId = $moduleConfig['collection_id'] ?? '';
$ionosModel = $moduleConfig['model'] ?? '';
$ionosEndpoint = $moduleConfig['endpoint'] ?? '';

// Add navigation to all templates
echo $navigationHtml;

if ($view === 'collections') {
    // Collections View
    $document->setTitle(Text::_('COM_BEARS_AICHATBOT_COLLECTIONS_TITLE'));
    
    // Fetch collections from IONOS API
    $collections = [];
    $error = '';
    
    if ($ionosToken && $ionosTokenId) {
        try {
            $collectionsData = fetchCollectionsFromIONOS($ionosToken, $ionosTokenId, $ionosEndpoint);
            $collections = $collectionsData['collections'] ?? [];
            $error = $collectionsData['error'] ?? '';
        } catch (\Throwable $e) {
            $error = 'Failed to fetch collections: ' . $e->getMessage();
        }
    } else {
        $error = 'IONOS API credentials not configured. Please configure the Bears AI Chatbot module.';
    }
    
    // Prepare variables for collections template
    $title = Text::_('COM_BEARS_AICHATBOT_COLLECTIONS_TITLE');
    
    // Include collections template
    require JPATH_COMPONENT_ADMINISTRATOR . '/tmpl/collections/default.php';
    
} elseif ($view === 'usage') {
    // Token Usage View
    $document->setTitle(Text::_('COM_BEARS_AICHATBOT_USAGE_TITLE'));
    
    // Get real token usage data from database
    $tokenUsage = getTokenUsageData();
    
    // Calculate usage trends (percentage change from previous period)
    $usageTrends = calculateUsageTrends($tokenUsage);
    
    // Build usage summary content
    $summaryContent = '';
    $totalTokensAllTime = array_sum(array_column($tokenUsage, 'total_tokens'));
    $todayTokens = $tokenUsage['today']['total_tokens'] ?? 0;
    $weekTokens = $tokenUsage['7day']['total_tokens'] ?? 0;
    
    if ($totalTokensAllTime > 0) {
        $summaryContent = '<strong>' . Text::_('COM_BEARS_AICHATBOT_USAGE_SUMMARY') . '</strong><br>';
        $summaryContent .= Text::_('COM_BEARS_AICHATBOT_TODAY_USAGE') . ': ' . number_format($todayTokens) . ' tokens<br>';
        $summaryContent .= Text::_('COM_BEARS_AICHATBOT_WEEK_USAGE') . ': ' . number_format($weekTokens) . ' tokens<br>';
        $summaryContent .= Text::_('COM_BEARS_AICHATBOT_TOTAL_TRACKED') . ': ' . number_format($totalTokensAllTime) . ' tokens<br>';
        
        // Calculate estimated cost (using IONOS standard pricing)
        $estimatedCost = ($totalTokensAllTime / 1000) * 0.0005; // Rough estimate
        $summaryContent .= Text::_('COM_BEARS_AICHATBOT_ESTIMATED_COST') . ': ~$' . number_format($estimatedCost, 4);
    } else {
        $summaryContent = Text::_('COM_BEARS_AICHATBOT_NO_USAGE_YET');
    }
    
    // Prepare variables for usage template
    $title = Text::_('COM_BEARS_AICHATBOT_USAGE_TITLE');
    
    // Include usage template
    require JPATH_COMPONENT_ADMINISTRATOR . '/tmpl/usage/default.php';
    
} elseif ($view === 'keywords') {
    // Keywords View
    $document->setTitle(Text::_('COM_BEARS_AICHATBOT_KEYWORDS_TITLE'));
    
    // Get selected time period from request
    $selectedPeriod = $input->getCmd('period', '30'); // Default to 30 days
    
    // Validate period
    $validPeriods = ['7', '30', '60', '90', 'ytd', 'all'];
    if (!in_array($selectedPeriod, $validPeriods)) {
        $selectedPeriod = '30';
    }
    
    // Get keyword statistics with time filtering
    $keywordStats = getKeywordStats($selectedPeriod);
    $keywords = $keywordStats['keywords'];
    $totals = $keywordStats['totals'];
    $trending = $keywordStats['trending'];
    
    // Prepare variables for keywords template
    $title = Text::_('COM_BEARS_AICHATBOT_KEYWORDS_TITLE');
    
    // Ensure all variables are available in template scope
    // (selectedPeriod, keywords, totals, trending, title are all defined above)
    
    // Include keywords template
    require JPATH_COMPONENT_ADMINISTRATOR . '/tmpl/keywords/default.php';
    
} else {
    // System Status View (default)
    $document->setTitle(Text::_('COM_BEARS_AICHATBOT_STATUS_TITLE'));
    
    // Build status content with configuration details
    $statusContent = '';
    if ($ionosToken && $ionosTokenId) {
        $statusContent = Text::_('COM_BEARS_AICHATBOT_PANEL_STATUS_CONNECTED') . '<br><br>';
        $statusContent .= '<strong>' . Text::_('COM_BEARS_AICHATBOT_CONFIG_DETAILS') . '</strong><br>';
        $statusContent .= Text::_('COM_BEARS_AICHATBOT_TOKEN_ID') . ': ' . htmlspecialchars(substr($ionosTokenId, 0, 8) . '...', ENT_QUOTES, 'UTF-8') . '<br>';
        if ($ionosCollectionId) {
            $statusContent .= Text::_('COM_BEARS_AICHATBOT_COLLECTION_ID') . ': ' . htmlspecialchars(substr($ionosCollectionId, 0, 12) . '...', ENT_QUOTES, 'UTF-8') . '<br>';
        } else {
            $statusContent .= Text::_('COM_BEARS_AICHATBOT_COLLECTION_ID') . ': <em>Not created yet (will be auto-created)</em><br>';
        }
        $statusContent .= Text::_('COM_BEARS_AICHATBOT_MODEL') . ': ' . htmlspecialchars($ionosModel, ENT_QUOTES, 'UTF-8') . '<br>';
        $statusContent .= Text::_('COM_BEARS_AICHATBOT_ENDPOINT') . ': ' . htmlspecialchars($ionosEndpoint, ENT_QUOTES, 'UTF-8') . '<br>';
    } else {
        $statusContent = Text::_('COM_BEARS_AICHATBOT_PANEL_STATUS_NOT_CONFIGURED');
    }
    
    // Get token usage data for quick stats
    $tokenUsage = getTokenUsageData();
    $totalTokensAllTime = array_sum(array_column($tokenUsage, 'total_tokens'));
    $todayTokens = $tokenUsage['today']['total_tokens'] ?? 0;
    $weekTokens = $tokenUsage['7day']['total_tokens'] ?? 0;
    
    // Build quick stats content
    $quickStatsContent = '';
    if ($totalTokensAllTime > 0) {
        $quickStatsContent = '<strong>' . Text::_('COM_BEARS_AICHATBOT_QUICK_STATS') . '</strong><br>';
        $quickStatsContent .= Text::_('COM_BEARS_AICHATBOT_TODAY_USAGE') . ': ' . number_format($todayTokens) . ' tokens<br>';
        $quickStatsContent .= Text::_('COM_BEARS_AICHATBOT_WEEK_USAGE') . ': ' . number_format($weekTokens) . ' tokens<br>';
        $quickStatsContent .= Text::_('COM_BEARS_AICHATBOT_TOTAL_TRACKED') . ': ' . number_format($totalTokensAllTime) . ' tokens<br>';
        
        // Calculate estimated cost (using IONOS standard pricing)
        $estimatedCost = ($totalTokensAllTime / 1000) * 0.0005; // Rough estimate
        $quickStatsContent .= Text::_('COM_BEARS_AICHATBOT_ESTIMATED_COST') . ': ~$' . number_format($estimatedCost, 4);
    } else {
        $quickStatsContent = Text::_('COM_BEARS_AICHATBOT_NO_USAGE_YET');
    }
    
    // Diagnostic: Check database and IONOS for collections
    $diagnosticInfo = checkCollectionStatus($ionosToken, $ionosTokenId, $ionosEndpoint);
    
    $panels = [
        [
            'title' => Text::_('COM_BEARS_AICHATBOT_PANEL_STATUS'),
            'content' => $statusContent,
            'is_html' => true,
        ],
        [
            'title' => Text::_('COM_BEARS_AICHATBOT_QUICK_STATS'),
            'content' => $quickStatsContent,
            'is_html' => true,
        ],
    ];
    
    // Prepare variables for status template
    $title = Text::_('COM_BEARS_AICHATBOT_STATUS_TITLE');
    
    // Include status template
    require JPATH_COMPONENT_ADMINISTRATOR . '/tmpl/status/default.php';
}
