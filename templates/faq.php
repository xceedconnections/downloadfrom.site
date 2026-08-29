<?php
/** @var array<int, array{q: string, a: string}> $faq */

$pageTitle = 'Frequently Asked Questions – ' . ($settings ? (string) ($settings->get('site_name') ?: $config['app']['name']) : $config['app']['name']);
$pageDescription = 'Answers to common questions about our video and audio converter tools, supported platforms, and how the service works.';
$canonicalPath = App\ServiceConfig::PAGE_FAQ;
$jsonLdScripts = [
    $seo->jsonLdFaq($faq),
    $seo->jsonLdBreadcrumb([
        ['name' => 'Home', 'url' => $seo->canonical('')],
        ['name' => 'FAQs', 'url' => $seo->canonical(App\ServiceConfig::PAGE_FAQ)],
    ]),
];
$adPageType = 'faq';
require __DIR__ . '/header.php';
?>

<section class="hero hero-compact hero-minimal">
    <div class="container">
        <h1>Frequently Asked Questions</h1>
        <p class="hero-desc">Find answers about supported platforms, downloads, privacy, and how to use our tools.</p>
    </div>
</section>

<section class="section section-alt" id="faq">
    <div class="container">
        <?php if ($faq === []): ?>
        <p class="section-lead">No FAQ items have been published yet. Please check back soon.</p>
        <?php else: ?>
        <div class="faq-list">
            <?php foreach ($faq as $item): ?>
            <details class="faq-item">
                <summary><?= App\Security::escape($item['q']) ?></summary>
                <div class="faq-answer"><?= App\Security::sanitizeAdminHtml((string) ($item['a'] ?? '')) ?></div>
            </details>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php $placement = 'home_bottom'; require __DIR__ . '/partials/ad-zone.php'; ?>

<?php require __DIR__ . '/footer.php'; ?>
