<?php

declare(strict_types=1);

$config = require __DIR__ . '/app/bootstrap.php';

use App\AdManager;
use App\Analytics;
use App\Cache;
use App\Storage\StorageFactory;
use App\Logger;
use App\PlatformConfig;
use App\Provider\ProviderManager;
use App\RateLimiter;
use App\Router;
use App\Security;
use App\Seo;
use App\Settings;
use App\ServiceConfig;
use App\Validator;
use App\VideoService;

Logger::init($config);
Security::initSession($config);
Security::setHeaders($config);

$providerManager = ProviderManager::boot($config);
$db = StorageFactory::create($config);
$settings = new Settings($db);
$adManager = new AdManager($db, $config['app']['url']);
$videoPlatforms = PlatformConfig::mergePlatforms($providerManager->getVideoPlatforms(), $settings, 'video');
$audioPlatforms = PlatformConfig::mergePlatforms($providerManager->getAudioPlatforms(), $settings, 'audio');
$platforms = $videoPlatforms;
$servicesNav = ServiceConfig::buildNavigation($settings, $videoPlatforms, $audioPlatforms);
$cache = new Cache($config);
$seo = new Seo($config, $videoPlatforms, $audioPlatforms);
$validator = new Validator($config);
$rateLimiter = new RateLimiter($db, $config);
$analytics = new Analytics($db, $config);
$detector = $providerManager->getDetector();
$videoService = new VideoService(
    $detector,
    $cache,
    $providerManager->getVideoRegistry(),
    $providerManager->getAudioRegistry(),
    $analytics,
    $settings
);

$router = new Router(
    $config,
    $seo,
    $videoService,
    $validator,
    $rateLimiter,
    $settings,
    $videoPlatforms,
    $audioPlatforms,
    $servicesNav,
    $adManager
);

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$router->dispatch($uri, $method);
