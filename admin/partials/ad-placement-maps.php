<?php

declare(strict_types=1);

use App\AdManager;

/** @var App\AdManager $adManager */
$mapPages = AdManager::placementMapPages();

?>
<div class="ad-map-intro">
    <p>Visual guide showing where each ad placement appears on the public site. Match the <strong>placement checkbox</strong> when creating or editing an ad.</p>
</div>

<div class="ad-map-grid">
<?php foreach ($mapPages as $pageId => $page): ?>
    <article class="ad-map-card" id="ad-map-<?= App\Security::escape($pageId) ?>">
        <header class="ad-map-card-head">
            <h3><?= App\Security::escape($page['title']) ?></h3>
            <p><?= App\Security::escape($page['description']) ?></p>
        </header>

        <div class="ad-map-wireframe ad-map-wireframe-<?= App\Security::escape($pageId) ?>">
            <?php if ($pageId === 'global'): ?>
            <div class="wf-block wf-header">
                <span class="wf-label">Site header / nav</span>
            </div>
            <div class="wf-block wf-ad wf-ad-highlight" data-placement="header_banner">
                <span class="wf-ad-num">Ad 1</span>
                <span class="wf-ad-name">header_banner</span>
            </div>
            <div class="wf-block wf-content wf-content-tall">
                <span class="wf-label">Page content area</span>
            </div>
            <div class="wf-block wf-ad" data-placement="footer_banner">
                <span class="wf-ad-num">Ad 2</span>
                <span class="wf-ad-name">footer_banner</span>
            </div>
            <div class="wf-block wf-footer">
                <span class="wf-label">Site footer</span>
            </div>
            <div class="wf-popup-note">+ popup overlay (full screen, timed)</div>

            <?php elseif ($pageId === 'home'): ?>
            <div class="wf-block wf-header"><span class="wf-label">Header</span></div>
            <div class="wf-block wf-ad wf-ad-sm" data-placement="header_banner"><span class="wf-ad-num">1</span> header_banner</div>
            <div class="wf-block wf-hero">
                <div class="wf-hero-split">
                    <div class="wf-hero-left">
                        <span class="wf-label">Title + description</span>
                        <span class="wf-fake-input">URL paste field | All ▾</span>
                        <span class="wf-fake-btn">Generate Links</span>
                        <div class="wf-ad wf-ad-inline" data-placement="home_after_form"><span class="wf-ad-num">3</span> home_after_form</div>
                    </div>
                    <div class="wf-hero-right wf-ad wf-ad-highlight" data-placement="home_hero_sidebar">
                        <span class="wf-ad-num">Ad 2</span>
                        <span class="wf-ad-name">home_hero_sidebar</span>
                        <span class="wf-ad-hint">Hero right sidebar</span>
                    </div>
                </div>
                <span class="wf-label wf-label-sub">Supported platforms row</span>
            </div>
            <div class="wf-block wf-content"><span class="wf-label">How It Works</span></div>
            <div class="wf-block wf-ad" data-placement="home_middle"><span class="wf-ad-num">4</span> home_middle</div>
            <div class="wf-block wf-content"><span class="wf-label">Supported Platforms + FAQ</span></div>
            <div class="wf-block wf-ad" data-placement="home_bottom"><span class="wf-ad-num">5</span> home_bottom</div>
            <div class="wf-block wf-ad wf-ad-sm" data-placement="footer_banner"><span class="wf-ad-num">6</span> footer_banner</div>
            <div class="wf-block wf-footer"><span class="wf-label">Footer</span></div>

            <?php elseif ($pageId === 'platform'): ?>
            <div class="wf-block wf-header"><span class="wf-label">Header</span></div>
            <div class="wf-block wf-ad wf-ad-sm" data-placement="header_banner"><span class="wf-ad-num">1</span> header_banner</div>
            <div class="wf-block wf-hero wf-hero-compact">
                <div class="wf-hero-split">
                    <div class="wf-hero-left">
                        <span class="wf-label">Platform title + form</span>
                        <div class="wf-ad wf-ad-inline" data-placement="platform_top"><span class="wf-ad-num">3</span> platform_top</div>
                    </div>
                    <div class="wf-hero-right wf-ad wf-ad-highlight" data-placement="platform_hero_sidebar">
                        <span class="wf-ad-num">Ad 2</span>
                        <span class="wf-ad-name">platform_hero_sidebar</span>
                    </div>
                </div>
            </div>
            <div class="wf-block wf-content"><span class="wf-label">How to Use + FAQ</span></div>
            <div class="wf-block wf-ad" data-placement="platform_bottom"><span class="wf-ad-num">4</span> platform_bottom</div>
            <div class="wf-block wf-ad wf-ad-sm" data-placement="footer_banner"><span class="wf-ad-num">5</span> footer_banner</div>
            <div class="wf-block wf-footer"><span class="wf-label">Footer</span></div>

            <?php elseif ($pageId === 'result'): ?>
            <div class="wf-block wf-header"><span class="wf-label">Header</span></div>
            <div class="wf-block wf-ad wf-ad-sm" data-placement="header_banner"><span class="wf-ad-num">1</span> header_banner</div>
            <div class="wf-block wf-ad" data-placement="result_top"><span class="wf-ad-num">2</span> result_top</div>
            <div class="wf-block wf-result-split">
                <div class="wf-result-main">
                    <span class="wf-label">Thumbnail + title</span>
                    <div class="wf-result-cols">
                        <span class="wf-label">Video links</span>
                        <span class="wf-label">Audio links</span>
                    </div>
                </div>
                <div class="wf-result-side wf-ad wf-ad-highlight" data-placement="result_sidebar">
                    <span class="wf-ad-num">Ad 3</span>
                    <span class="wf-ad-name">result_sidebar</span>
                </div>
            </div>
            <div class="wf-block wf-ad" data-placement="result_bottom"><span class="wf-ad-num">4</span> result_bottom</div>
            <div class="wf-modal-note">+ download_modal when Download clicked</div>
            <div class="wf-block wf-ad wf-ad-sm" data-placement="footer_banner"><span class="wf-ad-num">5</span> footer_banner</div>
            <div class="wf-block wf-footer"><span class="wf-label">Footer</span></div>
            <?php endif; ?>
        </div>

        <ul class="ad-map-legend">
            <?php foreach ($page['zones'] as $zone): ?>
            <li>
                <strong><?= App\Security::escape($zone['label']) ?></strong>
                <code><?= App\Security::escape($zone['key']) ?></code>
                — <?= App\Security::escape($zone['hint']) ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </article>
<?php endforeach; ?>
</div>
