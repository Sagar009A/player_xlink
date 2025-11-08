<?php
/**
 * Terabox API Configuration Checker
 * Checks if 1024tera.com API is properly configured
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';

echo "<h1>Terabox API Configuration Check</h1>";
echo "<style>body{font-family:Arial;padding:20px}table{border-collapse:collapse;width:100%;margin:20px 0}th,td{border:1px solid #ddd;padding:12px;text-align:left}th{background:#007bff;color:white}.success{color:green}.error{color:red}.warning{color:orange}</style>";

// Check database settings
echo "<h2>1. Database Settings</h2>";
echo "<table>";
echo "<tr><th>Setting Key</th><th>Setting Value</th></tr>";

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE '%terabox%' OR setting_key LIKE '%token%' ORDER BY setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($settings)) {
        echo "<tr><td colspan='2' class='warning'>No Terabox settings found in database</td></tr>";
    } else {
        foreach ($settings as $setting) {
            $value = htmlspecialchars($setting['setting_value']);
            if (strlen($value) > 100) {
                $value = substr($value, 0, 100) . '... (truncated)';
            }
            echo "<tr><td>" . htmlspecialchars($setting['setting_key']) . "</td><td>$value</td></tr>";
        }
    }
} catch (Exception $e) {
    echo "<tr><td colspan='2' class='error'>Database error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
}
echo "</table>";

// Test 1024tera.com API vs terabox.com API
echo "<h2>2. API Endpoint Tests</h2>";

$testShortcode = "16y9PvRU-Kx5LEb83Yh6iAg"; // Sample shortcode
$token = "564037D37FF7C391108D94A9E2964894507746778E55E7175C9AFD7B0BC2F35FA6CCC152CCEDF8E3CC04099611C5771F5D34E1463FB70B52DB0DF0F41DFBE4EE";

// Try to get token from database
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'terabox_js_token'");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result && !empty($result['setting_value'])) {
        $token = trim($result['setting_value']);
        echo "<p class='success'>✓ Using database token</p>";
    } else {
        echo "<p class='warning'>⚠ No token in database, using fallback</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Database token fetch failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

$domains = [
    'www.terabox.com' => 'https://www.terabox.com/api/shorturlinfo',
    'www.terabox.app' => 'https://www.terabox.app/api/shorturlinfo',
    'www.1024tera.com' => 'https://www.1024tera.com/api/shorturlinfo',
    '1024tera.com' => 'https://1024tera.com/api/shorturlinfo'
];

echo "<table>";
echo "<tr><th>Domain</th><th>API URL</th><th>Status</th><th>Response</th></tr>";

foreach ($domains as $domain => $apiUrl) {
    $fullUrl = $apiUrl . "?" . http_build_query([
        "app_id" => "250528",
        "web" => "1",
        "channel" => "dubox",
        "clienttype" => "0",
        "jsToken" => $token,
        "dp-logid" => "35980000896792010019",
        "shorturl" => $testShortcode,
        "root" => "1",
        "scene" => ""
    ]);
    
    $headers = [
        "Host: $domain",
        "Cookie: browserid=test; csrfToken=test;",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
        "Accept: application/json, text/plain, */*",
        "Referer: https://$domain/sharing/link?surl=" . $testShortcode
    ];
    
    $ch = curl_init($fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $statusClass = 'error';
    $statusText = 'Failed';
    $responseText = '';
    
    if ($response !== false && $httpCode === 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['errno'])) {
            if ($data['errno'] === 0) {
                $statusClass = 'success';
                $statusText = "✓ HTTP $httpCode - Working!";
                $responseText = "errno: 0 (Success)";
            } else {
                $statusClass = 'warning';
                $statusText = "⚠ HTTP $httpCode";
                $responseText = "errno: {$data['errno']}, msg: " . ($data['errmsg'] ?? 'unknown');
            }
        } else {
            $statusClass = 'warning';
            $statusText = "⚠ HTTP $httpCode";
            $responseText = "Invalid JSON response";
        }
    } else {
        $responseText = $curlError ? "cURL Error: $curlError" : "HTTP $httpCode";
    }
    
    echo "<tr>";
    echo "<td><strong>$domain</strong></td>";
    echo "<td>" . htmlspecialchars(substr($apiUrl, 0, 50)) . "...</td>";
    echo "<td class='$statusClass'>$statusText</td>";
    echo "<td>" . htmlspecialchars($responseText) . "</td>";
    echo "</tr>";
}

echo "</table>";

// Check extractor configuration
echo "<h2>3. Extractor Configuration</h2>";
$extractorPath = __DIR__ . '/extractors/TeraboxExtractor.php';
if (file_exists($extractorPath)) {
    $content = file_get_contents($extractorPath);
    
    // Check API URL
    if (preg_match('/https:\/\/www\.terabox\.(com|app)\/api\/shorturlinfo/', $content, $matches)) {
        echo "<p class='warning'>⚠ Extractor is using: <strong>www.terabox.{$matches[1]}</strong> for API calls</p>";
    }
    
    // Check Host header
    if (preg_match('/"Host: www\.terabox\.(com|app)"/', $content, $matches)) {
        echo "<p class='warning'>⚠ Extractor has hardcoded Host header: <strong>www.terabox.{$matches[1]}</strong></p>";
    }
    
    echo "<p class='info'>📝 Note: The extractor accepts 1024tera.com URLs but makes API calls to terabox.com/terabox.app</p>";
} else {
    echo "<p class='error'>✗ TeraboxExtractor.php not found</p>";
}

echo "<h2>4. Recommendations</h2>";
echo "<div style='background:#f8f9fa;padding:15px;border-left:4px solid #007bff'>";
echo "<p><strong>Current Issue:</strong> The system accepts 1024tera.com URLs but the API calls are hardcoded to use terabox.com/terabox.app endpoints.</p>";
echo "<p><strong>Solution Options:</strong></p>";
echo "<ol>";
echo "<li><strong>Dynamic Host Detection:</strong> Detect the domain from the input URL and use the same domain for API calls</li>";
echo "<li><strong>Test 1024tera.com API:</strong> Check if 1024tera.com has its own API endpoint that works differently</li>";
echo "<li><strong>Use terabox.app as universal:</strong> If all domains share the same backend, ensure terabox.app API works for all</li>";
echo "</ol>";
echo "</div>";

?>
