<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/app/bootstrap.php';

use App\AdminAuth;
use App\Storage\StorageFactory;
use App\Storage\StorageBootstrap;
use App\Logger;
use App\PlatformConfig;
use App\Provider\ProviderManager;
use App\RateLimiter;
use App\Security;
use App\ServiceConfig;
use App\Settings;

Logger::init($config);
Security::initSession($config);

$db = StorageFactory::create($config);
StorageBootstrap::ensureInitialized($db, $config);
$rateLimiter = new RateLimiter($db, $config);
$auth = new AdminAuth($db, $rateLimiter);
$settings = new Settings($db);
$providerManager = ProviderManager::boot($config);
$videoPlatforms = PlatformConfig::mergePlatforms($providerManager->getVideoPlatforms(), $settings, 'video');
$audioPlatforms = PlatformConfig::mergePlatforms($providerManager->getAudioPlatforms(), $settings, 'audio');
$platforms = $videoPlatforms;
$allVideoPlatforms = $providerManager->getVideoPlatforms();
$allAudioPlatforms = $providerManager->getAudioPlatforms();
$allPlatforms = $allVideoPlatforms;
$plugins = $providerManager->getVideoPlugins();
$audioPlugins = $providerManager->getAudioPlugins();
$services = ServiceConfig::getServices($settings);
