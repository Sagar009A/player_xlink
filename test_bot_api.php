<?php
/**
 * Test Bot API - Shorten URL
 * This script tests if the shorten.php API is working correctly
 */

echo "=== Testing Bot Shorten API ===\n\n";

// Configuration
$apiUrl = 'https://teraboxurll.in/api/shorten.php';
$apiKey = '1bb552c628a975b768fa83b78348af17065aeb5cc74bac53539eff50215bde50';
$testUrl = 'https://1024terabox.com/s/16y9PvRU-Kx5LEb83Yh6iAg';

echo "API URL: $apiUrl\n";
echo "Test URL: $testUrl\n";
echo "API Key: " . substr($apiKey, 0, 20) . "...\n\n";

// Make the API call
echo "Making API request...\n";

$postData = [
    'api_key' => $apiKey,
    'url' => $testUrl
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";

if ($error) {
    echo "CURL Error: $error\n";
    exit(1);
}

echo "\nResponse:\n";
echo str_repeat('-', 50) . "\n";
echo $response . "\n";
echo str_repeat('-', 50) . "\n\n";

// Parse response
$result = json_decode($response, true);

if (!$result) {
    echo "❌ Failed to parse JSON response\n";
    exit(1);
}

if (isset($result['success']) && $result['success']) {
    echo "✓ Success!\n";
    echo "Short URL: " . ($result['short_url'] ?? $result['shortUrl'] ?? 'Not found') . "\n";
    
    if (isset($result['data'])) {
        echo "\nData:\n";
        foreach ($result['data'] as $key => $value) {
            if (!is_array($value) && !is_object($value)) {
                echo "  $key: $value\n";
            }
        }
    }
} else {
    echo "❌ API Error\n";
    echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n";
    if (isset($result['message'])) {
        echo "Message: " . $result['message'] . "\n";
    }
}

echo "\n=== Test Complete ===\n";
