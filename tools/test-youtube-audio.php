<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/app/bootstrap.php';

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

require dirname(__DIR__) . '/app/audio-provider/youtube/youtubeDlp.php';
require dirname(__DIR__) . '/app/audio-provider/youtube/Extractor.php';

use App\HttpClient;
use App\AudioProvider\Youtube\Extractor;
use App\AudioProvider\Youtube\YoutubeDlp;
use App\Analytics;
use App\Cache;
use App\Provider\ProviderManager;
use App\ServiceConfig;
use App\Settings;
use App\Storage\StorageFactory;
use App\VideoService;

$url = $argv[1] ?? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

$http = new HttpClient(['youtube.com', 'youtu.be', 'googlevideo.com']);
$configArr = require dirname(__DIR__) . '/config/config.php';

$dlp = new YoutubeDlp($configArr);
$data = \App\YtDlpHelper::fetchJson($url, $configArr);
echo 'yt-dlp formats: ' . count($data['formats'] ?? []) . PHP_EOL;

foreach ($data['formats'] ?? [] as $f) {
    echo '  fmt ' . ($f['format_id'] ?? '?')
        . ' v=' . ($f['vcodec'] ?? '')
        . ' a=' . ($f['acodec'] ?? '')
        . ' h=' . ($f['height'] ?? 0)
        . ' abr=' . ($f['abr'] ?? $f['tbr'] ?? '')
        . ' ext=' . ($f['ext'] ?? '')
        . ' url=' . (empty($f['url']) ? 'NO' : 'yes')
        . PHP_EOL;
}

$links = $dlp->extract($url);
echo 'Dlp extract: ' . count($links) . " links\n";
foreach ($links as $l) {
    echo '  - ' . ($l['label'] ?? '') . ' ext=' . ($l['ext'] ?? '') . ' q=' . ($l['quality'] ?? '') . PHP_EOL;
}

$extractor = new Extractor($http, $configArr);
$extLinks = $extractor->extract($url);
echo "\nExtractor: " . count($extLinks) . " links\n";

$pm = ProviderManager::boot($configArr);
$db = StorageFactory::create($configArr);
$settings = new Settings($db);
$vs = new VideoService(
    $pm->getDetector(),
    new Cache($configArr),
    $pm->getVideoRegistry(),
    $pm->getAudioRegistry(),
    new Analytics($db, $configArr),
    $db,
    $settings
);

$r = $vs->process($url, ServiceConfig::SERVICE_AUDIO);
echo "\nVideoService audio: " . ($r['success'] ? 'OK' : 'FAIL ' . ($r['message'] ?? '')) . PHP_EOL;
if ($r['success']) {
    echo 'links: ' . count($r['data']['links'] ?? []) . PHP_EOL;
    echo 'notice: ' . ($r['data']['notice'] ?? 'none') . PHP_EOL;
    foreach ($r['data']['links'] ?? [] as $l) {
        echo '  - ' . ($l['label'] ?? '') . PHP_EOL;
    }
}
