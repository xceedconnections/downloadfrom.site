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

    <fieldset class="admin-fieldset">
        <legend>Assign ads to placements</legend>
        <p class="admin-note">Create ads in <strong>Manage Ads</strong>, then choose which ad appears in each zone below.</p>

        <?php if ($allAds === []): ?>
        <p class="admin-error">No ads yet. <a href="ads.php?tab=ads&amp;action=create">Create an ad</a> first, then return here to assign it.</p>
        <?php else: ?>
        <table class="admin-table ad-map-assign-table">
            <thead>
                <tr>
                    <th>Placement</th>
                    <th>Location</th>
                    <th>Ad to show</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (AdManager::PLACEMENTS as $pk => $plabel): ?>
                <tr>
                    <td><code><?= Security::escape($pk) ?></code></td>
                    <td><?= Security::escape($plabel) ?></td>
                    <td>
                        <select name="placement_map[<?= Security::escape($pk) ?>]">
                            <option value="">— None —</option>
                            <?php foreach ($allAds as $ad):
                                $aid = (string) ($ad['id'] ?? '');
                                $selected = ($placementMap[$pk] ?? '') === $aid;
                            ?>
                            <option value="<?= Security::escape($aid) ?>" <?= $selected ? 'selected' : '' ?>>
                                <?= Security::escape($ad['name'] ?? $aid) ?>
                                <?= empty($ad['enabled']) ? ' (disabled)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </fieldset>

    <button type="submit" class="btn btn-primary">Save placement map</button>
</form>

<div class="ad-map-intro" style="margin-top:2rem">
    <p>Visual guide — numbered zones match the table above.</p>
</div>

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
            <div class="wf-block wf-ad wf-ad-highlight" data-placement="header_banner">
                <span class="wf-ad-num">Ad 1</span><span class="wf-ad-name">header_banner</span>
            </div>
            <div class="wf-block wf-content wf-content-tall"><span class="wf-label">Page content</span></div>
            <div class="wf-block wf-ad" data-placement="footer_banner">
                <span class="wf-ad-num">Ad 2</span><span class="wf-ad-name">footer_banner</span>
            </div>
            <div class="wf-block wf-footer"><span class="wf-label">Site footer</span></div>
            <div class="wf-popup-note">+ popup overlay (timed)</div>

            <?php elseif ($pageId === 'home'): ?>
            <div class="wf-block wf-header"><span class="wf-label">Header</span></div>
            <div class="wf-block wf-ad wf-ad-sm" data-placement="header_banner"><span class="wf-ad-num">1</span> header_banner</div>
            <div class="wf-block wf-hero">
                <div class="wf-hero-split">
                    <div class="wf-hero-left">
                        <span class="wf-label">Title + URL form</span>
                        <div class="wf-ad wf-ad-inline" data-placement="home_after_form"><span class="wf-ad-num">3</span> home_after_form</div>
                    </div>
                    <div class="wf-hero-right wf-ad wf-ad-highlight" data-placement="home_hero_sidebar">
                        <span class="wf-ad-num">Ad 2</span><span class="wf-ad-name">home_hero_sidebar</span>
                    </div>
                </div>
            </div>
            <div class="wf-block wf-ad" data-placement="home_middle"><span class="wf-ad-num">4</span> home_middle</div>
            <div class="wf-block wf-ad" data-placement="home_bottom"><span class="wf-ad-num">5</span> home_bottom</div>
            <div class="wf-block wf-ad wf-ad-sm" data-placement="footer_banner"><span class="wf-ad-num">6</span> footer_banner</div>

            <?php elseif ($pageId === 'platform'): ?>
            <div class="wf-block wf-header"><span class="wf-label">Header</span></div>
            <div class="wf-block wf-ad wf-ad-sm" data-placement="header_banner"><span class="wf-ad-num">1</span> header_banner</div>
            <div class="wf-block wf-hero wf-hero-compact">
                <div class="wf-hero-split">
                    <div class="wf-hero-left">
                        <span class="wf-label">Platform form</span>
                        <div class="wf-ad wf-ad-inline" data-placement="platform_top"><span class="wf-ad-num">3</span> platform_top</div>
                    </div>
                    <div class="wf-hero-right wf-ad wf-ad-highlight" data-placement="platform_hero_sidebar">
                        <span class="wf-ad-num">Ad 2</span><span class="wf-ad-name">platform_hero_sidebar</span>
                    </div>
                </div>
            </div>
            <div class="wf-block wf-ad" data-placement="platform_bottom"><span class="wf-ad-num">4</span> platform_bottom</div>
            <div class="wf-block wf-ad wf-ad-sm" data-placement="footer_banner"><span class="wf-ad-num">5</span> footer_banner</div>

            <?php elseif ($pageId === 'result'): ?>
            <div class="wf-block wf-header"><span class="wf-label">Header</span></div>
            <div class="wf-block wf-ad wf-ad-sm" data-placement="header_banner"><span class="wf-ad-num">1</span> header_banner</div>
            <div class="wf-block wf-ad" data-placement="result_top"><span class="wf-ad-num">2</span> result_top</div>
            <div class="wf-block wf-result-split">
                <div class="wf-result-main"><span class="wf-label">Thumbnail + links</span></div>
                <div class="wf-result-side wf-ad wf-ad-highlight" data-placement="result_sidebar">
                    <span class="wf-ad-num">Ad 3</span><span class="wf-ad-name">result_sidebar</span>
                </div>
            </div>
            <div class="wf-block wf-ad" data-placement="result_bottom"><span class="wf-ad-num">4</span> result_bottom</div>
            <div class="wf-modal-note">+ download_modal on Download click</div>
            <div class="wf-block wf-ad wf-ad-sm" data-placement="footer_banner"><span class="wf-ad-num">5</span> footer_banner</div>
            <?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
</div>
