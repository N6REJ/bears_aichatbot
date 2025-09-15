<?php
/**
 * Bears AI Chatbot - API Helper Functions
 *
 * @version 2025.09.15.8
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

/**
 * Get the appropriate API base URL for document collections
 * based on the configured chat completions endpoint
 * 
 * @param string $endpoint The configured chat completions endpoint
 * @return string The base URL for document collections API
 */
function getDocumentCollectionsApiBase(string $endpoint): string
{
    // Check if this is an IONOS inference endpoint
    if (strpos($endpoint, 'inference') !== false && strpos($endpoint, 'ionos.com') !== false) {
        // For IONOS inference endpoints, use the Model Hub API
        // According to API docs: https://api.ionos.com/docs/inference-modelhub/v1/
        return 'https://api.ionos.com/inference-modelhub/v1';
    }
    
    // For other endpoints, try to extract base URL
    $apiBase = $endpoint;
    
    // Remove common chat completions paths
    $pathsToRemove = [
        '/v1/chat/completions',
        '/chat/completions',
        '/v1/completions',
        '/completions'
    ];
    
    foreach ($pathsToRemove as $path) {
        if (strpos($apiBase, $path) !== false) {
            $apiBase = str_replace($path, '', $apiBase);
            break;
        }
    }
    
    // Ensure we have a valid URL
    if (empty($apiBase) || strpos($apiBase, 'http') !== 0) {
        // Default to IONOS Model Hub API
        return 'https://api.ionos.com/inference-modelhub/v1';
    }
    
    // Clean up any trailing slashes
    $apiBase = rtrim($apiBase, '/');
    
    // For generic inference endpoints, append /v1 if not present
    if (strpos($apiBase, '/v1') === false && strpos($apiBase, 'inference') !== false) {
        $apiBase .= '/v1';
    }
    
    return $apiBase;
}

/**
 * Get the collections endpoint URL based on the API base
 * 
 * @param string $apiBase The API base URL
 * @return string The collections endpoint URL
 */
function getCollectionsEndpoint(string $apiBase): string
{
    // For IONOS Model Hub API
    if (strpos($apiBase, 'api.ionos.com/inference-modelhub') !== false) {
        return $apiBase . '/document-collections';
    }
    
    // For legacy inference endpoints
    if (strpos($apiBase, 'inference.de-txl.ionos.com') !== false) {
        return str_replace('/v1', '', $apiBase) . '/collections';
    }
    
    // Default pattern
    return $apiBase . '/collections';
}

/**
 * Get the delete collection endpoint URL
 * 
 * @param string $apiBase The API base URL
 * @param string $collectionId The collection ID to delete
 * @return string The delete endpoint URL
 */
function getDeleteCollectionEndpoint(string $apiBase, string $collectionId): string
{
    $collectionsEndpoint = getCollectionsEndpoint($apiBase);
    return $collectionsEndpoint . '/' . rawurlencode($collectionId);
}
