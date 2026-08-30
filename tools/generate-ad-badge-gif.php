<?php

declare(strict_types=1);

/**
 * Minimal animated GIF writer (2+ frames).
 */
final class SimpleGifCreator
{
    private string $gif = 'GIF89a';

    /** @param array<int, string> $frames Raw GIF89a frame blobs from imagegif() */
    /** @param array<int, int> $durations Frame delays in centiseconds */
    public function create(array $frames, array $durations, int $loops = 0): string
    {
        if ($frames === []) {
            return '';
        }

        $this->gif .= substr($frames[0], 6, 7);
        $this->gif .= $this->word($loops);
        $this->gif .= "\xFF\xFF";
        $this->gif .= "\xF9\x04\x00\x00\x00\x00\x00";
        $this->gif .= "\x2C\x00\x00\x00\x00\x00";
        $this->gif .= chr(ord($frames[0][6])) . chr(ord($frames[0][8])) . "\x00\x00";
        $this->gif .= substr($frames[0], 13);

        $count = count($frames);
        for ($i = 0; $i < $count; $i++) {
            $delay = $durations[$i] ?? 10;
            $this->gif .= "\x21\xF9\x04\x04\x00";
            $this->gif .= chr($delay & 0xFF) . chr(($delay >> 8) & 0xFF);
            $this->gif .= "\x00\x00\x00";
            $this->gif .= "\x21\xFE\x09\x50\x68\x70\x47\x69\x66\x00";
            $this->gif .= "\x2C\x00\x00\x00\x00\x00";
            $this->gif .= chr(ord($frames[$i][6])) . chr(ord($frames[$i][8])) . "\x00\x00";
            $this->gif .= substr($frames[$i], 13);
        }

        $this->gif .= "\x3B";

        return $this->gif;
    }

    private function word(int $int): string
    {
        return chr($int & 0xFF) . chr(($int >> 8) & 0xFF);
    }
}

$out = dirname(__DIR__) . '/assets/img/advertisement-flashing.gif';
$w = 140;
$h = 32;

$frames = [];
foreach ([0, 100] as $fade) {
    $im = imagecreate($w, $h);
    $orange = imagecolorallocate($im, 249, 115, 22);
    $dim = imagecolorallocate($im, 251, 176, 120);
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $fade === 0 ? $orange : $dim);
    imagestring($im, 5, 46, 8, 'AD', $white);
    ob_start();
    imagegif($im);
    $frames[] = ob_get_clean() ?: '';
    imagedestroy($im);
}

$creator = new SimpleGifCreator();
$gif = $creator->create($frames, [35, 35], 0);
file_put_contents($out, $gif);
echo 'OK ' . strlen($gif) . " bytes\n";
