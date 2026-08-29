<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Cache;
use App\Storage\StorageFactory;
use App\Storage\StorageKeys;
use App\VideoService;
use App\Analytics;
use App\Settings;
use App\Provider\ProviderManager;

$manager = ProviderManager::boot($config);
$db = StorageFactory::create($config);
$settings = new Settings($db);
$cache = new Cache($config);
$analytics = new Analytics($db, $config);
$vs = new VideoService($manager->getDetector(), $cache, $manager->getRegistry(), $analytics, $db, $settings);

$token = bin2hex(random_bytes(16));
$url = 'https://youtube.com/watch?v=cleanuptest';
$data = [
    'title' => 'test',
    'normalized_url' => $url,
    'original_url' => $url,
    'cache_key' => hash('sha256', $url),
];
$db->write(StorageKeys::result($token), ['token' => $token, 'data' => $data, 'created' => time(), 'expires' => time() + 3600]);
$cache->set($url, $data);

$resultBefore = $db->exists(StorageKeys::result($token));
$cacheBefore = is_file($cache->pathForKey($data['cache_key']));

$vs->cleanupResult($token);

$resultAfter = $db->exists(StorageKeys::result($token));
$cacheAfter = is_file($cache->pathForKey($data['cache_key']));

echo 'Result deleted: ' . ($resultBefore && !$resultAfter ? 'OK' : 'FAIL') . PHP_EOL;
echo 'Cache deleted: ' . ($cacheBefore && !$cacheAfter ? 'OK' : 'FAIL') . PHP_EOL;
