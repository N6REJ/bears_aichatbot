<?php
/**
 * Test script to find the correct IONOS API endpoint for document collections
 * Run this from the command line or browser to test different endpoints
 */

// Configuration - replace with your actual values
$token = 'eyJ0eXAiOiJKV1QiLCJraWQiOiI3MzY3ZmY3OS0wMWFjLTRiYTItYTRlZS1iNTA3ZGIwNTY1NjEiLCJhbGciOiJSUzI1NiJ9.eyJpc3MiOiJpb25vc2Nsb3VkIiwiaWF0IjoxNzU2OTg3NTUyLCJjbGllbnQiOiJVU0VSIiwiaWRlbnRpdHkiOnsicm9sZSI6InVzZXIiLCJyZWdEb21haW4iOiJpb25vcy5jb20iLCJyZXNlbGxlcklkIjoxLCJ1dWlkIjoiYWE4NTFmNWYtNWMyMC00M2Q4LTk4ZDItOTkzM2FhNjY1M2E1IiwicHJpdmlsZWdlcyI6WyJBQ0NFU1NfQU5EX01BTkFHRV9JQU1fUkVTT1VSQ0VTIiwiQUNDRVNTX0FORF9NQU5BR0VfQUlfTU9ERUxfSFVCIl0sImlzUGFyZW50IjpmYWxzZSwiY29udHJhY3ROdW1iZXIiOjM2MjM3ODExfSwiZXhwIjoxNzg4NTIzNTUyfQ.LUEQAF3NypXiJitmzHVdhxBu7qWl0T9p94YduJew2yu8zPDhf03-5SOIL7s8lDFHydbTMubeoLbjRlrOP6Hjyt8eJgV1A86nE_v7hPzq1MZqBIniTjapYVkEv_DmxWKjWgMxEdwXysC9t9zpuxtN5nq2PXMXOLGkb8uDH5O9Ex7ggrzXENxJPYqhF1WE6emn_oabKNPQaoV4bS9tArSnF6PGON33SDJawRJHv-dH7N0ZFzgD1RS_Cxnu1JIwko9SRmtIToRmATPDzZUvIsYX7K-1UG1YPytJFKL8UAWmN1qvnvgDbVcW8v4Ct46ZDYVglvRx-35IIDWzebX7QzS89w';  // Replace with your actual token
$tokenId = '7367ff79-01ac-4ba2-a4ee-b507db056561';  // Replace with your actual token ID

// Test endpoints for document collections
$documentEndpoints = [
    'Cloud API v6 (AI Model Hub)' => 'https://api.ionos.com/cloudapi/v6/ai/modelhub/document-collections',
    'Inference Model Hub v1' => 'https://api.ionos.com/inference-modelhub/v1/document-collections',
    'Cloud API v6 (Direct)' => 'https://api.ionos.com/cloudapi/v6/document-collections',
    'Inference Direct' => 'https://inference.de-txl.ionos.com/v1/document-collections',
    'AI API v1' => 'https://api.ionos.com/ai/v1/document-collections',
    'Model Hub API' => 'https://api.ionos.com/modelhub/v1/document-collections',
    'Cloud API v6 (Collections)' => 'https://api.ionos.com/cloudapi/v6/collections',
    'AI Model Hub (Alt)' => 'https://api.ionos.com/cloudapi/v6/ai/model-hub/document-collections',
    'Inference API (Alt)' => 'https://inference.de-txl.ionos.com/v1/collections',
    'Model Hub Direct' => 'https://modelhub.de-txl.ionos.com/v1/document-collections'
];

// Test basic API connectivity first
$basicEndpoints = [
    'Chat Completions (OpenAI)' => 'https://openai.inference.de-txl.ionos.com/v1/models',
    'Chat Completions (Inference)' => 'https://inference.de-txl.ionos.com/v1/models',
    'Cloud API Root' => 'https://api.ionos.com/cloudapi/v6',
    'AI Model Hub Root' => 'https://api.ionos.com/cloudapi/v6/ai/modelhub'
];

function testEndpoint($url, $token, $tokenId) {
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    
    if ($tokenId) {
        $headers[] = 'X-IONOS-Token-Id: ' . $tokenId;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'response' => $response,
        'error' => $error
    ];
}

