# Image Cache Library (PHP)

A reusable, domain-agnostic image cache for game importers (Steam, IGDB, RAWG, etc).

## Features
- Auto-detect or explicit web path support
- Content-type validation
- Steam CDN fallback support
- Re-encoding & resizing (Imagick preferred, GD fallback)
- WebP / JPG output
- Cache-busting via file mtime
- Shared cache across multiple projects

## Requirements
- PHP 8+
- cURL extension
- Imagick (recommended) or GD

## Usage
```php
require 'ImageCache.php';

$cache = new ImageCache(
    '/srv/shared-cache/steam-images',
    '/assets/shared/steam-images'
);

$url = $cache->fetch($steamUrl, $fallbacks);
```

## Steam capsule upscale helper
```php
require 'steam_capsule_upscale.php';

$src  = '/cache/steam/capsules/730_184x69.jpg';
$dest = '/cache/steam/capsules/730_552x207.webp';

upscaleSteamCapsule(
    $src,
    $dest,
    552,
    207
);
```

Capsule-aware auto sizing (keeps aspect ratio perfect):
```php
$ratio = $srcW / $srcH;
$targetW = 552;
$targetH = (int) round($targetW / $ratio);
```

The helper caps output to 2x the source dimensions.
