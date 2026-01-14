<?php

declare(strict_types=1);

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$dataFile = __DIR__ . '/../data/steam-top100.json';

$fallbackPayload = [
    'games' => [],
    'generated_at' => date('c'),
    'error' => 'No data available',
];

function outputFallback(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

// Check cache first (30-minute cache on the API response)
$cacheFile = '/tmp/pcgamers_steam_top100_cache.json';
$cacheTtlSeconds = 1800; // 30 minutes

if (file_exists($cacheFile)) {
    $cacheContents = file_get_contents($cacheFile);
    if ($cacheContents !== false) {
        $cached = json_decode($cacheContents, true);
        if (isset($cached['expires_at'], $cached['payload']) && time() < $cached['expires_at']) {
            echo json_encode($cached['payload'], JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

// Read data from JSON file
if (!file_exists($dataFile)) {
    outputFallback($fallbackPayload);
}

$dataContents = file_get_contents($dataFile);
if ($dataContents === false) {
    outputFallback($fallbackPayload);
}

$data = json_decode($dataContents, true);
if (!is_array($data) || !isset($data['games']) || !is_array($data['games'])) {
    outputFallback($fallbackPayload);
}

// Return the data
$payload = $data;

// Cache the result
file_put_contents($cacheFile, json_encode([
    'expires_at' => time() + $cacheTtlSeconds,
    'payload' => $payload,
], JSON_UNESCAPED_SLASHES));

echo json_encode($payload, JSON_UNESCAPED_SLASHES);
