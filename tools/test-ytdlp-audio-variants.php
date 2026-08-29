<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/YtDlpHelper.php';

use App\YtDlpHelper;

$config = require dirname(__DIR__) . '/config/config.php';
$url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
$bin = YtDlpHelper::resolvePath($config);
$node = YtDlpHelper::resolveNodePath($config);
$js = $node ? ' --js-runtimes ' . escapeshellarg('node:' . $node) : '';

$variants = [
    'none' => '',
    'android,web' => "--extractor-args " . escapeshellarg('youtube:player_client=android,web'),
    'web,mweb' => "--extractor-args " . escapeshellarg('youtube:player_client=web,mweb'),
    'tv,web' => "--extractor-args " . escapeshellarg('youtube:player_client=tv,web'),
    'bestaudio' => '-f ba',
];

foreach ($variants as $name => $extra) {
    echo "=== {$name} ===\n";
    $cmd = escapeshellarg((string) $bin)
        . ' -j --no-playlist --no-warnings --no-check-certificates'
        . $js . ' ' . $extra . ' ' . escapeshellarg($url) . ' 2>&1';
    $out = YtDlpHelper::exec($cmd);
    $data = YtDlpHelper::parseJsonOutput((string) $out);
    if ($data === null) {
        echo "parse fail: " . substr((string) $out, 0, 160) . "\n\n";
        continue;
    }
    $formats = $data['formats'] ?? [];
    if ($formats === [] && !empty($data['url'])) {
        $formats = [$data];
    }
    echo 'formats: ' . count($formats) . "\n";
    $audio = 0;
    foreach ($formats as $f) {
        if (($f['vcodec'] ?? 'none') === 'none' && ($f['acodec'] ?? 'none') !== 'none') {
            $audio++;
            echo '  ' . ($f['format_id'] ?? '?') . ' abr=' . ($f['abr'] ?? $f['tbr'] ?? 0) . ' ext=' . ($f['ext'] ?? '') . "\n";
        }
    }
    echo "audio-only: {$audio}\n";
    if ($name === 'bestaudio') {
        echo 'root format_id: ' . ($data['format_id'] ?? 'none') . ' ext=' . ($data['ext'] ?? '') . ' url=' . (empty($data['url']) ? 'no' : 'yes') . "\n";
        echo 'requested_formats: ' . count($data['requested_formats'] ?? []) . "\n";
    }
    echo "\n";
}
