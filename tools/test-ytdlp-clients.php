<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$config = require dirname(__DIR__) . '/config/config.php';
$url = $argv[1] ?? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

$ref = new ReflectionClass(App\YtDlpHelper::class);
$method = $ref->getMethod('fetchJsonAttempt');
$method->setAccessible(true);

foreach ([null, 'android,web', 'ios,web', 'tv,web', 'mweb,web'] as $client) {
    $data = $method->invoke(null, $url, $config, ['player_clients' => $client]);
    $formats = is_array($data) ? count($data['formats'] ?? []) : 0;
    $video = is_array($data) ? count(App\YtDlpFormatLinks::buildVideoLinks($data['formats'] ?? [])) : 0;
    $audio = is_array($data) ? count(App\YtDlpFormatLinks::buildAudioMp3Links($data['formats'] ?? [])) : 0;
    echo ($client ?? 'default') . " formats={$formats} video_links={$video} audio_links={$audio}\n";
}
