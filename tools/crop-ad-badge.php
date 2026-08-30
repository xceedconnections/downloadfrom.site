<?php

declare(strict_types=1);

/** Crop whitespace from the advertisement label image. */
$src = $argv[1] ?? dirname(__DIR__) . '/assets/img/advertisement-label-source.png';
$out = dirname(__DIR__) . '/assets/img/advertisement-label.png';

if (!is_file($src)) {
    fwrite(STDERR, "Source not found: {$src}\n");
    exit(1);
}

$data = file_get_contents($src);
if ($data === false) {
    fwrite(STDERR, "Failed to read source.\n");
    exit(1);
}

$im = imagecreatefromstring($data);
if ($im === false) {
    fwrite(STDERR, "Failed to decode image.\n");
    exit(1);
}

$w = imagesx($im);
$h = imagesy($im);
$minX = $w;
$minY = $h;
$maxX = 0;
$maxY = 0;

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $rgba = imagecolorat($im, $x, $y);
        $a = ($rgba >> 24) & 0x7F;
        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;
        $isBackground = $a >= 120 || ($r > 240 && $g > 240 && $b > 240);
        if ($isBackground) {
            continue;
        }
        $minX = min($minX, $x);
        $minY = min($minY, $y);
        $maxX = max($maxX, $x);
        $maxY = max($maxY, $y);
    }
}

if ($maxX < $minX || $maxY < $minY) {
    fwrite(STDERR, "No content pixels found.\n");
    exit(1);
}

$cropW = $maxX - $minX + 1;
$cropH = $maxY - $minY + 1;
$cropped = imagecreatetruecolor($cropW, $cropH);
imagealphablending($cropped, false);
imagesavealpha($cropped, true);
$transparent = imagecolorallocatealpha($cropped, 255, 255, 255, 127);
imagefill($cropped, 0, 0, $transparent);

for ($y = 0; $y < $cropH; $y++) {
    for ($x = 0; $x < $cropW; $x++) {
        $rgba = imagecolorat($im, $minX + $x, $minY + $y);
        $a = ($rgba >> 24) & 0x7F;
        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;
        if ($a >= 120 || ($r > 240 && $g > 240 && $b > 240)) {
            imagesetpixel($cropped, $x, $y, $transparent);
            continue;
        }
        $color = imagecolorallocatealpha($cropped, $r, $g, $b, 0);
        imagesetpixel($cropped, $x, $y, $color);
    }
}

imagepng($cropped, $out, 9);
imagedestroy($im);
imagedestroy($cropped);

echo "Cropped to {$cropW}x{$cropH} -> {$out}\n";
