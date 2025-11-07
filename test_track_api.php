<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track API Testing - req.md Implementation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #1a1a2e;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        .content { padding: 30px; color: #e2e8f0; }
        h2 { color: #fbbf24; margin: 25px 0 15px 0; }
        .test-box {
            background: #16213e;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            border-left: 4px solid #667eea;
        }
        .pass { border-left-color: #4ade80; }
        .fail { border-left-color: #f87171; }
        .code {
            background: #0d1117;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #c9d1d9;
        }
        .result {
            background: #0f172a;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
            border: 1px solid #334155;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px 5px 10px 0;
            cursor: pointer;
            border: none;
            font-size: 16px;
        }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Track API Testing</h1>
            <p>req.md Implementation - Link Tracking by Short Code</p>
        </div>
        
        <div class="content">
            <h2>📋 req.md Requirement</h2>
            <div class="test-box">
                <strong>Original Requirement:</strong>
                <div class="code">
if ($action === 'track') {
    $short_code = $_POST['short_code'] ?? $_GET['short_code'] ?? '';
    $api_key = $_POST['api_key'] ?? $_GET['api_key'] ?? '';
    
    // Validate API key
    // Validate short code
    // Find link by short_code
    // Get username from users table
    // Return JSON response
}
                </div>
            </div>

            <h2>✅ Implementation Status</h2>
            
            <?php
            require_once __DIR__ . '/config/database.php';
            
            try {
                $pdo = getDBConnection();
                
                // Check if track_api.php exists
                if (file_exists(__DIR__ . '/api/track_api.php')) {
                    echo '<div class="test-box pass">';
                    echo '<strong>✅ Track API File Created:</strong> <code>api/track_api.php</code><br>';
                    $size = filesize(__DIR__ . '/api/track_api.php');
                    echo 'Size: ' . number_format($size) . ' bytes (' . round($size/1024, 2) . ' KB)';
                    echo '</div>';
                } else {
                    echo '<div class="test-box fail">';
                    echo '<strong>❌ Track API File Not Found</strong>';
                    echo '</div>';
                }
                
                // Check for API users
                $stmt = $pdo->query("
                    SELECT COUNT(*) as count FROM api_users WHERE api_status = 'active'
                ");
                $apiUserCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($apiUserCount > 0) {
                    echo '<div class="test-box pass">';
                    echo "<strong>✅ Active API Users Found:</strong> $apiUserCount users<br>";
                    
                    // Get a sample API key
                    $stmt = $pdo->query("
                        SELECT u.username, au.api_key 
                        FROM api_users au
                        JOIN users u ON au.user_id = u.id
                        WHERE au.api_status = 'active'
                        LIMIT 1
                    ");
                    $sampleUser = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($sampleUser) {
                        echo "Sample User: <code>{$sampleUser['username']}</code><br>";
                        echo "API Key: <code>" . substr($sampleUser['api_key'], 0, 30) . "...</code>";
                    }
                    echo '</div>';
                } else {
                    echo '<div class="test-box fail">';
                    echo '<strong>⚠️ No Active API Users</strong><br>';
                    echo 'Create an API user first to test the Track API';
                    echo '</div>';
                }
                
                // Check for links
                $stmt = $pdo->query("
                    SELECT COUNT(*) as count FROM links WHERE is_active = 1
                ");
                $linkCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($linkCount > 0) {
                    echo '<div class="test-box pass">';
                    echo "<strong>✅ Active Links Found:</strong> $linkCount links<br>";
                    
                    // Get a sample link
                    $stmt = $pdo->query("
                        SELECT l.short_code, l.custom_alias, l.title, u.username
                        FROM links l
                        JOIN users u ON l.user_id = u.id
                        WHERE l.is_active = 1
                        LIMIT 1
                    ");
                    $sampleLink = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($sampleLink) {
                        echo "Sample Link: <code>{$sampleLink['title']}</code><br>";
                        echo "Short Code: <code>{$sampleLink['short_code']}</code><br>";
                        echo "Owner: <code>{$sampleLink['username']}</code>";
                    }
                    echo '</div>';
                } else {
                    echo '<div class="test-box fail">';
                    echo '<strong>⚠️ No Active Links</strong><br>';
                    echo 'Create a link first to test the Track API';
                    echo '</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="test-box fail">';
                echo '<strong>❌ Database Error:</strong> ' . htmlspecialchars($e->getMessage());
                echo '</div>';
            }
            ?>

            <h2>🧪 Test Track API</h2>
            
            <div class="test-box">
                <strong>Method 1: GET Request</strong>
                <div class="code">
GET /api/track_api.php?action=track&short_code=ABC123&api_key=YOUR_API_KEY

# Using cURL
curl -X GET \
  "<?= defined('SITE_URL') ? SITE_URL : 'http://yoursite.com' ?>/api/track_api.php?action=track&short_code=ABC123&api_key=YOUR_KEY"
                </div>
            </div>

            <div class="test-box">
                <strong>Method 2: POST Request</strong>
                <div class="code">
POST /api/track_api.php
Content-Type: application/json

{
    "action": "track",
    "short_code": "ABC123",
    "api_key": "YOUR_API_KEY"
}

# Using cURL
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"action":"track","short_code":"ABC123","api_key":"YOUR_KEY"}' \
  "<?= defined('SITE_URL') ? SITE_URL : 'http://yoursite.com' ?>/api/track_api.php"
                </div>
            </div>

            <div class="test-box">
                <strong>Method 3: Header-based (Recommended)</strong>
                <div class="code">
GET /api/track_api.php?action=track&short_code=ABC123
X-API-Key: YOUR_API_KEY

# Using cURL
curl -X GET \
  -H "X-API-Key: YOUR_KEY" \
  "<?= defined('SITE_URL') ? SITE_URL : 'http://yoursite.com' ?>/api/track_api.php?action=track&short_code=ABC123"
                </div>
            </div>

            <h2>📤 Expected Response (Success)</h2>
            <div class="result">
                <pre style="color: #4ade80;">{
    "status": "success",
    "data": {
        "link_id": 123,
        "user_id": 5,
        "username": "john_doe",
        "email": "john@example.com",
        "short_code": "ABC123",
        "custom_alias": "my-video",
        "title": "My Video Title",
        "original_url": "https://terabox.com/...",
        "direct_video_url": "https://direct-link.com/video.mp4",
        "short_url": "<?= defined('SITE_URL') ? SITE_URL : 'http://yoursite.com' ?>/ABC123",
        "thumbnail_url": "<?= defined('SITE_URL') ? SITE_URL : 'http://yoursite.com' ?>/uploads/thumbnails/xyz.jpg",
        "views": 1500,
        "today_views": 25,
        "earnings": 12.5,
        "is_active": true,
        "created_at": "2025-11-01 10:30:00",
        "last_view_at": "2025-11-01 14:30:00"
    },
    "message": "Link tracked successfully"
}</pre>
            </div>

            <h2>❌ Expected Response (Error)</h2>
            <div class="result">
                <pre style="color: #f87171;">{
    "status": "error",
    "message": "Invalid API key"
}

{
    "status": "error",
    "message": "Missing short_code parameter"
}

{
    "status": "error",
    "message": "Invalid or inactive short_code"
}</pre>
            </div>

            <h2>📊 Additional Feature: Stats API</h2>
            <div class="test-box">
                <strong>Get Link Statistics</strong>
                <div class="code">
GET /api/track_api.php?action=stats&short_code=ABC123&api_key=YOUR_KEY

# Response:
{
    "status": "success",
    "data": {
        "short_code": "ABC123",
        "title": "My Video",
        "total_views": 1500,
        "unique_visitors": 850,
        "countries_reached": 15,
        "last_view_date": "2025-11-01",
        "earnings": 12.50,
        "top_countries": [
            {"country_code": "US", "country_name": "United States", "views": 500},
            {"country_code": "IN", "country_name": "India", "views": 350}
        ]
    }
}
                </div>
            </div>

            <?php
            // Live Test Section
            if (isset($sampleUser) && isset($sampleLink)) {
                echo '<h2>🎯 Live Test (Ready to Use)</h2>';
                echo '<div class="test-box" style="background: #064e3b; border-left-color: #10b981;">';
                echo '<strong>✅ System Ready for Testing!</strong><br><br>';
                
                $testUrl = (defined('SITE_URL') ? SITE_URL : 'http://yoursite.com') . '/api/track_api.php';
                
                echo '<strong>Test Command:</strong>';
                echo '<div class="code">';
                echo 'curl -X GET \\' . "\n";
                echo '  -H "X-API-Key: ' . $sampleUser['api_key'] . '" \\' . "\n";
                echo '  "' . $testUrl . '?action=track&short_code=' . $sampleLink['short_code'] . '"';
                echo '</div>';
                
                echo '<button class="btn" onclick="copyToClipboard(this.previousElementSibling.textContent)">📋 Copy Command</button>';
                
                echo '</div>';
            }
            ?>

            <h2>📚 Features Implemented</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                <div class="test-box pass">
                    <strong>✅ API Key Validation</strong><br>
                    <small>Validates against api_users table</small>
                </div>
                <div class="test-box pass">
                    <strong>✅ Short Code Tracking</strong><br>
                    <small>Finds links by short_code or custom_alias</small>
                </div>
                <div class="test-box pass">
                    <strong>✅ User Information</strong><br>
                    <small>Returns username and email</small>
                </div>
                <div class="test-box pass">
                    <strong>✅ Complete Link Data</strong><br>
                    <small>All link details with URLs</small>
                </div>
                <div class="test-box pass">
                    <strong>✅ Request Logging</strong><br>
                    <small>Logs all API calls to api_requests_log</small>
                </div>
                <div class="test-box pass">
                    <strong>✅ Multiple Auth Methods</strong><br>
                    <small>GET/POST params or X-API-Key header</small>
                </div>
                <div class="test-box pass">
                    <strong>✅ Stats API</strong><br>
                    <small>Bonus: Get detailed link statistics</small>
                </div>
                <div class="test-box pass">
                    <strong>✅ Error Handling</strong><br>
                    <small>Clear error messages</small>
                </div>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="api_check_dashboard.php" class="btn">🏠 Back to Dashboard</a>
                <a href="check_system.php" class="btn">🔍 Complete System Check</a>
            </div>
        </div>
    </div>

    <script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text.trim()).then(() => {
            alert('Command copied to clipboard!');
        });
    }
    </script>
</body>
</html>
