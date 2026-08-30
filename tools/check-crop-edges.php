<?php

declare(strict_types=1);

$src = $argv[1] ?? dirname(__DIR__) . '/assets/img/advertisement-label.png';
$im = imagecreatefromstring((string) file_get_contents($src));
$w = imagesx($im);
$h = imagesy($im);
echo "Size: {$w}x{$h}\n";

$isTransparent = static function (int $rgba): bool {
    return (($rgba >> 24) & 0x7F) >= 127;
};

for ($edge = 0; $edge < 4; $edge++) {
    $label = ['top', 'right', 'bottom', 'left'][$edge];
    $opaque = 0;
    if ($edge === 0) {
        for ($x = 0; $x < $w; $x++) {
            if (!$isTransparent(imagecolorat($im, $x, 0))) {
                $opaque++;
            }
        }
    } elseif ($edge === 1) {
        for ($y = 0; $y < $h; $y++) {
            if (!$isTransparent(imagecolorat($im, $w - 1, $y))) {
                $opaque++;
            }
        }
    } elseif ($edge === 2) {
        for ($x = 0; $x < $w; $x++) {
            if (!$isTransparent(imagecolorat($im, $x, $h - 1))) {
                $opaque++;
            }
        }
    } else {
        for ($y = 0; $y < $h; $y++) {
            if (!$isTransparent(imagecolorat($im, 0, $y))) {
                $opaque++;
            }
        }
    }
    echo "{$label} edge opaque pixels: {$opaque}\n";
}
