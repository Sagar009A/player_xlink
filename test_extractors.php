<?php
/**
 * Test Script for All Video Extractors
 * Tests all provided links and shows extraction results
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set UTF-8 encoding for proper emoji display
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/extractors.php';

// Load AbstractExtractor FIRST
if (file_exists(__DIR__ . '/extractors/AbstractExtractor.php')) {
    require_once __DIR__ . '/extractors/AbstractExtractor.php';
}

require_once __DIR__ . '/services/ExtractorManager.php';

// Check if running from CLI or browser
$isCli = php_sapi_name() === 'cli';

// Function to output with proper formatting
function output($text, $isCli = false) {
    if ($isCli) {
        echo $text . "\n";
    } else {
        echo nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . "<br>";
    }
}

if (!$isCli) {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Extractor Test Suite</title>
    <style>
        body { font-family: "Courier New", monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #569cd6; }
        .header { color: #dcdcaa; font-weight: bold; font-size: 18px; }
        .section { margin: 20px 0; padding: 15px; background: #252526; border-left: 3px solid #007acc; }
        pre { background: #1e1e1e; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>';
}

output("==========================================", $isCli);
output("    VIDEO EXTRACTOR TEST SUITE", $isCli);
output("==========================================", $isCli);
output("", $isCli);

$testLinks = [
    'Terabox' => [
        'https://1024terabox.com/s/1yQMI_F-5EAPsJTw87VHlBA',
        'https://terasharefile.com/s/185WWXZWZMRuWXJ16k7KQ5Q',
        'https://teraboxurl.com/s/1rp-mcOxzM3PnCVk9q_D7VA'
    ],
    'Diskwala' => [
        'https://www.diskwala.com/app/68ef8b57fa94e97eb28d1e9c'
    ],
    'Streaam.net' => [
        'https://streaam.net/T/OddydUbFa$clz5kgpi1'
    ],
    'NowPlayToc' => [
        'https://nowplaytoc.com/1984895971020115969'
    ],
    'VividCast' => [
        'https://www.vividcastydca.com/#/?linkId=1984866717981085697'
    ]
];

$manager = new ExtractorManager();

foreach ($testLinks as $platform => $links) {
    if (!$isCli) echo '<div class="section">';
    output("", $isCli);
    output("========================================", $isCli);
    output("Testing: $platform", $isCli);
    output("========================================", $isCli);
    
    foreach ($links as $index => $url) {
        $linkNum = $index + 1;
        output("", $isCli);
        output("[$platform - Link $linkNum]", $isCli);
        output("URL: $url", $isCli);
        output(str_repeat("-", 60), $isCli);
        
        try {
            $result = $manager->extract($url, ['skip_cache' => true]);
            
            if ($result['success']) {
                if ($isCli) {
                    echo "\033[32m? SUCCESS!\033[0m\n";
                } else {
                    echo '<span class="success">? SUCCESS!</span><br>';
                }
                $data = $result['data'];
                
                output("  ? Title: " . ($data['title'] ?? $data['filename'] ?? 'N/A'), $isCli);
                output("  ? Direct Link: " . substr($data['direct_link'] ?? 'N/A', 0, 80) . "...", $isCli);
                output("  ? Thumbnail: " . ($data['thumbnail'] ? 'Yes' : 'No'), $isCli);
                output("  ? Size: " . ($data['size_formatted'] ?? $data['size'] ?? 'N/A'), $isCli);
                output("  ? Quality: " . ($data['quality'] ?? 'N/A'), $isCli);
                output("  ? Format: " . ($data['format'] ?? 'N/A'), $isCli);
                
                if (isset($data['expires_at_formatted'])) {
                    output("  ? Expires At: " . $data['expires_at_formatted'], $isCli);
                } elseif (isset($data['expires_in'])) {
                    output("  ? Expires In: " . gmdate('H:i:s', $data['expires_in']), $isCli);
                } else {
                    output("  ? Expires: Never", $isCli);
                }
                
            } else {
                if ($isCli) {
                    echo "\033[31m? FAILED!\033[0m\n";
                } else {
                    echo '<span class="error">? FAILED!</span><br>';
                }
                output("  ? Error: " . ($result['error'] ?? 'unknown'), $isCli);
                output("  ? Message: " . ($result['message'] ?? 'No message'), $isCli);
                
                if (isset($result['error_info'])) {
                    output("  ? Info: " . $result['error_info']['description'], $isCli);
                }
            }
            
        } catch (Exception $e) {
            if ($isCli) {
                echo "\033[31m? EXCEPTION!\033[0m\n";
            } else {
                echo '<span class="error">? EXCEPTION!</span><br>';
            }
            output("  ? " . $e->getMessage(), $isCli);
        }
        
        output("", $isCli);
    }
    if (!$isCli) echo '</div>';
}

if (!$isCli) echo '<div class="section">';
output("", $isCli);
output("==========================================", $isCli);
output("    SUPPORTED PLATFORMS", $isCli);
output("==========================================", $isCli);

$platforms = $manager->getSupportedPlatforms();
foreach ($platforms as $platform) {
    $status = $platform['enabled'] ? '?' : '?';
    $expiry = $platform['link_expiry'] > 0 ? gmdate('H:i:s', $platform['link_expiry']) : 'Never';
    
    output("", $isCli);
    if ($isCli) {
        $color = $platform['enabled'] ? "\033[32m" : "\033[31m";
        echo "{$color}{$status}\033[0m {$platform['icon']} {$platform['name']}\n";
    } else {
        $colorClass = $platform['enabled'] ? 'success' : 'error';
        echo "<span class='$colorClass'>{$status}</span> {$platform['icon']} {$platform['name']}<br>";
    }
    output("   Domains: " . implode(', ', $platform['domains']), $isCli);
    output("   Expiry: $expiry", $isCli);
    output("   Cookies: " . ($platform['requires_cookies'] ? 'Yes' : 'No'), $isCli);
}
if (!$isCli) echo '</div>';

output("", $isCli);
output("==========================================", $isCli);
output("Test completed!", $isCli);
output("==========================================", $isCli);

if (!$isCli) {
    echo '</body></html>';
}