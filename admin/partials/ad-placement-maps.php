<?php

declare(strict_types=1);

use App\AdManager;
use App\Security;

/** @var App\AdManager $adManager */
/** @var App\Settings $settings */
/** @var array<string, array<string, mixed>> $videoPlatforms */
/** @var array<string, array<string, mixed>> $audioPlatforms */
/** @var array $config */
/** @var string $message */
/** @var string $error */

$placementMap = $adManager->getPlacementMap();
$adData = $adManager->getData();
$allAds = $adManager->allAds();
$mapPages = AdManager::placementMapPages($settings, $videoPlatforms, $audioPlatforms);

/**
 * @param array<string, array<int, string>> $placementMap
 * @param array<int, array<string, mixed>> $allAds
 */
$renderZonePicker = static function (
    string $placementKey,
    string $num,
    string $title,
    string $extraClass = '',
    string $hint = '',
    ?string $serviceId = null,
    ?string $providerId = null,
    bool $popupZone = false
) use ($placementMap, $allAds): void {
    $mapKey = AdManager::placementMapKey($placementKey, $serviceId, $providerId);
    $plabel = AdManager::PLACEMENTS[$placementKey] ?? $title;
    if ($providerId !== null) {
        $plabel .= ' (' . $providerId . ')';
    } elseif ($serviceId !== null) {
        $plabel .= ' (' . $serviceId . ')';
    }
    $selectedIds = $placementMap[$mapKey] ?? [];
    $serviceKey = $serviceId !== null && $providerId !== null
        ? AdManager::placementMapKey($placementKey, $serviceId)
        : ($serviceId !== null ? AdManager::placementMapKey($placementKey, $serviceId) : '');
    $serviceAssigned = $serviceKey !== '' ? ($placementMap[$serviceKey] ?? []) : [];
    $globalIds = $placementMap[$placementKey] ?? [];
    $classes = trim('wf-block wf-ad wf-zone-picker ' . $extraClass);
    $zoneAds = array_values(array_filter($allAds, static function (array $ad) use ($popupZone): bool {
        $type = (string) ($ad['type'] ?? '');
        return $popupZone ? $type === 'popup' : $type !== 'popup';
    }));
    $modeHint = $popupZone
        ? 'Add multiple popup ads — all assigned popups show in order.'
        : 'Add multiple ads — one rotates per page view (weighted by priority).';
    ?>
    <div class="<?= Security::escape($classes) ?>" data-placement="<?= Security::escape($mapKey) ?>" data-popup-zone="<?= $popupZone ? '1' : '0' ?>">
        <span class="wf-ad-num"><?= Security::escape($num) ?></span>
        <span class="wf-ad-title"><?= Security::escape($title) ?></span>
        <?php if ($hint !== ''): ?>
        <span class="wf-ad-hint"><?= Security::escape($hint) ?></span>
        <?php endif; ?>
        <?php if ($selectedIds === [] && $providerId !== null && $serviceAssigned !== []): ?>
        <span class="wf-ad-hint">Falls back to service-wide ads when empty.</span>
        <?php elseif ($selectedIds === [] && ($providerId !== null || $serviceId !== null) && $globalIds !== []): ?>
        <span class="wf-ad-hint">Falls back to global zone ads when empty.</span>
        <?php endif; ?>
        <div class="wf-ad-multi" data-placement="<?= Security::escape($mapKey) ?>">
            <ul class="wf-ad-chips" aria-label="<?= Security::escape($plabel) ?> assigned ads">
                <?php foreach ($selectedIds as $selectedId):
                    $selectedAd = null;
                    foreach ($allAds as $ad) {
                        if (($ad['id'] ?? '') === $selectedId) {
                            $selectedAd = $ad;
                            break;
                        }
                    }
                    if ($selectedAd === null) {
                        continue;
                    }
                ?>
                <li class="wf-ad-chip" data-ad-id="<?= Security::escape($selectedId) ?>">
                    <span><?= Security::escape($selectedAd['name'] ?? $selectedId) ?><?= empty($selectedAd['enabled']) ? ' (off)' : '' ?></span>
                    <button type="button" class="wf-ad-chip-remove" aria-label="Remove ad">&times;</button>
                    <input type="hidden" name="placement_map[<?= Security::escape($mapKey) ?>][]" value="<?= Security::escape($selectedId) ?>">
                </li>
                <?php endforeach; ?>
            </ul>
            <label class="wf-ad-select-wrap">
                <span class="wf-ad-select-label">Add ad</span>
                <select class="wf-ad-add" data-placement="<?= Security::escape($mapKey) ?>" aria-label="Add ad to <?= Security::escape($plabel) ?>">
                    <option value="">— Choose ad —</option>
                    <?php foreach ($zoneAds as $ad):
                        $aid = (string) ($ad['id'] ?? '');
                        if ($aid === '' || in_array($aid, $selectedIds, true)) {
                            continue;
                        }
                    ?>
                    <option value="<?= Security::escape($aid) ?>" data-name="<?= Security::escape($ad['name'] ?? $aid) ?>" data-enabled="<?= empty($ad['enabled']) ? '0' : '1' ?>">
                        <?= Security::escape($ad['name'] ?? $aid) ?><?= empty($ad['enabled']) ? ' (off)' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <span class="wf-ad-hint wf-ad-multi-hint"><?= Security::escape($modeHint) ?></span>
        </div>
    </div>
    <?php
};

