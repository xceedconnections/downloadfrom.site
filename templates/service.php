<?php
/** @var array<string, mixed> $serviceMeta */
/** @var array<int, array<string, mixed>> $servicePlatforms */
/** @var string $serviceId */

$pageTitle = $serviceMeta['title'];
$pageDescription = $serviceMeta['description'];
$canonicalPath = $serviceMeta['page_slug'];
$jsonLdScripts = [
    $seo->jsonLdWebPage($serviceMeta, $serviceMeta['page_slug']),
    $seo->jsonLdBreadcrumb([
        ['name' => 'Home', 'url' => $seo->canonical('')],
        ['name' => $serviceMeta['h1'], 'url' => $seo->canonical($serviceMeta['page_slug'])],
    ]),
];
$adPageType = 'platform';
$adServiceId = $serviceId;
$adProviderId = null;
$currentService = $serviceId;
$selectedService = $serviceId;
$showServiceSelect = false;
require __DIR__ . '/header.php';
?>

<section class="hero hero-compact">
    <div class="container">
        <div class="hero-grid hero-grid-compact">
            <div class="hero-main">
                <h1 class="service-page-title">
                    <?php $serviceId; $iconSize = 'lg'; require __DIR__ . '/partials/service-icon.php'; ?>
                    <span><?= App\Security::escape($serviceMeta['h1']) ?></span>
                </h1>
                <p class="hero-desc"><?= App\Security::escape($serviceMeta['description']) ?></p>
                <?php require __DIR__ . '/partials/url-form.php'; ?>
                <?php $placement = 'platform_top'; require __DIR__ . '/partials/ad-zone.php'; ?>
            </div>
            <?php if (isset($adManager) && $adManager->hasPlacement('platform_hero_sidebar', $adPageType ?? 'platform', $adServiceId ?? null, $adProviderId ?? null)): ?>
            <aside class="hero-side-slot">
                <?php $placement = 'platform_hero_sidebar'; require __DIR__ . '/partials/ad-zone.php'; ?>
            </aside>
            <?php endif; ?>
        </div>

        <?php if ($servicePlatforms !== []): ?>
        <div class="platform-icons">
            <p class="platform-label">Supported platforms:</p>
            <div class="icon-grid">
                <?php foreach ($servicePlatforms as $p): ?>
                <a href="<?= App\Security::escape($baseUrl . '/' . ($p['slug'] ?? '')) ?>" class="platform-badge" title="<?= App\Security::escape($p['name'] ?? '') ?>">
                    <?php $iconSize = 'sm'; require __DIR__ . '/partials/platform-icon.php'; ?>
                    <span><?= App\Security::escape($p['name'] ?? '') ?></span>
                    <?php require __DIR__ . '/partials/platform-new-badge.php'; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-alt" id="supported-platforms">
    <div class="container">
        <h2>Choose a Platform</h2>
        <p class="section-lead">Select a provider below or paste any supported URL in the form above.</p>
        <div class="platform-links">
            <?php foreach ($servicePlatforms as $p): ?>
            <a href="<?= App\Security::escape($baseUrl . '/' . ($p['slug'] ?? '')) ?>" class="card-link">
                <?php $iconSize = 'md'; require __DIR__ . '/partials/platform-icon.php'; ?>
                <span><?= App\Security::escape($p['name'] ?? '') ?></span>
                <?php require __DIR__ . '/partials/platform-new-badge.php'; ?>
                <span class="card-link-arrow" aria-hidden="true">→</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php $placement = 'platform_bottom'; require __DIR__ . '/partials/ad-zone.php'; ?>

<?php require __DIR__ . '/footer.php'; ?>
