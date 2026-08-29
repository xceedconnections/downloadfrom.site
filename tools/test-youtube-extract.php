<?php

declare(strict_types=1);

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
use App\Provider\Youtube\YoutubeDlp;

$allowed = ['youtube.com', 'youtu.be', 'googlevideo.com', 'i.ytimg.com'];
$http = new HttpClient($allowed);
$extractor = new Extractor($http, $config);

$url = $argv[1] ?? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

echo "URL: {$url}\n";
echo 'shell_exec: ' . (function_exists('shell_exec') ? 'yes' : 'no') . "\n";
echo 'ytdlp: ' . ($config['ytdlp']['path'] ?? 'none') . ' exists=' . (is_file($config['ytdlp']['path'] ?? '') ? 'yes' : 'no') . "\n";

$ytdlp = new YoutubeDlp($config);
echo 'ytdlp available: ' . ($ytdlp->isAvailable() ? 'yes' : 'no') . "\n\n";

$links = $extractor->extract($url);
echo count($links) . " links\n";
foreach (array_slice($links, 0, 10) as $link) {
    echo '  - ' . ($link['label'] ?? '?') . "\n";
}
