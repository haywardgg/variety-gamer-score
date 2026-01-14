<?php
declare(strict_types=1);

function curlHead(string $url, int $timeout): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => 'ImageCache/1.0',
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
    curl_close($ch);

    return [
        'ok' => ($code >= 200 && $code < 400),
        'content_type' => $ctype,
    ];
}

function curlDownload(string $url, string $dest, int $timeout): bool {
    $fp = fopen($dest, 'wb');
    if (!$fp) return false;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => 'ImageCache/1.0',
    ]);

    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    fclose($fp);

    if ($ok === false or $code < 200 or $code >= 400) {
        @unlink($dest);
        return false;
    }
    return true;
}
