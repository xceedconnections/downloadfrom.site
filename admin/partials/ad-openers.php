<?php

declare(strict_types=1);

use App\AdManager;
use App\Security;

/** @var App\AdManager $adManager */
/** @var array $config */
/** @var string $message */
/** @var string $error */

$openerSettings = $adManager->getDownloadOpenerSettings();
$openers = $adManager->allOpenerAds();
$openerAction = $_GET['opener_action'] ?? 'list';
$editOpenerId = $_GET['opener_id'] ?? '';
$editOpener = $editOpenerId !== '' ? $adManager->getAd($editOpenerId) : null;
if ($editOpener !== null && ($editOpener['type'] ?? '') !== 'download_opener') {
    $editOpener = null;
}
?>

<div class="admin-tab-panel">
    <h2>Download Link Openers</h2>
    <p class="admin-note">These URLs open in new tab(s) when a visitor clicks <strong>Download</strong> or <strong>Download Video Now</strong>. Assign openers to the <strong>Opener</strong> zone on result pages in <a href="ads.php?tab=map">Placement Map</a>.</p>

    <form method="POST" class="admin-form admin-form-wide" style="margin-bottom:1.5rem">
        <?= Security::csrfField($config) ?>
        <input type="hidden" name="save_opener_settings" value="1">
        <fieldset class="admin-fieldset">
            <legend>Opener behavior</legend>
            <label>Open mode
                <select name="download_opener_mode">
                    <option value="random" <?= $openerSettings['mode'] === 'random' ? 'selected' : '' ?>>Random — pick 1 link from assigned openers each click</option>
                    <option value="multiple" <?= $openerSettings['mode'] === 'multiple' ? 'selected' : '' ?>>Multiple — open several links at once (see count below)</option>
                </select>
            </label>
            <label>Links to open per download (multiple mode only)
                <select name="download_opener_count">
                    <option value="1" <?= $openerSettings['count'] === 1 ? 'selected' : '' ?>>1 opener link</option>
                    <option value="2" <?= $openerSettings['count'] === 2 ? 'selected' : '' ?>>2 opener links</option>
                    <option value="3" <?= $openerSettings['count'] === 3 ? 'selected' : '' ?>>3 opener links</option>
                </select>
            </label>
            <p class="admin-field-hint">The platform download (YouTube, TikTok, etc.) always opens once. Opener links are extra tabs opened in the same click.</p>
            <button type="submit" class="btn btn-primary">Save opener settings</button>
        </fieldset>
    </form>

    <?php if ($openerAction === 'create' || $openerAction === 'edit'): ?>
    <?php
        $o = $editOpener ?? [
            'name' => '',
            'enabled' => true,
            'priority' => 0,
            'content' => ['link_url' => ''],
        ];
        $oc = $o['content'] ?? [];
    ?>
    <h3><?= $openerAction === 'edit' ? 'Edit opener link' : 'Add opener link' ?></h3>
    <form method="POST" class="admin-form admin-form-wide">
        <?= Security::csrfField($config) ?>
        <input type="hidden" name="save_opener_ad" value="1">
        <input type="hidden" name="opener_ad_id" value="<?= Security::escape($o['id'] ?? '') ?>">
        <fieldset class="admin-fieldset">
            <label>Internal name
                <input type="text" name="opener_name" required value="<?= Security::escape($o['name'] ?? '') ?>">
            </label>
            <label class="checkbox"><input type="checkbox" name="opener_enabled" <?= !isset($o['enabled']) || $o['enabled'] ? 'checked' : '' ?>> Active</label>
            <label>Destination URL
                <input type="url" name="opener_url" required value="<?= Security::escape($oc['link_url'] ?? '') ?>" placeholder="https://example.com/offer">
            </label>
            <label>Priority (higher = preferred when multiple open)
                <input type="number" name="opener_priority" value="<?= (int) ($o['priority'] ?? 0) ?>">
            </label>
        </fieldset>
        <button type="submit" class="btn btn-primary">Save opener</button>
        <a href="ads.php?tab=openers" class="btn btn-secondary" style="margin-left:.5rem">Cancel</a>
    </form>

    <?php else: ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
        <h3>Opener links (<?= count($openers) ?>)</h3>
        <a href="ads.php?tab=openers&amp;opener_action=create" class="btn btn-primary">+ Add opener link</a>
    </div>

    <?php if ($openers === []): ?>
    <p>No opener links yet. <a href="ads.php?tab=openers&amp;opener_action=create">Add your first opener</a>, then assign it in Placement Map → result page → Opener.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr><th>Name</th><th>URL</th><th>Used in zones</th><th>Priority</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($openers as $ad):
            $aid = (string) ($ad['id'] ?? '');
            $linkUrl = trim((string) ($ad['content']['link_url'] ?? ''));
            $usedIn = [];
            foreach ($adManager->getPlacementMap() as $place => $mappedIds) {
                if (!is_array($mappedIds)) {
                    $mappedIds = [$mappedIds];
                }
                if (!in_array($aid, $mappedIds, true)) {
                    continue;
                }
                $parsed = AdManager::parsePlacementMapKey($place);
                if (($parsed['placement'] ?? '') !== 'download_link_opener') {
                    continue;
                }
                $label = 'Opener';
                if ($parsed['page_scope'] !== null) {
                    $label = ucfirst((string) $parsed['page_scope']) . ': ' . $label;
                }
                if ($parsed['provider_id'] !== null) {
                    $label .= ' (' . $parsed['provider_id'] . ')';
                } elseif ($parsed['service_id'] !== null) {
                    $label .= ' (' . $parsed['service_id'] . ')';
                }
                $usedIn[] = $label;
            }
        ?>
        <tr>
            <td><?= Security::escape($ad['name'] ?? '') ?></td>
            <td><?php if ($linkUrl !== ''): ?><a href="<?= Security::escape($linkUrl) ?>" target="_blank" rel="noopener noreferrer"><?= Security::escape(parse_url($linkUrl, PHP_URL_HOST) ?: $linkUrl) ?></a><?php else: ?><span class="admin-error-inline">Missing URL</span><?php endif; ?></td>
            <td><?= Security::escape($usedIn !== [] ? implode(', ', $usedIn) : '— assign in Placement Map') ?></td>
            <td><?= (int) ($ad['priority'] ?? 0) ?></td>
            <td><?= !empty($ad['enabled']) ? 'Active' : 'Disabled' ?></td>
            <td>
                <a href="ads.php?tab=openers&amp;opener_action=edit&amp;opener_id=<?= Security::escape($aid) ?>">Edit</a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this opener link?')">
                    <?= Security::csrfField($config) ?>
                    <input type="hidden" name="delete_opener_ad" value="1">
                    <input type="hidden" name="opener_ad_id" value="<?= Security::escape($aid) ?>">
                    <button type="submit" class="btn-link danger">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php endif; ?>
</div>
