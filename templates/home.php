<?php
$pageTitle = $meta['title'];
$pageDescription = $meta['meta_description'];
$pageKeywords = $meta['keywords'] ?? '';
$ogImage = $meta['og_image'] ?? '';
$robotsMeta = $meta['robots'] ?? 'index, follow';
$canonicalPath = '';
$siteNameForLd = $settings ? (string) ($settings->get('site_name') ?: $config['app']['name']) : $config['app']['name'];
$jsonLdScripts = [
    $seo->jsonLdWebSite($siteNameForLd),
    $seo->jsonLdWebPage($meta, ''),
];
$adPageType = 'home';
require __DIR__ . '/header.php';
?>

<section class="hero">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-main">
                <h1><?= App\Security::escape($meta['h1']) ?></h1>
                <p class="hero-desc"><?= App\Security::escape($pageDescription) ?></p>

                <?php require __DIR__ . '/partials/url-form.php'; ?>

                <?php $placement = 'home_after_form'; require __DIR__ . '/partials/ad-zone.php'; ?>
            </div>
            <?php if (isset($adManager) && $adManager->hasPlacement('home_hero_sidebar', $adPageType ?? 'home', $adServiceId ?? null)): ?>
            <aside class="hero-side-slot">
                <?php $placement = 'home_hero_sidebar'; require __DIR__ . '/partials/ad-zone.php'; ?>
            </aside>
            <?php endif; ?>
        </div>

        <div class="platform-icons">
            <p class="platform-label">Supported services:</p>
            <?php foreach ($servicesNav ?? [] as $service): ?>
            <div class="service-platform-group">
                <p class="service-group-label">
                    <?php $serviceId = (string) ($service['id'] ?? ''); $iconSize = 'xs'; require __DIR__ . '/partials/service-icon.php'; ?>
                    <span><?= App\Security::escape($service['name']) ?></span>
                </p>
                <div class="icon-grid">
                    <?php foreach ($service['platforms'] as $p): ?>
                    <a href="<?= App\Security::escape($baseUrl . '/' . $p['slug']) ?>" class="platform-badge" title="<?= App\Security::escape($p['name']) ?>">
                        <?php $iconSize = 'sm'; require __DIR__ . '/partials/platform-icon.php'; ?>
                        <span><?= App\Security::escape($p['name']) ?></span>
                        <?php require __DIR__ . '/partials/platform-new-badge.php'; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" id="how-it-works">
    <div class="container">
        <h2>How It Works</h2>
        <ol class="steps-list">
            <li><strong>Copy</strong> the video URL from your favorite platform</li>
            <li><strong>Paste</strong> it into the input box above</li>
            <li><strong>Generate</strong> available links and metadata</li>
            <li><strong>Select</strong> the permitted viewing option</li>
        </ol>
    </div>
</section>

<?php $placement = 'home_middle'; require __DIR__ . '/partials/ad-zone.php'; ?>

<section class="section section-alt" id="supported-platforms">
    <div class="container">
        <h2>Supported Platforms</h2>
        <?php foreach ($servicesNav ?? [] as $service): ?>
        <h3 class="service-section-title">
            <?php $serviceId = (string) ($service['id'] ?? ''); $iconSize = 'sm'; require __DIR__ . '/partials/service-icon.php'; ?>
            <span><?= App\Security::escape($service['name']) ?></span>
        </h3>
        <div class="platform-links">
            <?php foreach ($service['platforms'] as $p): ?>
            <a href="<?= App\Security::escape($baseUrl . '/' . $p['slug']) ?>" class="card-link">
                <?php $iconSize = 'md'; require __DIR__ . '/partials/platform-icon.php'; ?>
                <span><?= App\Security::escape($p['name']) ?></span>
                <?php require __DIR__ . '/partials/platform-new-badge.php'; ?>
                <span class="card-link-arrow" aria-hidden="true">→</span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="section" id="why-use">
    <div class="container">
        <h2>Why Use Our Tool</h2>
        <div class="features-grid">
            <div class="feature-card">
                <h3>Fast & Free</h3>
                <p>Instantly retrieve public video metadata without registration or fees.</p>
            </div>
            <div class="feature-card">
                <h3>Multi-Platform</h3>
                <p><?= App\Security::escape(App\PlatformConfig::multiPlatformBlurb($platforms)) ?></p>
            </div>
            <div class="feature-card">
                <h3>Mobile Friendly</h3>
                <p>Optimized for phones and tablets with large touch-friendly controls.</p>
            </div>
            <div class="feature-card">
                <h3>Direct Downloads</h3>
                <p>Get downloadable MP4 links in multiple qualities when available.</p>
            </div>
            <div class="feature-card">
                <h3>Compliant</h3>
                <p>Works with public videos from major platforms worldwide.</p>
            </div>
        </div>
    </div>
</section>

<?php $placement = 'home_bottom'; require __DIR__ . '/partials/ad-zone.php'; ?>

<section class="section seo-content">
    <div class="container prose">
        <?php if (!empty($meta['seo_content'])): ?>
        <?= App\Security::sanitizeAdminHtml($meta['seo_content']) ?>
        <?php else: ?>
        <h2>Online Video URL Tool</h2>
        <p>Our free online video URL utility helps you quickly access public information about videos from supported platforms. <?= App\Security::escape(App\PlatformConfig::platformExamplesBlurb($platforms)) ?></p>
        <p>We extract direct download links where possible and retrieve video titles, thumbnails, author information, and multiple quality options.</p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
