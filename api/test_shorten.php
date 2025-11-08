<?php
/**
 * Test Shorten API - Direct Test
 * Access this via: https://teraboxurll.in/api/test_shorten.php
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Shorten API</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .result { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; }
        pre { overflow-x: auto; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>🧪 Test Shorten API</h1>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $apiKey = $_POST['api_key'] ?? '';
        $url = $_POST['url'] ?? '';
        
        echo '<h2>Test Results:</h2>';
        
        if (!$apiKey || !$url) {
            echo '<div class="result error"><strong>Error:</strong> Please provide both API key and URL</div>';
        } else {
            // Make internal API call
            $postData = [
                'api_key' => $apiKey,
                'url' => $url
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://teraboxurll.in/api/shorten.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            echo '<div class="result">';
            echo '<strong>HTTP Status:</strong> ' . htmlspecialchars($httpCode) . '<br>';
            
            if ($error) {
                echo '<strong>CURL Error:</strong> ' . htmlspecialchars($error);
            } else {
                $result = json_decode($response, true);
                
                if ($result && isset($result['success']) && $result['success']) {
                    echo '</div><div class="result success">';
                    echo '<strong>✓ Success!</strong><br>';
                    echo '<strong>Short URL:</strong> <a href="' . htmlspecialchars($result['short_url'] ?? $result['shortUrl'] ?? '') . '" target="_blank">' . htmlspecialchars($result['short_url'] ?? $result['shortUrl'] ?? 'N/A') . '</a><br>';
                    
                    if (isset($result['data'])) {
                        echo '<br><strong>Details:</strong><br>';
                        echo '<ul>';
                        foreach ($result['data'] as $key => $value) {
                            if (!is_array($value) && !is_object($value)) {
                                echo '<li>' . htmlspecialchars($key) . ': ' . htmlspecialchars($value) . '</li>';
                            }
                        }
                        echo '</ul>';
                    }
                } else {
                    echo '</div><div class="result error">';
                    echo '<strong>❌ API Error</strong><br>';
                    echo '<strong>Error:</strong> ' . htmlspecialchars($result['error'] ?? 'Unknown error') . '<br>';
                    if (isset($result['message'])) {
                        echo '<strong>Message:</strong> ' . htmlspecialchars($result['message']);
                    }
                }
                
                echo '<br><br><strong>Full Response:</strong><br>';
                echo '<pre>' . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) . '</pre>';
            }
            echo '</div>';
        }
    }
    ?>
    
    <form method="POST">
        <h2>Test Form:</h2>
        <p>
            <label><strong>API Key:</strong></label><br>
            <input type="text" name="api_key" value="1bb552c628a975b768fa83b78348af17065aeb5cc74bac53539eff50215bde50" style="width: 100%; padding: 8px;">
        </p>
        <p>
            <label><strong>URL to Shorten:</strong></label><br>
            <input type="text" name="url" value="https://1024terabox.com/s/16y9PvRU-Kx5LEb83Yh6iAg" style="width: 100%; padding: 8px;">
        </p>
        <button type="submit">🚀 Test API</button>
    </form>
    
    <hr>
    <h3>Quick Database Check:</h3>
    <div class="result">
        <?php
        require_once __DIR__ . '/../config/database.php';
        
        try {
            $stmt = $pdo->prepare("SELECT id, username, email, status FROM users WHERE api_key = ? LIMIT 1");
            $stmt->execute(['1bb552c628a975b768fa83b78348af17065aeb5cc74bac53539eff50215bde50']);
            $user = $stmt->fetch();
            
            if ($user) {
                echo '✓ User found in database:<br>';
                echo '<ul>';
                echo '<li>ID: ' . htmlspecialchars($user['id']) . '</li>';
                echo '<li>Username: ' . htmlspecialchars($user['username']) . '</li>';
                echo '<li>Email: ' . htmlspecialchars($user['email']) . '</li>';
                echo '<li>Status: ' . htmlspecialchars($user['status']) . '</li>';
                echo '</ul>';
            } else {
                echo '❌ User with this API key not found or not approved';
            }
        } catch (Exception $e) {
            echo '❌ Database Error: ' . htmlspecialchars($e->getMessage());
        }
        ?>
    </div>
    
    <hr>
    <p><small>This is a diagnostic tool. In production, remove this file or restrict access.</small></p>
</body>
</html>
