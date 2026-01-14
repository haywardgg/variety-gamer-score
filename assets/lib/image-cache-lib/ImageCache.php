<?php
declare(strict_types=1);

/**
 * ImageCache (Imagick-only)
 *
 * - Steam-friendly (GET only, no HEAD)
 * - Requires Imagick
 * - No GD fallback
 * - Re-encode + resize
 * - WebP or JPG output
 * - Cache-busting via mtime
 */

require_once __DIR__ . '/helpers.php';

final class ImageCache
{
    private string $fsDir;
    private string $webPath;
    private string $ext;
    private int $timeout;
    private int $maxW;
    private int $maxH;
    private int $quality;

    public function __construct(
        string $fsDir,
        string $webPath,
        array $formats = ['webp', 'jpg'],
        bool $preferWebp = true,
        int $timeoutSeconds = 12,
        int $maxW = 512,
        int $maxH = 512,
        int $quality = 82
    ) {
        if (!class_exists('Imagick')) {
            throw new RuntimeException('Imagick extension is required');
        }

        $this->fsDir   = rtrim($fsDir, '/');
        $this->webPath = '/' . trim($webPath, '/');
        $this->timeout = $timeoutSeconds;

        // Choose output format safely
        if (
            $preferWebp &&
            in_array('webp', $formats, true) &&
            !empty(\Imagick::queryFormats('WEBP'))
        ) {
            $this->ext = 'webp';
        } else {
            $this->ext = 'jpg';
        }

        $this->maxW    = $maxW;
        $this->maxH    = $maxH;
        $this->quality = $quality;

        mkdirp($this->fsDir);
    }

    /**
     * Fetch, cache, re-encode, resize image.
     */
    public function fetch(?string $url, array $fallbackUrls = []): string
    {
        if (!$url && !$fallbackUrls) {
            return '';
        }

        $candidates = array_values(array_filter(array_merge([$url], $fallbackUrls)));
        if (!$candidates) {
            return '';
        }

        // Stable cache key (all sources)
        $hash     = sha1(implode('|', $candidates));
        $filename = $hash . '.' . $this->ext;

        $fsPath  = $this->fsDir . '/' . $filename;
        $pubPath = $this->webPath . '/' . $filename;

        if (file_exists($fsPath)) {
            return withCacheBuster($pubPath, $fsPath);
        }

        foreach ($candidates as $candidate) {
$blob = $this->downloadGet($candidate);
if ($blob === null) {
    continue;
}

try {
    $this->processImageFromBlob(
        $blob,
        $fsPath,
        $this->ext,
        $this->maxW,
        $this->maxH,
        $this->quality
    );

    return withCacheBuster($pubPath, $fsPath);


                @unlink($rawPath);
                return withCacheBuster($pubPath, $fsPath);

            } catch (Throwable $e) {
                error_log('ImageCache Imagick failed: ' . $e->getMessage());
                @unlink($rawPath);
                @unlink($fsPath);
                continue;
            }
        }

        return '';
    }

    /**
     * Steam-friendly GET download (no HEAD, no curl).
     */
    private function downloadGet(string $url): ?string
{
    error_log("Downloading: $url");

    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => $this->timeout,
            'header'  => "User-Agent: Mozilla/5.0 (compatible; ImageCache/1.0)\r\n",
        ],
    ]);

    $data = @file_get_contents($url, false, $context);

    if ($data === false || strlen($data) < 256) {
        return null;
    }

    return $data;
}


    /**
     * Imagick-only processing.
     */
private function processImageFromBlob(
    string $blob,
    string $destPath,
    string $outExt,
    int $maxW,
    int $maxH,
    int $quality
): void {
    $im = new Imagick();

    // CRITICAL FIX: bypass filename-based format guessing
    $im->readImageBlob($blob);

    $w = $im->getImageWidth();
    $h = $im->getImageHeight();

    if ($w > $maxW || $h > $maxH) {
        $im->thumbnailImage($maxW, $maxH, true, true);
    }

    if ($outExt === 'webp') {
        $im->setImageFormat('WEBP');
        $im->setOption('webp:method', '6');
    } else {
        $im->setImageFormat('JPEG');
    }

    $im->setImageCompressionQuality($quality);

    if (!$im->writeImage($destPath)) {
        throw new RuntimeException('Imagick writeImage failed');
    }

    $im->clear();
    $im->destroy();
}

}
