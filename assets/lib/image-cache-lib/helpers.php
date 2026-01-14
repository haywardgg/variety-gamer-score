<?php
declare(strict_types=1);

function mkdirp(string $dir): void {
    if (!is_dir($dir)) mkdir($dir, 0775, true);
}

function isAllowedImageContentType(string $ctype): bool {
    $ctype = strtolower(trim(explode(';', $ctype)[0]));
    return in_array($ctype, [
        'image/jpeg','image/jpg','image/png','image/webp','image/avif'
    ], true);
}

function withCacheBuster(string $publicPath, string $fsPath): string {
    return $publicPath . (file_exists($fsPath) ? ('?v=' . filemtime($fsPath)) : '');
}
