<?php

declare(strict_types=1);

$config = require __DIR__ . '/app/bootstrap.php';

use App\Storage\StorageFactory;
use App\Storage\StorageBootstrap;
use App\PlatformConfig;
use App\Provider\ProviderManager;
use App\Seo;
use App\Settings;

$providerManager = ProviderManager::boot($config);
$db = StorageFactory::create($config);
StorageBootstrap::ensureInitialized($db, $config);
$settings = new Settings($db);
$videoPlatforms = PlatformConfig::mergePlatforms($providerManager->getVideoPlatforms(), $settings, 'video');
$audioPlatforms = PlatformConfig::mergePlatforms($providerManager->getAudioPlatforms(), $settings, 'audio');
$seo = new Seo($config, $videoPlatforms, $audioPlatforms);
$urls = $seo->sitemapUrls();

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
    <url>
        <loc><?= htmlspecialchars($url['loc'], ENT_XML1) ?></loc>
        <changefreq><?= htmlspecialchars($url['changefreq'], ENT_XML1) ?></changefreq>
        <priority><?= htmlspecialchars($url['priority'], ENT_XML1) ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