echo "<h1>IONOS API Endpoint Testing</h1>\n";
echo "<p>Testing different endpoints to find the correct one for document collections...</p>\n";

if ($token === 'YOUR_TOKEN_HERE' || $tokenId === 'YOUR_TOKEN_ID_HERE') {
    echo "<p style='color: red;'><strong>ERROR:</strong> Please update the token and tokenId variables in this script with your actual IONOS credentials.</p>\n";
    exit;
}

// Test basic connectivity first
echo "<h2>Basic API Connectivity Test</h2>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>Endpoint Name</th><th>URL</th><th>HTTP Code</th><th>Response</th></tr>\n";

foreach ($basicEndpoints as $name => $url) {
    echo "<tr>\n";
    echo "<td>" . htmlspecialchars($name) . "</td>\n";
    echo "<td>" . htmlspecialchars($url) . "</td>\n";
    
    $result = testEndpoint($url, $token, $tokenId);
    
    $statusColor = 'red';
    if ($result['code'] >= 200 && $result['code'] < 300) {
        $statusColor = 'green';
    } elseif ($result['code'] >= 400 && $result['code'] < 500) {
        $statusColor = 'orange';
    }
    
    echo "<td style='color: {$statusColor};'>" . $result['code'] . "</td>\n";
    
    $responseText = $result['response'];
    if ($result['error']) {
        $responseText = "CURL Error: " . $result['error'];
    } elseif ($responseText) {
        // Pretty print JSON if possible
        $json = json_decode($responseText, true);
        if ($json) {
            $responseText = json_encode($json, JSON_PRETTY_PRINT);
        }
        // Truncate long responses
        if (strlen($responseText) > 500) {
            $responseText = substr($responseText, 0, 500) . "...";
        }
    }
    
    echo "<td><pre>" . htmlspecialchars($responseText) . "</pre></td>\n";
    echo "</tr>\n";
}

echo "</table>\n";

// Test document collection endpoints
echo "<h2>Document Collection Endpoints Test</h2>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>Endpoint Name</th><th>URL</th><th>HTTP Code</th><th>Response</th></tr>\n";

foreach ($documentEndpoints as $name => $url) {
    echo "<tr>\n";
    echo "<td>" . htmlspecialchars($name) . "</td>\n";
    echo "<td>" . htmlspecialchars($url) . "</td>\n";
    
    $result = testEndpoint($url, $token, $tokenId);
    
    $statusColor = 'red';
    if ($result['code'] >= 200 && $result['code'] < 300) {
        $statusColor = 'green';
    } elseif ($result['code'] >= 400 && $result['code'] < 500) {
        $statusColor = 'orange';
    }
    
    echo "<td style='color: {$statusColor};'>" . $result['code'] . "</td>\n";
    
    $responseText = $result['response'];
    if ($result['error']) {
        $responseText = "CURL Error: " . $result['error'];
    } elseif ($responseText) {
        // Pretty print JSON if possible
        $json = json_decode($responseText, true);
        if ($json) {
            $responseText = json_encode($json, JSON_PRETTY_PRINT);
        }
        // Truncate long responses
        if (strlen($responseText) > 500) {
            $responseText = substr($responseText, 0, 500) . "...";
        }
    }
    
    echo "<td><pre>" . htmlspecialchars($responseText) . "</pre></td>\n";
    echo "</tr>\n";
}

echo "</table>\n";

echo "<h2>Instructions:</h2>\n";
echo "<ol>\n";
echo "<li>Look for endpoints that return HTTP 200 (green) - these are working</li>\n";
echo "<li>HTTP 401 (orange) means authentication issue but endpoint exists</li>\n";
echo "<li>HTTP 404 (red) means endpoint doesn't exist</li>\n";
echo "<li>HTTP 403 (orange) means endpoint exists but access denied</li>\n";
echo "</ol>\n";

echo "<h2>Next Steps:</h2>\n";
echo "<p>Once you find a working endpoint (HTTP 200), update the Bears AI Chatbot code to use that endpoint.</p>\n";
?>
