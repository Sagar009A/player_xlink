<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$guide = [
    'success' => true,
    'supported_platforms' => [
        'TeraBox' => [
            'status' => 'Supported',
            'notes' => 'May fail if link is private or requires password',
            'example' => 'https://terabox.com/s/XXXXX'
        ],
        'Diskwala' => [
            'status' => 'Fully Supported',
            'notes' => 'Works for all public videos',
            'example' => 'https://diskwala.com/app/XXXXX'
        ],
        'StreamTape' => [
            'status' => 'Fully Supported',
            'example' => 'https://streamtape.com/v/XXXXX'
        ],
        'YouTube' => [
            'status' => 'Thumbnail Only',
            'notes' => 'Direct download not supported, only thumbnail extraction'
        ]
    ],
    'tips' => [
        'Make sure TeraBox links are public (not password protected)',
        'If TeraBox fails, try Diskwala or StreamTape',
        'Use Auto-Fetch button to automatically extract video info',
        'Links expire after 1-2 hours, system will auto-refresh them'
    ],
    'error_codes' => [
        '-105' => 'Link is private, expired, or requires password',
        '-9' => 'Invalid share link',
        '4' => 'Share link expired',
        '31045' => 'Link requires password'
    ]
];

echo json_encode($guide, JSON_PRETTY_PRINT);