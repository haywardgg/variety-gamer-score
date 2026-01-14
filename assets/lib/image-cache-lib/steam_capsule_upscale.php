<?php
declare(strict_types=1);

function upscaleSteamCapsule(
    string $srcPath,
    string $destPath,
    int $targetWidth,
    int $targetHeight,
    bool $force = false
): string {
    if (!extension_loaded('imagick')) {
        throw new RuntimeException('Imagick not available');
    }

    if (file_exists($destPath) && !$force) {
        return $destPath;
    }

    $img = new Imagick($srcPath);
    $img->setImageColorspace(Imagick::COLORSPACE_RGB);

    $srcW = $img->getImageWidth();
    $srcH = $img->getImageHeight();

    $maxW = (int) round($srcW * 2);
    $maxH = (int) round($srcH * 2);

    $targetWidth = min($targetWidth, $maxW);
    $targetHeight = min($targetHeight, $maxH);

    /* ---------- MULTI-STEP UPSCALE ---------- */
    $steps = 2;
    for ($i = 1; $i <= $steps; $i++) {
        $w = (int) ($srcW + (($targetWidth - $srcW) * ($i / $steps)));
        $h = (int) ($srcH + (($targetHeight - $srcH) * ($i / $steps)));

        $img->resizeImage(
            $w,
            $h,
            Imagick::FILTER_LANCZOS,
            1
        );
    }

    /* ---------- CAPSULE-SPECIFIC CLEANUP ---------- */

    // Kill resize ringing
    $img->blurImage(0.3, 0.4);

    // Restore text edges
    $img->unsharpMaskImage(
        0,    // radius (0 = auto)
        0.9,  // sigma
        1.2,  // amount
        0.03  // threshold
    );

    // Micro contrast bump
    $img->contrastImage(1);

    /* ---------- OUTPUT ---------- */

    $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));

    if ($ext === 'webp') {
        $img->setImageFormat('webp');
        $img->setImageCompressionQuality(90);
    } else {
        $img->setImageFormat('png');
    }

    $img->writeImage($destPath);
    $img->clear();
    $img->destroy();

    return $destPath;
}
