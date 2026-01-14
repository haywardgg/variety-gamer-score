<?php

declare(strict_types=1);

/**
 * Steam Top Importer (Top N Most Played)
 * - Source: Steam ISteamChartsService/GetGamesByConcurrentPlayers API
 * - Enrich: Steam Store appdetails
 * - Store: JSON file
 * - Image caching: shared reusable ImageCache (webp/jpg, content-type validation, resize/re-encode, cache-busting)
 * - Steam CDN fallback: steamstatic patterns
 * - Cron-safe: flock() lock
 */

//////////////////////////////
// CONFIG
//////////////////////////////

$config = require __DIR__ . '/assets/config.php';
$steamTopConfig = $config['steam_top'] ?? [];

$cc    = 'gb';
$lang  = 'en';
$limit = isset($steamTopConfig['import_limit']) ? (int) $steamTopConfig['import_limit'] : 50;

/**
 * IMAGE TYPE CONFIGURATION
 * Choose which image type to use for game thumbnails:
 * - 'capsule_image': Standard capsule (231x87)
 * - 'capsule_imagev5': Alternative capsule format (467x181)
 * - 'header_image': Large header image (460x215)
 */
$imageType = isset($steamTopConfig['image_type']) ? (string) $steamTopConfig['image_type'] : 'capsule_imagev5';

$lockFile = __DIR__ . '/steamtopimport.lock';
$cacheDir = __DIR__ . '/cache_store_appdetails';
$dataFile = __DIR__ . '/data/steam-top100.json';

$cacheTtlSeconds = 6 * 3600;
$storeMinDelayMs = 250;
$httpTimeout     = 12;

$excludeTypes = [
    'dlc', 'demo', 'tool', 'application', 'video', 'music', 'hardware', 'advertising'
];

// Tags to exclude (case-insensitive)
$excludeTags = [
    'utilities', 'software', 'animation & modeling', 'design & illustration',
    'audio production', 'video production', 'photo editing', 'web publishing',
    'education', 'tutorial', 'game development'
];
// Preprocess to lowercase for performance
$excludeTags = array_map('strtolower', $excludeTags);
$excludeIfTypeMissing = false;

/**
 * Shared image cache across projects:
 * - FS dir: shared cache folder
 * - Web path: must map to that FS dir via nginx `alias` or a symlink in your web root
 *
 * If you don't want cross-project sharing, set $sharedFsCacheDir to a local path like:
 *   __DIR__ . '/assets/cache/steam-images'
 * and $sharedWebPath to:
 *   '/assets/cache/steam-images'
 */
$sharedFsCacheDir = __DIR__ . '/assets/cache/steam-images';
$sharedWebPath    = '/assets/cache/steam-images';

/**
 * Output format + processing
 */
$preferWebp = true;           // set false to force jpg
$formats    = ['webp', 'jpg']; // allowed outputs
$quality    = 99;

// Per imageType size caps (keeps aspect ratio)
$sizeCaps = [
    'capsule_imagev5' => ['w' => 467, 'h' => 181],
    'capsule_image'   => ['w' => 231, 'h' => 87],
    'header_image'    => ['w' => 460, 'h' => 215],
];

//////////////////////////////
// INCLUDE IMAGE CACHE LIB
//////////////////////////////

require_once __DIR__ . '/assets/lib/image-cache-lib/ImageCache.php'; // provides ImageCache, mkdirp()
require_once __DIR__ . '/assets/lib/image-cache-lib/steam_fallback.php'; // provides steamFallbackUrls()

//////////////////////////////
// UTIL
//////////////////////////////

function sleepMs(int $ms): void {
    usleep($ms * 1000);
}

function httpGetJson(string $url, int $timeoutSeconds): array {
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => $timeoutSeconds,
            'header'  => "User-Agent: SteamTopImporter/1.0\r\n",
        ]
    ]);

    $raw = file_get_contents($url, false, $ctx);
    if ($raw === false) {
        throw new RuntimeException("HTTP GET failed: $url");
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException("Invalid JSON from: $url");
    }

    return $json;
}

