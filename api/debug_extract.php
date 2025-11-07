<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>API Extract Debug</h2>";

// Test 1: Check file exists
echo "<h3>File Check:</h3>";
if (file_exists('extract.php')) {
    echo "✓ extract.php exists<br>";
} else {
    echo "✗ extract.php NOT FOUND<br>";
}

// Test 2: Check required files
echo "<h3>Required Files:</h3>";
$files = [
    '../services/ExtractorManager.php',
    '../services/CacheService.php',
    '../config/extractors.php',
    '../extractors/AbstractExtractor.php',
    '../extractors/TeraboxExtractor.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ $file<br>";
    } else {
        echo "✗ $file MISSING<br>";
    }
}

// Test 3: Try to load ExtractorManager
echo "<h3>Loading ExtractorManager:</h3>";
try {
    require_once '../services/ExtractorManager.php';
    echo "✓ ExtractorManager loaded successfully<br>";
    
    $manager = new ExtractorManager();
    echo "✓ ExtractorManager instantiated<br>";
    
    $platforms = $manager->getSupportedPlatforms();
    echo "✓ Supported platforms: " . count($platforms) . "<br>";
    
} catch (Error $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "<br>";
}

// Test 4: Try actual extraction
echo "<h3>Test Extraction:</h3>";
echo '<a href="extract.php?info=true">Click to test extract.php?info=true</a>';