$renderPlatformWireframe = static function (?string $serviceId, ?string $providerId = null, ?string $providerLabel = null) use ($renderZonePicker): void {
    $providerHint = $providerLabel !== null ? $providerLabel . ' page' : 'Converter hub';
    ?>
    <div class="wf-block wf-header"><span class="wf-label">Header</span></div>
    <?php $renderZonePicker('header_banner', '1', 'Header banner', 'wf-ad-sm', '', $serviceId, $providerId); ?>
    <div class="wf-block wf-hero wf-hero-compact">
        <div class="wf-hero-split">
            <div class="wf-hero-left">
                <span class="wf-label"><?= Security::escape($providerHint) ?> + form</span>
                <?php $renderZonePicker('platform_top', '3', 'Below form', 'wf-ad-inline', '', $serviceId, $providerId); ?>
            </div>
            <div class="wf-hero-right">
                <?php $renderZonePicker('platform_hero_sidebar', '2', 'Hero sidebar', 'wf-ad-highlight wf-ad-fill', 'Right of form (desktop)', $serviceId, $providerId); ?>
            </div>
        </div>
    </div>
    <div class="wf-block wf-content"><span class="wf-label">How to Use + FAQ</span></div>
    <?php $renderZonePicker('platform_bottom', '4', 'Bottom content', '', '', $serviceId, $providerId); ?>
    <?php $renderZonePicker('footer_banner', '5', 'Footer banner', 'wf-ad-sm', '', $serviceId, $providerId); ?>
    <?php $renderZonePicker('popup', 'Popup', 'Timed popup', 'wf-ad-popup', 'Full-screen overlay after delay', $serviceId, $providerId, true); ?>
    <?php
};

