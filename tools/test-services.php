<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/app/bootstrap.php';

$pm = App\Provider\ProviderManager::boot($config);
$db = App\Storage\StorageFactory::create($config);
$settings = new App\Settings($db);

$video = App\PlatformConfig::mergePlatforms($pm->getVideoPlatforms(), $settings, 'video');
$audio = App\PlatformConfig::mergePlatforms($pm->getAudioPlatforms(), $settings, 'audio');
$nav = App\ServiceConfig::buildNavigation($settings, $video, $audio);

echo count($video) . " video platforms\n";
echo count($audio) . " audio platforms\n";
echo count($nav) . " services in nav\n";
foreach ($nav as $s) {
    echo '  ' . $s['name'] . ': ' . count($s['platforms']) . " providers\n";
}
