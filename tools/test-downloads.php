<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Analytics;
use App\Cache;
use App\Storage\StorageFactory;
use App\Provider\ProviderManager;
use App\Settings;
use App\VideoService;

$config = require dirname(__DIR__) . '/config/config.php';
$pm = ProviderManager::boot($config);
$db = StorageFactory::create($config);
$settings = new Settings($db);
$vs = new VideoService(
    $pm->getDetector(),
    new Cache($config),
    $pm->getRegistry(),
    new Analytics($db, $config),
    $db,
    $settings
);

$tests = [
    'tiktok' => 'https://vm.tiktok.com/ZMhKwLnLx/',
    'youtube' => 'https://www.youtube.com/watch?v=8-NHiPHi_x0',
];

foreach ($tests as $name => $url) {
    echo "=== {$name} ===\n";
    $r = $vs->process($url);
    if (!$r['success']) {
        echo "FAIL: {$r['message']}\n\n";
        continue;
    }
    $downloads = array_filter($r['data']['links'] ?? [], static fn(array $l): bool => !empty($l['download']));
    echo count($downloads) . " download links\n";
    foreach (array_slice($downloads, 0, 5) as $l) {
        echo "  - {$l['label']}\n";
    }
    echo "\n";
}
