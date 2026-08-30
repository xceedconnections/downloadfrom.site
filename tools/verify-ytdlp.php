<?php

declare(strict_types=1);

/**
 * Verify yt-dlp + node work and return multi-quality YouTube links.
 * Run on server: php tools/verify-ytdlp.php
 */
require dirname(__DIR__) . '/app/bootstrap.php';

$config = require dirname(__DIR__) . '/config/config.php';
$url = $argv[1] ?? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

$ytdlp = App\YtDlpHelper::resolvePath($config);
$node = App\YtDlpHelper::resolveNodePath($config);

echo "yt-dlp path: " . ($ytdlp ?? 'MISSING') . "\n";
echo "node path: " . ($node ?? 'MISSING') . "\n";
echo "ytdlp enabled: " . (!empty($config['ytdlp']['enabled']) ? 'yes' : 'no') . "\n";

if ($ytdlp === null) {
    fwrite(STDERR, "FAIL: yt-dlp binary not found.\n");
    exit(1);
}

$version = trim((string) App\YtDlpHelper::exec(escapeshellarg($ytdlp) . ' --version 2>&1'));
echo "yt-dlp version: {$version}\n";

if ($node !== null) {
    $nodeVer = trim((string) App\YtDlpHelper::exec(escapeshellarg($node) . ' --version 2>&1'));
    echo "node version: {$nodeVer}\n";
}

$data = App\YtDlpHelper::fetchJson($url, $config);
if ($data === null) {
    fwrite(STDERR, "FAIL: yt-dlp returned no usable JSON.\n");
    exit(1);
}

$video = App\YtDlpFormatLinks::buildVideoLinks($data['formats'] ?? []);
$audio = App\YtDlpFormatLinks::buildAudioMp3Links($data['formats'] ?? []);

echo 'formats: ' . count($data['formats'] ?? []) . "\n";
echo 'video links: ' . count($video) . "\n";
echo 'audio mp3 links: ' . count($audio) . "\n";

foreach ($video as $link) {
    echo '  video: ' . ($link['label'] ?? '') . "\n";
}
foreach ($audio as $link) {
    echo '  audio: ' . ($link['label'] ?? '') . "\n";
}

if (count($video) < 3) {
    fwrite(STDERR, "FAIL: expected multiple video qualities, got " . count($video) . ".\n");
    exit(1);
}

if (count($audio) < 1) {
    fwrite(STDERR, "FAIL: expected at least one MP3 link.\n");
    exit(1);
}

echo "OK\n";
