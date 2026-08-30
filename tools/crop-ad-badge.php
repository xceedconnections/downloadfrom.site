<?php

declare(strict_types=1);

/** Tight-crop advertisement label: transparent bg, preserve original blue text. */
$src = $argv[1] ?? dirname(__DIR__) . '/assets/img/advertisement-label-source.png';
$out = dirname(__DIR__) . '/assets/img/advertisement-label.png';

if (!is_file($src)) {
    fwrite(STDERR, "Source not found: {$src}\n");
    exit(1);
}

$im = imagecreatefromstring((string) file_get_contents($src));
if ($im === false) {
    fwrite(STDERR, "Failed to decode image.\n");
    exit(1);
}

imagepalettetotruecolor($im);
imagesavealpha($im, true);
imagealphablending($im, false);

$w = imagesx($im);
$h = imagesy($im);

$isBackground = static function (int $r, int $g, int $b, int $alpha): bool {
    if ($alpha >= 100) {
        return true;
    }

    $max = max($r, $g, $b);
    $min = min($r, $g, $b);

    if ($max >= 238 && ($max - $min) <= 20) {
        return true;
    }

    if ($max >= 215 && ($max - $min) <= 8) {
        return true;
    }

    return $b <= $r + 10 && $g <= $r + 10 && $max >= 200;
};

$textAlpha = static function (int $r, int $g, int $b) use ($isBackground): ?int {
    if ($isBackground($r, $g, $b, 0)) {
        return null;
    }

    $blueStrength = $b - max($r, $g);
    if ($blueStrength < 22) {
        return null;
    }

    if ($blueStrength >= 75) {
        return 0;
    }

    return 127 - (int) round(($blueStrength / 75) * 127);
};

$minX = $w;
$minY = $h;
$maxX = -1;
$maxY = -1;
$mask = [];

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $rgba = imagecolorat($im, $x, $y);
        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;
        $opacity = $textAlpha($r, $g, $b);
        if ($opacity === null) {
            continue;
        }
        $mask[$y][$x] = ['r' => $r, 'g' => $g, 'b' => $b, 'a' => $opacity];
        $minX = min($minX, $x);
        $minY = min($minY, $y);
        $maxX = max($maxX, $x);
        $maxY = max($maxY, $y);
    }
}

if ($maxX < $minX || $maxY < $minY) {
    fwrite(STDERR, "No text pixels found.\n");
    exit(1);
}

$cropW = $maxX - $minX + 1;
$cropH = $maxY - $minY + 1;
$cropped = imagecreatetruecolor($cropW, $cropH);
imagealphablending($cropped, false);
imagesavealpha($cropped, true);
$transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
imagefill($cropped, 0, 0, $transparent);

for ($y = 0; $y < $cropH; $y++) {
    for ($x = 0; $x < $cropW; $x++) {
        $pixel = $mask[$minY + $y][$minX + $x] ?? null;
        if ($pixel === null) {
            continue;
        }
        $color = imagecolorallocatealpha($cropped, $pixel['r'], $pixel['g'], $pixel['b'], $pixel['a']);
        imagesetpixel($cropped, $x, $y, $color);
    }
}

imagepng($cropped, $out, 9);
imagedestroy($im);
imagedestroy($cropped);

echo "Cropped to {$cropW}x{$cropH} -> {$out}\n";
