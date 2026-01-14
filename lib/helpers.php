<?php

$loadJsonFile = static function (string $path): array {
    if (!file_exists($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        return [];
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : [];
};

$loadSteamTopGames = static function (string $path) use ($loadJsonFile): array {
    $decoded = $loadJsonFile($path);
    if (isset($decoded['games']) && is_array($decoded['games'])) {
        return $decoded['games'];
    }
    return [];
};

$buildThumbnailSources = static function (?string $thumbnailUrl): ?array {
    $thumbnailUrl = is_string($thumbnailUrl) ? trim($thumbnailUrl) : '';
    if ($thumbnailUrl === '') {
        return null;
    }

    $extension = strtolower(pathinfo(parse_url($thumbnailUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    $webp = $extension === 'webp' ? $thumbnailUrl : null;

    return [
        'fallback' => $thumbnailUrl,
        'webp' => $webp,
    ];
};
