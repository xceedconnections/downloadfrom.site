<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Provider\ProviderManager;

$manager = ProviderManager::boot($config);
$platforms = $manager->getPlatforms();

echo count($platforms) . " platforms loaded\n";
foreach ($platforms as $id => $p) {
    echo "  {$id} -> {$p['slug']} (folder: {$p['folder']})\n";
}

$detector = $manager->getDetector();
$testUrl = 'https://www.youtube.com/watch?v=8-NHiPHi_x0';
echo "\nDetect: " . $detector->detect($testUrl) . "\n";

$registry = $manager->getRegistry();
$provider = $registry->get('youtube');
if ($provider) {
    $result = $provider->fetch($testUrl);
    $links = $result['data']['links'] ?? [];
    echo "YouTube links: " . count($links) . "\n";
    if (!empty($links[0]['label'])) {
        echo "First: " . $links[0]['label'] . "\n";
    }
}