$renderResultWireframe = static function (?string $serviceId, string $linksLabel = 'Download links', ?string $providerId = null) use ($renderZonePicker): void {
    ?>
    <div class="wf-block wf-header"><span class="wf-label">Header</span></div>
    <?php $renderZonePicker('header_banner', '1', 'Header banner', 'wf-ad-sm', '', $serviceId, $providerId); ?>
    <?php $renderZonePicker('result_top', '2', 'Result top', '', '', $serviceId, $providerId); ?>
    <div class="wf-block wf-result-split">
        <div class="wf-result-main">
            <span class="wf-label">Thumbnail + title</span>
            <div class="wf-result-cols">
                <span class="wf-label"><?= Security::escape($linksLabel) ?></span>
            </div>
        </div>
        <div class="wf-result-side">
            <?php $renderZonePicker('result_sidebar', '3', 'Result sidebar', 'wf-ad-highlight wf-ad-fill', 'Right column', $serviceId, $providerId); ?>
        </div>
    </div>
    <?php $renderZonePicker('result_bottom', '4', 'Result bottom', '', '', $serviceId, $providerId); ?>
    <?php $renderZonePicker('download_modal', 'Modal', 'Download modal', 'wf-ad-modal', 'When user clicks Download', $serviceId, $providerId); ?>
    <?php $renderZonePicker('footer_banner', '5', 'Footer banner', 'wf-ad-sm', '', $serviceId, $providerId); ?>
    <?php $renderZonePicker('popup', 'Popup', 'Timed popup', 'wf-ad-popup', 'Full-screen overlay after delay', $serviceId, $providerId, true); ?>
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
        <p class="admin-note">Each zone supports <strong>multiple ads</strong>. Banner zones rotate one ad per page view. Popup zones show every assigned popup in order. Empty zones fall back: provider → service hub → global.</p>
        <p class="admin-note">Ads created here are your own content. They load through a same-origin endpoint so they still appear on the ad-blocker notice screen. Third-party tags in Settings → Custom Codes may remain blocked until the visitor disables their ad blocker.</p>

        <div class="ad-map-grid">
        <?php foreach ($mapPages as $pageId => $page):
            $layout = (string) ($page['layout'] ?? $pageId);
            $serviceId = isset($page['service_id']) ? (string) $page['service_id'] : null;
            $providerId = isset($page['provider_id']) ? (string) $page['provider_id'] : null;
            $providerName = isset($page['provider_name']) ? (string) $page['provider_name'] : null;
        ?>
            <article class="ad-map-card" id="ad-map-<?= Security::escape($pageId) ?>">
                <header class="ad-map-card-head">
                    <h3><?= Security::escape($page['title']) ?></h3>
                    <p><?= Security::escape($page['description']) ?></p>
                </header>

                <div class="ad-map-wireframe ad-map-wireframe-<?= Security::escape($layout) ?>">
                    <?php if ($layout === 'global'): ?>
                    <div class="wf-block wf-header"><span class="wf-label">Site header / nav</span></div>
                    <?php $renderZonePicker('header_banner', 'Ad 1', 'Header banner', 'wf-ad-highlight', 'Below nav, all pages'); ?>
                    <div class="wf-block wf-content wf-content-tall"><span class="wf-label">Page content</span></div>
                    <?php $renderZonePicker('footer_banner', 'Ad 2', 'Footer banner', '', 'Above footer, all pages'); ?>
                    <div class="wf-block wf-footer"><span class="wf-label">Site footer</span></div>
                    <?php $renderZonePicker('popup', 'Popup', 'Timed popup', 'wf-ad-popup', 'Full-screen overlay after delay', null, null, true); ?>

                    <?php elseif ($layout === 'home'): ?>
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
                                <?php $renderZonePicker('home_hero_sidebar', '2', 'Hero sidebar', 'wf-ad-highlight wf-ad-fill', 'Right of form (desktop)'); ?>
                            </div>
                        </div>
                        <span class="wf-label wf-label-sub">Supported platforms row</span>
                    </div>
                    <div class="wf-block wf-content"><span class="wf-label">How It Works</span></div>
                    <?php $renderZonePicker('home_middle', '4', 'Middle content'); ?>
                    <div class="wf-block wf-content"><span class="wf-label">Supported Platforms + FAQ</span></div>
                    <?php $renderZonePicker('home_bottom', '5', 'Bottom content'); ?>
                    <?php $renderZonePicker('footer_banner', '6', 'Footer banner', 'wf-ad-sm'); ?>
                    <?php $renderZonePicker('popup', 'Popup', 'Timed popup', 'wf-ad-popup', 'Full-screen overlay after delay', null, null, true); ?>

                    <?php elseif ($layout === 'platform'): ?>
                    <?php $renderPlatformWireframe($serviceId, $providerId, $providerName); ?>

                    <?php elseif ($layout === 'result'): ?>
                    <?php $renderResultWireframe($serviceId, (string) ($page['result_links_label'] ?? 'Download links'), $providerId); ?>
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
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getSelectedIds(multi) {
        var ids = [];
        multi.querySelectorAll('.wf-ad-chip').forEach(function (chip) {
            var id = chip.getAttribute('data-ad-id');
            if (id) {
                ids.push(id);
            }
        });
        return ids;
    }

    function renderChips(multi, items) {
        var placement = multi.getAttribute('data-placement');
        var list = multi.querySelector('.wf-ad-chips');
        list.innerHTML = '';
        items.forEach(function (item) {
            var chip = document.createElement('li');
            chip.className = 'wf-ad-chip';
            chip.setAttribute('data-ad-id', item.id);
            chip.innerHTML =
                '<span>' + escapeHtml(item.name) + (item.enabled === '0' ? ' (off)' : '') + '</span>' +
                '<button type="button" class="wf-ad-chip-remove" aria-label="Remove ad">&times;</button>' +
                '<input type="hidden" name="placement_map[' + placement + '][]" value="' + escapeHtml(item.id) + '">';
            list.appendChild(chip);
        });
        refreshAddOptions(multi);
    }

    function syncPlacement(placement, items) {
        document.querySelectorAll('.wf-ad-multi[data-placement="' + placement + '"]').forEach(function (multi) {
            renderChips(multi, items);
        });
    }

    function addChip(multi, adId, adName, enabled) {
        if (!adId) {
            return;
        }
        var placement = multi.getAttribute('data-placement');
        var items = [];
        var seen = {};
        multi.querySelectorAll('.wf-ad-chip').forEach(function (chip) {
            var id = chip.getAttribute('data-ad-id');
            if (id && !seen[id]) {
                seen[id] = true;
                items.push({
                    id: id,
                    name: chip.querySelector('span').textContent.replace(/ \(off\)$/, ''),
                    enabled: chip.querySelector('span').textContent.indexOf('(off)') !== -1 ? '0' : '1'
                });
            }
        });
        if (seen[adId]) {
            return;
        }
        items.push({ id: adId, name: adName, enabled: enabled });
        syncPlacement(placement, items);
    }

    function removeChip(multi, adId) {
        var placement = multi.getAttribute('data-placement');
        var items = [];
        multi.querySelectorAll('.wf-ad-chip').forEach(function (chip) {
            var id = chip.getAttribute('data-ad-id');
            if (id && id !== adId) {
                items.push({
                    id: id,
                    name: chip.querySelector('span').textContent.replace(/ \(off\)$/, ''),
                    enabled: chip.querySelector('span').textContent.indexOf('(off)') !== -1 ? '0' : '1'
                });
            }
        });
        syncPlacement(placement, items);
    }

    function refreshAddOptions(multi) {
        var selected = {};
        getSelectedIds(multi).forEach(function (id) {
            selected[id] = true;
        });
        multi.querySelectorAll('.wf-ad-add option').forEach(function (opt) {
            if (!opt.value) {
                return;
            }
            opt.hidden = !!selected[opt.value];
        });
        var addSelect = multi.querySelector('.wf-ad-add');
        if (addSelect) {
            addSelect.value = '';
        }
    }

    document.querySelectorAll('.wf-ad-multi').forEach(function (multi) {
        refreshAddOptions(multi);
        var addSelect = multi.querySelector('.wf-ad-add');
        if (addSelect) {
            addSelect.addEventListener('change', function () {
                var opt = addSelect.options[addSelect.selectedIndex];
                if (!opt || !opt.value) {
                    return;
                }
                addChip(multi, opt.value, opt.getAttribute('data-name') || opt.textContent, opt.getAttribute('data-enabled') || '1');
            });
        }
        multi.addEventListener('click', function (e) {
            var btn = e.target.closest('.wf-ad-chip-remove');
            if (!btn) {
                return;
            }
            var chip = btn.closest('.wf-ad-chip');
            if (chip) {
                removeChip(multi, chip.getAttribute('data-ad-id'));
            }
        });
    });
})();
</script>
<?php endif; ?>
