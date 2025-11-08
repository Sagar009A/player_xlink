<?php
/**
 * Setup Terabox API Settings
 * Initializes the new Terabox-specific settings in database
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

echo "<h1>Terabox Settings Setup</h1>";
echo "<style>body{font-family:Arial;padding:20px}.success{color:green}.error{color:red}.info{color:blue}</style>";

// Check if settings table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
    if ($stmt->rowCount() == 0) {
        echo "<p class='error'>❌ Settings table does not exist!</p>";
        exit;
    }
    echo "<p class='success'>✓ Settings table found</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Settings to initialize
$defaultSettings = [
    'terabox_use_dynamic_domain' => [
        'value' => '1',
        'description' => 'Enable dynamic domain detection for TeraBox URLs'
    ],
    'terabox_api_domain' => [
        'value' => 'www.terabox.app',
        'description' => 'Default/fallback TeraBox API domain'
    ]
];

echo "<h2>Initializing Settings</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;width:100%'>";
echo "<tr><th>Setting Key</th><th>Value</th><th>Status</th></tr>";

foreach ($defaultSettings as $key => $config) {
    try {
        // Check if setting already exists
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            echo "<tr>";
            echo "<td><strong>{$key}</strong><br><small>{$config['description']}</small></td>";
            echo "<td>" . htmlspecialchars($existing['setting_value']) . "</td>";
            echo "<td class='info'>ℹ Already exists</td>";
            echo "</tr>";
        } else {
            // Insert new setting
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$key, $config['value']]);
            
            echo "<tr>";
            echo "<td><strong>{$key}</strong><br><small>{$config['description']}</small></td>";
            echo "<td>" . htmlspecialchars($config['value']) . "</td>";
            echo "<td class='success'>✓ Created</td>";
            echo "</tr>";
        }
    } catch (Exception $e) {
        echo "<tr>";
        echo "<td><strong>{$key}</strong></td>";
        echo "<td>-</td>";
        echo "<td class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</td>";
        echo "</tr>";
    }
}

echo "</table>";

// Check terabox_js_token
echo "<h2>TeraBox JS Token Status</h2>";
try {
    $stmt = $pdo->prepare("SELECT setting_value, updated_at FROM settings WHERE setting_key = 'terabox_js_token'");
    $stmt->execute();
    $token = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($token && !empty($token['setting_value'])) {
        $tokenValue = $token['setting_value'];
        $updatedAt = $token['updated_at'] ?? 'Unknown';
        echo "<p class='success'>✓ Token exists in database</p>";
        echo "<p><strong>Token:</strong> " . substr($tokenValue, 0, 30) . "... (length: " . strlen($tokenValue) . ")</p>";
        echo "<p><strong>Last Updated:</strong> " . htmlspecialchars($updatedAt) . "</p>";
        
        // Check if token is valid (basic length check)
        if (strlen($tokenValue) > 50) {
            echo "<p class='success'>✓ Token appears to be valid format</p>";
        } else {
            echo "<p class='error'>⚠ Token may be too short</p>";
        }
    } else {
        echo "<p class='info'>ℹ No token in database (will use auto-fetch)</p>";
        echo "<p>Token will be automatically fetched from external source when needed.</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error checking token: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Summary
echo "<h2>Setup Complete</h2>";
echo "<div style='background:#d4edda;padding:15px;border-left:4px solid green;margin:20px 0'>";
echo "<p><strong>✓ Setup completed successfully!</strong></p>";
echo "<p>The following settings have been initialized:</p>";
echo "<ul>";
echo "<li><strong>terabox_use_dynamic_domain:</strong> Enabled by default</li>";
echo "<li><strong>terabox_api_domain:</strong> Set to www.terabox.app (fallback)</li>";
echo "</ul>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Go to Admin Panel → Settings to configure Terabox API</li>";
echo "<li>Test with 1024tera.com URLs using <a href='test_1024tera_fix.php'>test_1024tera_fix.php</a></li>";
echo "<li>Check API diagnostics with <a href='check_terabox_api.php'>check_terabox_api.php</a></li>";
echo "</ol>";
echo "</div>";

echo "<h3>All Current Terabox Settings</h3>";
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE '%terabox%' ORDER BY setting_key");
    $allSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($allSettings)) {
        echo "<p class='info'>No Terabox settings found in database</p>";
    } else {
        echo "<table border='1' cellpadding='10' style='border-collapse:collapse;width:100%'>";
        echo "<tr><th>Setting Key</th><th>Setting Value</th></tr>";
        foreach ($allSettings as $setting) {
            $value = $setting['setting_value'];
            if (strlen($value) > 100) {
                $value = substr($value, 0, 100) . '... (truncated)';
            }
            echo "<tr>";
            echo "<td>" . htmlspecialchars($setting['setting_key']) . "</td>";
            echo "<td>" . htmlspecialchars($value) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

?>
