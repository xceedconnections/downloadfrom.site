<?php
$pageTitle = $meta['title'];
$pageDescription = $meta['meta_description'];
$canonicalPath = $meta['slug'];
$jsonLdScripts = [
    $seo->jsonLdWebPage($meta, $meta['slug']),
    $seo->jsonLdFaq($meta['faq']),
    $seo->jsonLdBreadcrumb([
        ['name' => 'Home', 'url' => $seo->canonical('')],
        ['name' => $meta['h1'], 'url' => $seo->canonical($meta['slug'])],
    ]),
];
$adPageType = 'platform';
$adServiceId = $meta['service'] ?? App\ServiceConfig::SERVICE_VIDEO;
$adProviderId = (string) ($meta['id'] ?? '');
require __DIR__ . '/header.php';
?>

<section class="hero hero-compact">
    <div class="container">
        <div class="hero-grid hero-grid-compact">
            <div class="hero-main">
                <h1 class="platform-page-title">
                    <?php $p = $meta; $iconSize = 'lg'; require __DIR__ . '/partials/platform-icon.php'; ?>
                    <span><?= App\Security::escape($meta['h1']) ?></span>
                    <?php require __DIR__ . '/partials/platform-new-badge.php'; ?>
                </h1>
                <p class="hero-desc"><?= App\Security::escape($meta['description']) ?></p>
                <?php require __DIR__ . '/partials/url-form.php'; ?>
                <?php $placement = 'platform_top'; require __DIR__ . '/partials/ad-zone.php'; ?>
            </div>
            <?php if (isset($adManager) && $adManager->hasPlacement('platform_hero_sidebar', $adPageType ?? 'platform', $adServiceId ?? null, $adProviderId ?? null)): ?>
            <aside class="hero-side-slot">
                <?php $placement = 'platform_hero_sidebar'; require __DIR__ . '/partials/ad-zone.php'; ?>
            </aside>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container prose">
        <h2>How to Use</h2>
        <ol class="steps-list">
            <?php foreach ($meta['how_to'] as $step): ?>
            <li><?= App\Security::escape($step) ?></li>
            <?php endforeach; ?>
        </ol>

        <h2>Supported URL Examples</h2>
        <ul class="url-examples">
            <?php foreach ($meta['url_examples'] as $example): ?>
            <li><code><?= App\Security::escape($example) ?></code></li>
            <?php endforeach; ?>
        </ul>

        <h2>Supported Domains</h2>
        <p>This tool recognizes URLs from: <?= App\Security::escape(implode(', ', $meta['supported_domains'])) ?>.</p>

        <?php if (empty($meta['download_supported'])): ?>
        <div class="notice-box notice-success">
            <p><strong>Downloads enabled:</strong> Paste a <?= App\Security::escape($meta['name']) ?> URL above and click Generate Links to get direct download options.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <h2>Frequently Asked Questions</h2>
        <div class="faq-list">
            <?php foreach ($meta['faq'] as $item): ?>
            <details class="faq-item">
                <summary><?= App\Security::escape($item['q']) ?></summary>
                <p><?= App\Security::escape($item['a']) ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php $placement = 'platform_bottom'; require __DIR__ . '/partials/ad-zone.php'; ?>

<section class="section">
    <div class="container">
        <h2><?= ($adServiceId ?? '') === App\ServiceConfig::SERVICE_AUDIO ? 'Other Audio Platforms' : 'Other Video Platforms' ?></h2>
        <div class="platform-links">
            <?php
                $relatedPlatforms = ($adServiceId ?? '') === App\ServiceConfig::SERVICE_AUDIO
                    ? ($audioPlatforms ?? $platforms)
                    : ($videoPlatforms ?? $platforms);
                foreach ($relatedPlatforms as $p):
            ?>
                <?php if ($p['slug'] !== $meta['slug']): ?>
                <a href="<?= App\Security::escape($baseUrl . '/' . $p['slug']) ?>" class="card-link">
                    <?php $iconSize = 'md'; require __DIR__ . '/partials/platform-icon.php'; ?>
                    <span><?= App\Security::escape($p['name']) ?></span>
                    <span class="card-link-arrow" aria-hidden="true">→</span>
                </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
