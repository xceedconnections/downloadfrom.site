<?php

/** @var array $config */

/** @var App\Seo $seo */

/** @var string $pageTitle */

/** @var string $pageDescription */

/** @var string $canonicalPath */

/** @var string|null $robotsMeta */

/** @var string|null $pageKeywords */

/** @var string|null $ogImage */

/** @var array|null $jsonLdScripts */



$pageTitle = $pageTitle ?? ($meta['title'] ?? $config['app']['name']);

$pageDescription = $pageDescription ?? ($meta['meta_description'] ?? $meta['description'] ?? '');

$pageKeywords = $pageKeywords ?? ($meta['keywords'] ?? '');

$ogImage = $ogImage ?? ($meta['og_image'] ?? '');

$canonicalPath = $canonicalPath ?? '';

$robotsMeta = $robotsMeta ?? ($meta['robots'] ?? 'index, follow');

$jsonLdScripts = $jsonLdScripts ?? [];

$baseUrl = $seo->baseUrl();

/** @var App\Settings|null $settings */

$settings = $settings ?? null;

$logoUrl = $settings ? App\PlatformConfig::logoUrl($settings, $baseUrl) : null;

$siteName = $settings ? (string) ($settings->get('site_name') ?: $config['app']['name']) : $config['app']['name'];

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php App\CustomCodes::renderHead($settings ?? null, $baseUrl, (bool) ($config['ads']['relay_scripts'] ?? false)); ?>
    <title><?= App\Security::escape($pageTitle) ?></title>

    <meta name="description" content="<?= App\Security::escape($pageDescription) ?>">

    <?php if ($pageKeywords !== ''): ?>
    <meta name="keywords" content="<?= App\Security::escape($pageKeywords) ?>">
    <?php endif; ?>

    <meta name="robots" content="<?= App\Security::escape($robotsMeta) ?>">

    <link rel="canonical" href="<?= App\Security::escape($seo->canonical($canonicalPath)) ?>">

    <meta property="og:type" content="website">

    <meta property="og:title" content="<?= App\Security::escape($pageTitle) ?>">

    <meta property="og:description" content="<?= App\Security::escape($pageDescription) ?>">

    <meta property="og:url" content="<?= App\Security::escape($seo->canonical($canonicalPath)) ?>">

    <meta property="og:site_name" content="<?= App\Security::escape($siteName) ?>">

    <?php
    $resolvedOgImage = $ogImage;
    if ($resolvedOgImage === '' && $logoUrl) {
        $resolvedOgImage = $logoUrl;
    }
    if ($resolvedOgImage !== ''):
    ?>
    <meta property="og:image" content="<?= App\Security::escape($resolvedOgImage) ?>">
    <meta name="twitter:image" content="<?= App\Security::escape($resolvedOgImage) ?>">
    <?php endif; ?>

    <meta name="twitter:card" content="summary_large_image">

    <meta name="twitter:title" content="<?= App\Security::escape($pageTitle) ?>">

    <meta name="twitter:description" content="<?= App\Security::escape($pageDescription) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= App\Security::escape($baseUrl) ?>/assets/css/main.css">

    <link rel="stylesheet" href="<?= App\Security::escape($baseUrl) ?>/assets/css/zones.css">

    <link rel="stylesheet" href="<?= App\Security::escape($baseUrl) ?>/assets/css/gate.css">

    <?php foreach ($jsonLdScripts as $jsonLd): ?>

    <script type="application/ld+json"><?= $jsonLd ?></script>

    <?php endforeach; ?>

</head>

<body>
    <?php App\CustomCodes::renderBodyStart($settings ?? null, $baseUrl, (bool) ($config['ads']['relay_scripts'] ?? false)); ?>

    <?php
    $blockAdblock = $settings ? (bool) ($settings->get('ads_block_adblock') ?? false) : false;
    $gateEnabled = !empty($config['ads']['adblock_gate']) && $blockAdblock;
    $gateActive = $gateEnabled && isset($adManager) && $adManager->isEnabled();
    if ($gateActive): ?>
    <script>window.__DFG__=<?= json_encode(['enabled' => true, 'site' => $siteName], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="<?= App\Security::escape($baseUrl) ?>/assets/js/gate.js"></script>
    <?php else: ?>
    <script>window.__DF_GATE_BLOCKED__=false;</script>
    <?php endif; ?>

    <a href="#main-content" class="skip-link">Skip to content</a>

    <header class="site-header bh-header">

        <div class="bh-header-utility">

            <div class="container bh-header-utility-inner">

                <div class="bh-header-left">

                    <button type="button" class="bh-nav-toggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="main-nav">

                        <span class="bh-nav-toggle-bar"></span>

                        <span class="bh-nav-toggle-bar"></span>

                        <span class="bh-nav-toggle-bar"></span>

                    </button>

                    <a href="<?= App\Security::escape($baseUrl) ?>/" class="bh-logo">

                        <?php if ($logoUrl): ?>

                        <img src="<?= App\Security::escape($logoUrl) ?>" alt="<?= App\Security::escape($siteName) ?>" class="bh-logo-img" height="34">

                        <?php else: ?>

                        <span class="bh-logo-mark" aria-hidden="true"></span>

                        <span class="bh-logo-text"><?= App\Security::escape($siteName) ?></span>

                        <?php endif; ?>

                    </a>

                    <button type="button" class="bh-search-btn" id="bh-search-toggle" aria-expanded="false" aria-controls="bh-search-panel">

                        <span class="bh-search-label">SEARCH</span>

                        <svg class="bh-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">

                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>

                            <path d="M20 20L16 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>

                        </svg>

                    </button>

                </div>

                <div class="bh-header-utils">

                    <a href="<?= App\Security::escape($baseUrl) ?>/contact" class="bh-util-feedback">

                        <span class="bh-util-label">FEEDBACK</span>

                        <span class="bh-feedback-icon" aria-hidden="true">💬</span>

                    </a>

                </div>

            </div>

        </div>

        <?php require __DIR__ . '/partials/header-search.php'; ?>

        <div class="bh-header-nav" id="main-nav">

            <div class="container bh-header-nav-inner">

                <?php require __DIR__ . '/partials/main-nav.php'; ?>

            </div>

        </div>

    </header>

    <?php
    $adPageType = $adPageType ?? 'all';
    $adServiceId = $adServiceId ?? null;
    $adProviderId = $adProviderId ?? null;
    if (isset($adManager) && $adManager->hasPlacement('header_banner', $adPageType, $adServiceId, $adProviderId)): ?>
    <div class="dfz-bar dfz-bar-h">
        <div class="container">
            <?php $placement = 'header_banner'; require __DIR__ . '/partials/ad-zone.php'; ?>
        </div>
    </div>
    <?php endif; ?>

    <main class="site-main" id="main-content">

