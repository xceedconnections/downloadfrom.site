<?php

declare(strict_types=1);

use App\AdManager;
use App\Security;

/** @var App\AdManager $adManager */
/** @var array $config */
/** @var string $message */
/** @var string $error */

$placementMap = $adManager->getPlacementMap();
$adData = $adManager->getData();
$allAds = $adManager->allAds();
$mapPages = AdManager::placementMapPages();

/**
 * @param array<string, string> $placementMap
 * @param array<int, array<string, mixed>> $allAds
 */
$renderZonePicker = static function (
    string $placementKey,
    string $num,
    string $title,
    string $extraClass = '',
    string $hint = ''
) use ($placementMap, $allAds): void {
    $plabel = AdManager::PLACEMENTS[$placementKey] ?? $title;
    $selectedId = (string) ($placementMap[$placementKey] ?? '');
    $classes = trim('wf-block wf-ad wf-zone-picker ' . $extraClass);
    ?>
    <div class="<?= Security::escape($classes) ?>" data-placement="<?= Security::escape($placementKey) ?>">
        <span class="wf-ad-num"><?= Security::escape($num) ?></span>
        <span class="wf-ad-title"><?= Security::escape($title) ?></span>
        <?php if ($hint !== ''): ?>
        <span class="wf-ad-hint"><?= Security::escape($hint) ?></span>
        <?php endif; ?>
        <label class="wf-ad-select-wrap">
            <span class="wf-ad-select-label">Ad</span>
            <select
                class="wf-ad-select"
                name="placement_map[<?= Security::escape($placementKey) ?>]"
                data-placement="<?= Security::escape($placementKey) ?>"
                aria-label="<?= Security::escape($plabel) ?>"
            >
                <option value="">— None —</option>
                <?php foreach ($allAds as $ad):
                    $aid = (string) ($ad['id'] ?? '');
                    $selected = $selectedId === $aid;
                ?>
                <option value="<?= Security::escape($aid) ?>" <?= $selected ? 'selected' : '' ?>>
                    <?= Security::escape($ad['name'] ?? $aid) ?><?= empty($ad['enabled']) ? ' (off)' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <?php
};

