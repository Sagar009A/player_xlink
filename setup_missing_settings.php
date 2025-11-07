<?php
/**
 * Fix Missing Settings in Database
 * Run this file once to add missing settings
 */

require_once __DIR__ . '/config/database.php';

echo "Setting up missing settings...\n\n";

// Default settings to add
$defaultSettings = [
    'cpm_rate' => '0.50',  // $0.50 per 1000 views
    'currency_code' => 'USD',
    'admin_email' => 'admin@teraboxurll.in',
    'min_withdrawal' => '5.00',
    'site_name' => 'LinkStreamX',
    'auto_fetch_thumbnail' => '1',
    'default_redirect_delay' => '10',
    'enable_registration' => '1',
    'enable_email_verification' => '0',
    'ads_enabled' => '1',
    'maintenance_mode' => '0'
];

$added = 0;
$updated = 0;
$skipped = 0;

foreach ($defaultSettings as $key => $value) {
    try {
        // Check if setting exists
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            echo "? Setting '$key' already exists with value: {$existing['setting_value']}\n";
            $skipped++;
        } else {
            // Insert new setting
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
            echo "? Added setting '$key' = '$value'\n";
            $added++;
        }
    } catch (Exception $e) {
        echo "? Error processing setting '$key': " . $e->getMessage() . "\n";
    }
}

echo "\n";
echo "==========================================\n";
echo "Summary:\n";
echo "==========================================\n";
echo "? Added: $added\n";
echo "? Skipped (already exists): $skipped\n";
echo "Total settings: " . ($added + $skipped) . "\n";
echo "\n? Setup complete!\n";
echo "\nYou can now delete this file if you want.\n";
?>