function storeUrl(int $appid): string {
    return "https://store.steampowered.com/app/$appid/";
}

function cachePath(string $cacheDir, int $appid, string $cc, string $lang): string {
    return rtrim($cacheDir, '/') . "/appdetails_{$appid}_{$cc}_{$lang}.json";
}

function loadCached(string $path, int $ttlSeconds): ?array {
    if (!file_exists($path)) return null;
    if (time() - filemtime($path) > $ttlSeconds) return null;

    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function saveCached(string $path, array $json): void {
    file_put_contents($path, json_encode($json, JSON_UNESCAPED_SLASHES));
}

function fetchStoreAppDetails(
    int $appid,
    string $cc,
    string $lang,
    string $cacheDir,
    int $cacheTtl,
    int $timeoutSeconds,
    int $minDelayMs
): ?array {
    mkdirp($cacheDir);
    $path = cachePath($cacheDir, $appid, $cc, $lang);

    if ($cached = loadCached($path, $cacheTtl)) {
        return $cached;
    }

    sleepMs($minDelayMs);

    $url  = "https://store.steampowered.com/api/appdetails?appids=$appid&cc=$cc&l=$lang";
    $json = httpGetJson($url, $timeoutSeconds);
    saveCached($path, $json);

    return $json;
}

function shouldExcludeType(?string $type, array $excludeTypes, bool $excludeIfTypeMissing): bool {
    if (!$type) return $excludeIfTypeMissing;
    return in_array(strtolower($type), $excludeTypes, true);
}

/**
 * Check if game has any excluded tags (like Utilities)
 */
function shouldExcludeTags(array $categories, array $genres, array $excludeTags): bool {
    // Check categories
    foreach ($categories as $category) {
        $desc = strtolower($category['description'] ?? '');
        foreach ($excludeTags as $excludeTag) {
            if (strpos($desc, $excludeTag) !== false) {
                return true;
            }
        }
    }
    
    // Check genres
    foreach ($genres as $genre) {
        $desc = strtolower($genre['description'] ?? '');
        foreach ($excludeTags as $excludeTag) {
            if (strpos($desc, $excludeTag) !== false) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Select the best store-provided image URL based on configured type.
 */
function pickStoreImageUrl(array $appData, string $imageType): ?string {
    return match ($imageType) {
        'capsule_imagev5' => $appData['capsule_imagev5'] ?? $appData['capsule_image'] ?? null,
        'header_image'    => $appData['header_image'] ?? $appData['capsule_image'] ?? null,
        'capsule_image'   => $appData['capsule_image'] ?? null,
        default           => $appData['capsule_image'] ?? null,
    };
}

/**
 * Map importer imageType to steamstatic fallback kind.
 */
function fallbackKindForImageType(string $imageType): string {
    return match ($imageType) {
        'capsule_imagev5' => 'capsule_467x181',
        'capsule_image'   => 'capsule_231x87',
        'header_image'    => 'header_460x215',
        default           => 'capsule_231x87',
    };
}

//////////////////////////////
// CRON-SAFE LOCK
//////////////////////////////

$lockFp = fopen($lockFile, 'c+');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    exit(0);
}

try {
    // Build image cache instance with correct size cap for chosen imageType
    $cap = $sizeCaps[$imageType] ?? ['w' => 512, 'h' => 512];

    $imageCache = new ImageCache(
        $sharedFsCacheDir,
        $sharedWebPath,
        $formats,
        $preferWebp,
        $httpTimeout,
        $cap['w'],
        $cap['h'],
        $quality
    );

    $apiUrl      = "https://api.steampowered.com/ISteamChartsService/GetGamesByConcurrentPlayers/v1/";
    $apiResponse = httpGetJson($apiUrl, $httpTimeout);

    if (!isset($apiResponse['response']['ranks']) || !is_array($apiResponse['response']['ranks'])) {
        throw new RuntimeException('Invalid Steam API response');
    }

    // Phase 1: Collect games that pass exclusion filters (no image downloads yet)
    $gamesData = [];
    $rank  = 0;

    foreach ($apiResponse['response']['ranks'] as $entry) {
        if ($rank >= $limit) break;
        if (empty($entry['appid'])) continue;

        $appid = (int)$entry['appid'];

        $storeJson = fetchStoreAppDetails(
            $appid,
            $cc,
            $lang,
            $cacheDir,
            $cacheTtlSeconds,
            $httpTimeout,
            $storeMinDelayMs
        );

        if (!isset($storeJson[(string)$appid]['data']) || !is_array($storeJson[(string)$appid]['data'])) {
            continue;
        }

        $d = $storeJson[(string)$appid]['data'];

        if (shouldExcludeType($d['type'] ?? null, $excludeTypes, $excludeIfTypeMissing)) {
            continue;
        }

        // Check for excluded tags (like Utilities)
        $categories = $d['categories'] ?? [];
        $genres = $d['genres'] ?? [];
        if (shouldExcludeTags($categories, $genres, $excludeTags)) {
            continue;
        }

        $rank++;

        // Store game data without downloading images yet
        $gamesData[] = [
            'rank'           => $rank,
            'appid'          => $appid,
            'name'           => $d['name'] ?? 'Unknown',
            'isfree'         => !empty($d['is_free']) ? 1 : 0,
            'priceformatted' => $d['price_overview']['final_formatted'] ?? '',
            'currentplayers' => (int)($entry['concurrent_in_game'] ?? 0),
            'peakplayers'    => (int)($entry['peak_in_game'] ?? 0),
            'storeurl'       => storeUrl($appid),
            'imagetype'      => $imageType,
            'sourceImageUrl' => pickStoreImageUrl($d, $imageType),
            'fallbackkind'   => fallbackKindForImageType($imageType),
        ];
    }

// --- Phase 2: Download images only for games that passed exclusions
$games = [];
foreach ($gamesData as $gameData) {
    $appid = $gameData['appid'];
    $storeSource = $gameData['sourceImageUrl'] ?? null;

    // Build a kind-aware CDN primary URL (try this first).
    // fallbackkind is like 'capsule_467x181' / 'capsule_231x87' / 'header_460x215'
    $cdnPrimary = "https://shared.akamai.steamstatic.com/steam/apps/{$appid}/{$gameData['fallbackkind']}.jpg";

    // Steam CDN fallbacks (steamstatic), tuned per image type.
    // keep the generic fallbacks too (steamFallbackUrls) and include the original store URL as a last-resort.
    $fallbacks = array_values(array_filter(array_merge(
        [$storeSource],           // store-provided URL (may point to store_item_assets/.../capsule_184x69.jpg)
        steamFallbackUrls($appid) // existing generic cdn fallbacks
    )));

    // Try the CDN primary first, then fallbacks including original store URL.
    $imageUrl = $imageCache->fetch($cdnPrimary, $fallbacks);

    // Add to final games array with downloaded image
    $games[] = [
        'rank'           => $gameData['rank'],
        'appid'          => $appid,
        'name'           => $gameData['name'],
        'isfree'         => $gameData['isfree'],
        'priceformatted' => $gameData['priceformatted'],
        'currentplayers' => $gameData['currentplayers'],
        'peakplayers'    => $gameData['peakplayers'],
        'thumbnail'      => $imageUrl,
        'storeurl'       => $gameData['storeurl'],
        'imagetype'      => $gameData['imagetype'],
        'fallbackkind'   => $gameData['fallbackkind'],
    ];
}

    mkdirp(dirname($dataFile));

    file_put_contents(
        $dataFile . '.tmp',
        json_encode([
            'generated_at' => date('c'),
            'count'        => count($games),
            'games'        => $games
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
    );

    rename($dataFile . '.tmp', $dataFile);

} finally {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
}