?>
<form method="POST" class="admin-form admin-form-wide ad-map-form">
    <?= Security::csrfField($config) ?>
    <input type="hidden" name="save_placement_map" value="1">

    <fieldset class="admin-fieldset">
        <legend>Global</legend>
        <label class="checkbox">
            <input type="checkbox" name="ads_enabled" <?= !empty($adData['enabled']) ? 'checked' : '' ?>>
            Enable ads on the website
        </label>
        <label>Download modal countdown (seconds)
            <input type="number" name="download_modal_countdown" min="0" max="30" value="<?= (int) ($adData['download_modal_countdown'] ?? 5) ?>">
        </label>
    </fieldset>

    <?php if ($allAds === []): ?>
    <p class="admin-error">No ads yet. <a href="ads.php?tab=ads&amp;action=create">Create an ad</a> first, then return here to assign zones.</p>
    <?php else: ?>

    <fieldset class="admin-fieldset ad-map-visual-fieldset">
        <legend>Assign ads on the layout</legend>
        <p class="admin-note">Click each yellow zone in the diagrams below and pick which ad to show. The same zone (e.g. header banner) stays in sync across all page types.</p>

        <div class="ad-map-grid">
        <?php foreach ($mapPages as $pageId => $page): ?>
            <article class="ad-map-card" id="ad-map-<?= Security::escape($pageId) ?>">
                <header class="ad-map-card-head">
                    <h3><?= Security::escape($page['title']) ?></h3>
                    <p><?= Security::escape($page['description']) ?></p>
                </header>

                <div class="ad-map-wireframe ad-map-wireframe-<?= Security::escape($pageId) ?>">
                    <?php if ($pageId === 'global'): ?>
                    <div class="wf-block wf-header"><span class="wf-label">Site header / nav</span></div>
                    <?php $renderZonePicker('header_banner', 'Ad 1', 'Header banner', 'wf-ad-highlight', 'Below nav, all pages'); ?>
                    <div class="wf-block wf-content wf-content-tall"><span class="wf-label">Page content</span></div>
                    <?php $renderZonePicker('footer_banner', 'Ad 2', 'Footer banner', '', 'Above footer, all pages'); ?>
                    <div class="wf-block wf-footer"><span class="wf-label">Site footer</span></div>
                    <?php $renderZonePicker('popup', 'Popup', 'Timed popup', 'wf-ad-popup', 'Full-screen overlay after delay'); ?>

                    <?php elseif ($pageId === 'home'): ?>
                    <div class="wf-block wf-header"><span class="wf-label">Header</span></div>
                    <?php $renderZonePicker('header_banner', '1', 'Header banner', 'wf-ad-sm'); ?>
                    <div class="wf-block wf-hero">
                        <div class="wf-hero-split">
                            <div class="wf-hero-left">
                                <span class="wf-label">Title + URL form</span>
                                <span class="wf-fake-input">Paste URL…</span>
                                <span class="wf-fake-btn">Generate Links</span>
                                <?php $renderZonePicker('home_after_form', '3', 'Below form', 'wf-ad-inline'); ?>
                            </div>
                            <div class="wf-hero-right">
                                <?php $renderZonePicker('home_hero_sidebar', 'Ad 2', 'Hero sidebar', 'wf-ad-highlight wf-ad-fill', 'Right of form (desktop)'); ?>
                            </div>
                        </div>
                        <span class="wf-label wf-label-sub">Supported platforms row</span>
                    </div>
                    <div class="wf-block wf-content"><span class="wf-label">How It Works</span></div>
                    <?php $renderZonePicker('home_middle', '4', 'Middle content'); ?>
                    <div class="wf-block wf-content"><span class="wf-label">Supported Platforms + FAQ</span></div>
                    <?php $renderZonePicker('home_bottom', '5', 'Bottom content'); ?>
                    <?php $renderZonePicker('footer_banner', '6', 'Footer banner', 'wf-ad-sm'); ?>

                    <?php elseif ($pageId === 'platform'): ?>
                    <div class="wf-block wf-header"><span class="wf-label">Header</span></div>
                    <?php $renderZonePicker('header_banner', '1', 'Header banner', 'wf-ad-sm'); ?>
                    <div class="wf-block wf-hero wf-hero-compact">
                        <div class="wf-hero-split">
                            <div class="wf-hero-left">
                                <span class="wf-label">Platform title + form</span>
                                <?php $renderZonePicker('platform_top', '3', 'Below form', 'wf-ad-inline'); ?>
                            </div>
                            <div class="wf-hero-right">
                                <?php $renderZonePicker('platform_hero_sidebar', 'Ad 2', 'Hero sidebar', 'wf-ad-highlight wf-ad-fill'); ?>
                            </div>
                        </div>
                    </div>
                    <div class="wf-block wf-content"><span class="wf-label">How to Use + FAQ</span></div>
                    <?php $renderZonePicker('platform_bottom', '4', 'Bottom content'); ?>
                    <?php $renderZonePicker('footer_banner', '5', 'Footer banner', 'wf-ad-sm'); ?>

                    <?php elseif ($pageId === 'result'): ?>
                    <div class="wf-block wf-header"><span class="wf-label">Header</span></div>
                    <?php $renderZonePicker('header_banner', '1', 'Header banner', 'wf-ad-sm'); ?>
                    <?php $renderZonePicker('result_top', '2', 'Result top'); ?>
                    <div class="wf-block wf-result-split">
                        <div class="wf-result-main">
                            <span class="wf-label">Thumbnail + title</span>
                            <div class="wf-result-cols">
                                <span class="wf-label">Video links</span>
                                <span class="wf-label">Audio links</span>
                            </div>
                        </div>
                        <div class="wf-result-side">
                            <?php $renderZonePicker('result_sidebar', 'Ad 3', 'Result sidebar', 'wf-ad-highlight wf-ad-fill', 'Right column'); ?>
                        </div>
                    </div>
                    <?php $renderZonePicker('result_bottom', '4', 'Result bottom'); ?>
                    <?php $renderZonePicker('download_modal', 'Modal', 'Download modal', 'wf-ad-modal', 'When user clicks Download'); ?>
                    <?php $renderZonePicker('footer_banner', '5', 'Footer banner', 'wf-ad-sm'); ?>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    </fieldset>

    <button type="submit" class="btn btn-primary">Save placement map</button>
    <?php endif; ?>
</form>

<?php if ($allAds !== []): ?>
<script>
(function () {
    var selects = document.querySelectorAll('.wf-ad-select[data-placement]');
    selects.forEach(function (sel) {
        sel.addEventListener('change', function () {
            var key = sel.getAttribute('data-placement');
            var val = sel.value;
            document.querySelectorAll('.wf-ad-select[data-placement="' + key + '"]').forEach(function (other) {
                if (other !== sel) {
                    other.value = val;
                }
            });
        });
    });
})();
</script>
<?php endif; ?>
