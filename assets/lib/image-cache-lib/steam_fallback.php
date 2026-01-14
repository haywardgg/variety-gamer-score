<?php
declare(strict_types=1);

function steamFallbackUrls(int $appid): array {
    $bases = [
        "https://cdn.akamai.steamstatic.com/steam/apps/$appid",
    ];
    $paths = [
        'capsule_231x87.jpg',    
    ];

    $out = [];
    foreach ($bases as $b) {
        foreach ($paths as $p) $out[] = "$b/$p";
    }
    return $out;
}
