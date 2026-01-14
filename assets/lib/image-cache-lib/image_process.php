<?php
declare(strict_types=1);

function processImage(string $src, string $dest, string $ext, int $maxW, int $maxH, int $quality): void {
    if (class_exists('Imagick')) {
        $im = new Imagick($src);
        if (method_exists($im, 'autoOrient')) $im->autoOrient();
        $im->stripImage();
        $im->thumbnailImage($maxW, $maxH, true, true);
        $im->setImageFormat($ext === 'webp' ? 'webp' : 'jpeg');
        $im->setImageCompressionQuality($quality);
        $im->writeImage($dest);
        $im->clear();
        return;
    }

    $raw = file_get_contents($src);
    $img = imagecreatefromstring($raw);
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min($maxW/$w, $maxH/$h, 1.0);
    $nw = (int)($w*$scale);
    $nh = (int)($h*$scale);

    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

    if ($ext === 'webp' && function_exists('imagewebp')) {
        imagewebp($dst, $dest, $quality);
    } else {
        imagejpeg($dst, $dest, $quality);
    }

    imagedestroy($img);
    imagedestroy($dst);
}
