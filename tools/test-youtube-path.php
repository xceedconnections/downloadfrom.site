<?php

declare(strict_types=1);

putenv('PATH=C:\Windows\System32');

$config = require dirname(__DIR__) . '/config/config.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require dirname(__DIR__) . '/app/video-provider/youtube/youtubeDlp.php';
require dirname(__DIR__) . '/app/video-provider/youtube/Extractor.php';

use App\HttpClient;
use App\Provider\Youtube\Extractor;

$http = new HttpClient(['youtube.com', 'youtu.be', 'googlevideo.com']);
$extractor = new Extractor($http, $config);
$links = $extractor->extract('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
echo 'PATH=' . getenv('PATH') . PHP_EOL;
echo count($links) . " links with minimal PATH\n";
