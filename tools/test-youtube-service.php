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

use App\Analytics;
use App\Cache;
use App\PlatformConfig;
use App\Provider\ProviderManager;
use App\Settings;
use App\Storage\StorageFactory;
use App\ServiceConfig;
use App\VideoService;

$config = require dirname(__DIR__) . '/app/bootstrap.php';

$pm = ProviderManager::boot($config);
$db = StorageFactory::create($config);
$settings = new Settings($db);
$cache = new Cache($config);
$vs = new VideoService(
    $pm->getDetector(),
    $cache,
    $pm->getVideoRegistry(),
    $pm->getAudioRegistry(),
    new Analytics($db, $config),
    $db,
    $settings
);

$url = $argv[1] ?? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
$service = $argv[2] ?? ServiceConfig::SERVICE_VIDEO;

echo "Testing VideoService::process\nURL: {$url}\nService: {$service}\n\n";

$r = $vs->process($url, $service);
if (!$r['success']) {
    echo "FAIL: " . ($r['message'] ?? $r['error'] ?? 'unknown') . "\n";
    exit(1);
}

$data = $r['data'];
$downloads = array_filter($data['links'] ?? [], static fn(array $l): bool => !empty($l['download']));
echo 'Success. ' . count($downloads) . " download links\n";
echo 'Notice: ' . ($data['notice'] ?? 'none') . "\n";
foreach (array_slice($downloads, 0, 5) as $l) {
    echo '  - ' . ($l['label'] ?? '?') . "\n";
}
