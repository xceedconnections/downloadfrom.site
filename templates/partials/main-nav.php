<?php

declare(strict_types=1);

use App\ServiceConfig;

/** @var array<int, array<string, mixed>> $servicesNav */
/** @var string $baseUrl */

$servicesNav = $servicesNav ?? [];

$segmentColors = ['#e53935', '#ff9800', '#ffc107', '#4caf50', '#2196f3', '#9c27b0', '#e91e63', '#00bcd4'];
$navItems = [];

$navItems[] = [
    'type' => 'link',
    'label' => 'HOME',
    'href' => $baseUrl . '/',
];

foreach ($servicesNav as $service) {
    $serviceName = (string) ($service['name'] ?? '');
    $navItems[] = [
        'type' => 'mega',
        'label' => strtoupper($serviceName),
        'title' => $serviceName,
        'href' => $baseUrl . '/' . ($service['page_slug'] ?? ''),
        'service_id' => (string) ($service['id'] ?? ''),
        'platforms' => $service['platforms'] ?? [],
    ];
}

$navItems[] = [
    'type' => 'link',
    'label' => 'FAQS',
    'href' => $baseUrl . '/' . ServiceConfig::PAGE_FAQ,
];
$navItems[] = [
    'type' => 'link',
    'label' => 'CONTACT',
    'href' => $baseUrl . '/contact',
];

foreach ($navItems as $i => &$navItem) {
    $navItem['color'] = $segmentColors[$i % count($segmentColors)];
}
unset($navItem);

?>
<ul class="bh-nav-list">
    <?php foreach ($navItems as $item): ?>
    <li class="bh-nav-item<?= ($item['type'] ?? '') === 'mega' ? ' bh-has-mega' : '' ?>" style="--nav-accent: <?= App\Security::escape($item['color']) ?>">
        <?php if (($item['type'] ?? '') === 'link'): ?>
        <a href="<?= App\Security::escape($item['href']) ?>" class="bh-nav-link"><?= App\Security::escape($item['label']) ?></a>
        <?php else: ?>
        <div class="bh-nav-item-head">
            <a href="<?= App\Security::escape($item['href']) ?>" class="bh-nav-link bh-nav-service-link">
                <?php if (!empty($item['service_id'])): ?>
                <?php $serviceId = $item['service_id']; $iconSize = 'xs'; require __DIR__ . '/service-icon.php'; ?>
                <?php endif; ?>
                <span><?= App\Security::escape($item['label']) ?></span>
            </a>
            <button type="button" class="bh-nav-trigger" aria-expanded="false" aria-haspopup="true" aria-label="Show <?= App\Security::escape($item['title']) ?> providers">
                <span aria-hidden="true">▾</span>
            </button>
        </div>
        <div class="bh-mega-menu" role="region" aria-label="<?= App\Security::escape($item['title']) ?> providers">
            <div class="container bh-mega-inner">
                <div class="bh-mega-grid">
                    <?php foreach ($item['platforms'] as $p): ?>
                    <a href="<?= App\Security::escape($baseUrl . '/' . ($p['slug'] ?? '')) ?>" class="bh-mega-link">
                        <span class="bh-mega-link-main">
                            <?php $iconSize = 'sm'; require __DIR__ . '/platform-icon.php'; ?>
                            <span><?= App\Security::escape($p['name'] ?? '') ?></span>
                        </span>
                        <?php require __DIR__ . '/platform-new-badge.php'; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>
