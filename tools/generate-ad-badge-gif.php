<?php

declare(strict_types=1);

/** Generate a valid single-frame GIF; flashing effect comes from CSS. */
$out = dirname(__DIR__) . '/assets/img/advertisement-flashing.gif';
$w = 140;
$h = 32;

$im = imagecreatetruecolor($w, $h);
$orange = imagecolorallocate($im, 249, 115, 22);
$white = imagecolorallocate($im, 255, 255, 255);
$dark = imagecolorallocate($im, 234, 88, 12);
imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $orange);
imagefilledrectangle($im, 0, $h - 4, $w - 1, $h - 1, $dark);
imagestring($im, 5, 46, 8, 'AD', $white);

if (!imagegif($im, $out)) {
    fwrite(STDERR, "Failed to write GIF.\n");
    exit(1);
}

imagedestroy($im);
echo 'OK ' . filesize($out) . " bytes\n";
