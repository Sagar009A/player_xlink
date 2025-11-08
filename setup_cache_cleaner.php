<?php
/**
 * Setup Script for Auto Cache Cleaner
 * 
 * This script sets up the auto cache cleaner cron job
 * Run this once: php setup_cache_cleaner.php
 */

echo "=================================================\n";
echo "Auto Cache Cleaner Setup\n";
echo "=================================================\n\n";

// Get the absolute path to the cache cleaner script
$scriptPath = realpath(__DIR__ . '/cron/auto_cache_cleaner.php');

if (!$scriptPath) {
    echo "❌ Error: Cache cleaner script not found!\n";
    exit(1);
}

echo "✓ Found cache cleaner script at: {$scriptPath}\n\n";

// Create logs directory if it doesn't exist
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
    echo "✓ Created logs directory\n";
}

// Create cache directory if it doesn't exist
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
    echo "✓ Created cache directory\n";
}

// Test run the cache cleaner
echo "\n📋 Testing cache cleaner...\n";
echo "---\n";
$output = [];
$returnVar = 0;
exec("php " . escapeshellarg($scriptPath) . " 2>&1", $output, $returnVar);

foreach ($output as $line) {
    echo $line . "\n";
}

if ($returnVar === 0) {
    echo "\n✓ Cache cleaner test completed successfully!\n";
} else {
    echo "\n⚠️ Warning: Cache cleaner test returned non-zero exit code: {$returnVar}\n";
}

// Display cron job information
echo "\n=================================================\n";
echo "Cron Job Setup Instructions\n";
echo "=================================================\n\n";

echo "Add the following cron job to run the cache cleaner every 6 hours:\n\n";
echo "0 */6 * * * cd " . escapeshellarg(__DIR__) . " && php " . escapeshellarg($scriptPath) . " >> " . escapeshellarg($logsDir . '/cache_cleaner_cron.log') . " 2>&1\n\n";

echo "To edit your crontab, run:\n";
echo "  crontab -e\n\n";

echo "Or use the manage.sh script:\n";
echo "  bash manage.sh\n\n";

echo "Alternative schedules:\n";
echo "  • Every hour:    0 * * * *\n";
echo "  • Every 3 hours: 0 */3 * * *\n";
echo "  • Every 12 hours: 0 */12 * * *\n";
echo "  • Daily at 2 AM: 0 2 * * *\n\n";

// Provide manage.sh update option
echo "=================================================\n";
echo "Quick Setup with manage.sh\n";
echo "=================================================\n\n";

$manageScript = __DIR__ . '/manage.sh';
if (file_exists($manageScript)) {
    echo "You can add the cache cleaner to your existing manage.sh script.\n";
    echo "Would you like to see the code to add? (It's already created)\n\n";
} else {
    echo "Creating manage.sh script for easy cron management...\n";
    
    $manageContent = <<<'BASH'
#!/bin/bash

# LinkStreamX Cache Cleaner Management Script

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CACHE_CLEANER="$SCRIPT_DIR/cron/auto_cache_cleaner.php"

case "$1" in
    setup)
        echo "Setting up cache cleaner cron job..."
        
        # Check if cron job already exists
        if crontab -l 2>/dev/null | grep -q "auto_cache_cleaner.php"; then
            echo "Cache cleaner cron job already exists!"
            exit 0
        fi
        
        # Add cron job
        (crontab -l 2>/dev/null; echo "0 */6 * * * cd $SCRIPT_DIR && php $CACHE_CLEANER >> $SCRIPT_DIR/logs/cache_cleaner_cron.log 2>&1") | crontab -
        
        echo "✓ Cache cleaner cron job added successfully!"
        echo "  Schedule: Every 6 hours"
        ;;
        
    remove)
        echo "Removing cache cleaner cron job..."
        crontab -l 2>/dev/null | grep -v "auto_cache_cleaner.php" | crontab -
        echo "✓ Cache cleaner cron job removed"
        ;;
        
    test)
        echo "Running cache cleaner test..."
        php "$CACHE_CLEANER"
        ;;
        
    logs)
        echo "Showing recent cache cleaner logs..."
        tail -n 50 "$SCRIPT_DIR/logs/cache_cleaner.log"
        ;;
        
    status)
        echo "Checking cache cleaner status..."
        if crontab -l 2>/dev/null | grep -q "auto_cache_cleaner.php"; then
            echo "✓ Cache cleaner cron job is installed"
            echo ""
            echo "Current cron job:"
            crontab -l 2>/dev/null | grep "auto_cache_cleaner.php"
        else
            echo "✗ Cache cleaner cron job is not installed"
            echo "Run: $0 setup"
        fi
        ;;
        
    *)
        echo "LinkStreamX Cache Cleaner Management"
        echo ""
        echo "Usage: $0 {setup|remove|test|logs|status}"
        echo ""
        echo "Commands:"
        echo "  setup   - Add cache cleaner to cron"
        echo "  remove  - Remove cache cleaner from cron"
        echo "  test    - Run cache cleaner manually"
        echo "  logs    - View recent logs"
        echo "  status  - Check if cron job is installed"
        exit 1
        ;;
esac
BASH;

    file_put_contents($manageScript, $manageContent);
    chmod($manageScript, 0755);
    
    echo "✓ Created manage.sh script\n\n";
    echo "You can now use:\n";
    echo "  ./manage.sh setup   - Install cache cleaner cron job\n";
    echo "  ./manage.sh test    - Test cache cleaner\n";
    echo "  ./manage.sh status  - Check status\n";
    echo "  ./manage.sh logs    - View logs\n";
}

echo "\n=================================================\n";
echo "Setup Complete!\n";
echo "=================================================\n\n";

echo "Cache cleaner is ready to use. To enable automatic cleaning:\n";
echo "1. Run: ./manage.sh setup\n";
echo "2. Or manually add the cron job shown above\n\n";

echo "For immediate cleanup, run:\n";
echo "  php {$scriptPath}\n\n";
