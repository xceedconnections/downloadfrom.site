<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$config = require dirname(__DIR__) . '/config/config.php';
$data = App\YtDlpHelper::fetchJson($argv[1] ?? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', $config);
foreach ($data['formats'] ?? [] as $f) {
    $h = (int) ($f['height'] ?? 0);
    $v = (string) ($f['vcodec'] ?? 'none');
    if ($h >= 480 || ($v === 'none' && ($f['acodec'] ?? '') !== 'none')) {
        echo ($f['format_id'] ?? '?')
            . ' h=' . $h
            . ' p=' . ($f['protocol'] ?? '')
            . ' ext=' . ($f['ext'] ?? '')
            . ' url=' . (empty($f['url']) ? 'NO' : 'YES')
            . PHP_EOL;
    }
}
