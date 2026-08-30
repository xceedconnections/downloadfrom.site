<?php

declare(strict_types=1);

$src = $argv[1] ?? dirname(__DIR__) . '/assets/img/advertisement-label-source.png';
if (!is_file($src)) {
    fwrite(STDERR, "Missing: {$src}\n");
    exit(1);
}

$im = imagecreatefromstring((string) file_get_contents($src));
imagepalettetotruecolor($im);
$w = imagesx($im);
$h = imagesy($im);
echo "Size: {$w}x{$h}\n";

$colors = [];
for ($y = 0; $y < $h; $y += max(1, (int) ($h / 20))) {
    for ($x = 0; $x < $w; $x += max(1, (int) ($w / 20))) {
        $c = imagecolorat($im, $x, $y);
        $key = sprintf('%d,%d,%d,%d', ($c >> 16) & 255, ($c >> 8) & 255, $c & 255, ($c >> 24) & 127);
        $colors[$key] = ($colors[$key] ?? 0) + 1;
    }
}
arsort($colors);
echo "Sample colors (r,g,b,a): count\n";
$i = 0;
foreach ($colors as $k => $n) {
    echo "  {$k}: {$n}\n";
    if (++$i >= 12) {
        break;
    }
}
