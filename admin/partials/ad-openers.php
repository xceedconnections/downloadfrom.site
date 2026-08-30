<?php

declare(strict_types=1);

use App\AdManager;
use App\Security;

/** @var App\AdManager $adManager */
/** @var array $config */
/** @var string $message */
/** @var string $error */

$containers = $adManager->getOpenerContainers();
$editId = trim((string) ($_GET['container_id'] ?? ''));
$editContainer = null;
if ($editId !== '') {
    foreach ($containers as $container) {
        if (($container['id'] ?? '') === $editId) {
            $editContainer = $container;
            break;
        }
    }
}
$form = $editContainer ?? [
    'id' => '',
    'name' => '',
    'enabled' => true,
    'mode' => 'random',
    'links' => [],
];
$formLinks = implode("\n", $form['links'] ?? []);
?>

<div class="admin-tab-panel">
    <h2>Download Link Openers</h2>
    <p class="admin-note">Add a <strong>container</strong> with one or more links. When a visitor clicks <strong>Download</strong> or <strong>Download Video Now</strong>, your opener link(s) open in new tab(s) <em>and</em> the YouTube/TikTok (or other) download starts — on every result page.</p>

    <form method="POST" class="admin-form admin-form-wide opener-container-form">
        <?= Security::csrfField($config) ?>
        <input type="hidden" name="save_opener_container" value="1">
        <input type="hidden" name="container_id" value="<?= Security::escape($form['id'] ?? '') ?>">

        <fieldset class="admin-fieldset">
            <legend><?= $editContainer ? 'Edit container' : 'Add container' ?></legend>

            <label>Container name
                <input type="text" name="container_name" required value="<?= Security::escape($form['name'] ?? '') ?>" placeholder="e.g. Offer page">
            </label>

            <fieldset class="admin-fieldset opener-mode-fieldset">
                <legend>How to open links</legend>
                <label class="radio-row">
                    <input type="radio" name="container_mode" value="fixed" <?= ($form['mode'] ?? 'random') === 'fixed' ? 'checked' : '' ?>>
                    <span><strong>Fixed</strong> — open every link in this container</span>
                </label>
                <label class="radio-row">
                    <input type="radio" name="container_mode" value="random" <?= ($form['mode'] ?? 'random') !== 'fixed' ? 'checked' : '' ?>>
                    <span><strong>Random</strong> — open one random link from this container</span>
                </label>
            </fieldset>

            <label>Links <span class="admin-field-hint">(one URL per line — add one link or many)</span>
                <textarea name="container_links" rows="6" required placeholder="https://example.com/offer&#10;https://example.com/offer-2"><?= Security::escape($formLinks) ?></textarea>
            </label>

            <label class="checkbox">
                <input type="checkbox" name="container_enabled" <?= !isset($form['enabled']) || $form['enabled'] ? 'checked' : '' ?>>
                Active
            </label>

            <div class="admin-form-actions">
                <button type="submit" class="btn btn-primary"><?= $editContainer ? 'Save container' : 'Add container' ?></button>
                <?php if ($editContainer): ?>
                <a href="ads.php?tab=openers" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </fieldset>
    </form>

    <?php if ($containers !== []): ?>
    <h3>Your containers (<?= count($containers) ?>)</h3>
    <div class="opener-container-list">
        <?php foreach ($containers as $container): ?>
        <article class="opener-container-card">
            <header class="opener-container-head">
                <h4><?= Security::escape($container['name'] ?? 'Opener') ?></h4>
                <span class="opener-container-badge opener-container-badge-<?= Security::escape($container['mode'] ?? 'random') ?>">
                    <?= ($container['mode'] ?? 'random') === 'fixed' ? 'Fixed' : 'Random' ?>
                </span>
                <span class="opener-container-status"><?= !empty($container['enabled']) ? 'Active' : 'Off' ?></span>
            </header>
            <ul class="opener-container-links">
                <?php foreach ($container['links'] ?? [] as $link): ?>
                <li><a href="<?= Security::escape($link) ?>" target="_blank" rel="noopener noreferrer"><?= Security::escape($link) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <div class="opener-container-actions">
                <a href="ads.php?tab=openers&amp;container_id=<?= Security::escape((string) ($container['id'] ?? '')) ?>" class="btn btn-secondary btn-sm">Edit</a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this container?')">
                    <?= Security::csrfField($config) ?>
                    <input type="hidden" name="delete_opener_container" value="1">
                    <input type="hidden" name="container_id" value="<?= Security::escape((string) ($container['id'] ?? '')) ?>">
                    <button type="submit" class="btn-link danger">Delete</button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="admin-note">No containers yet. Fill in the form above and click <strong>Add container</strong>.</p>
    <?php endif; ?>
</div>

<style>
.opener-mode-fieldset { margin: 1rem 0; padding: .75rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px; }
.opener-mode-fieldset legend { font-size: .9rem; font-weight: 600; }
.radio-row { display: flex; gap: .5rem; align-items: flex-start; margin: .5rem 0; cursor: pointer; }
.radio-row input { margin-top: .2rem; }
.opener-container-list { display: grid; gap: 1rem; margin-top: 1rem; }
.opener-container-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; background: #fff; }
.opener-container-head { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; margin-bottom: .75rem; }
.opener-container-head h4 { margin: 0; flex: 1 1 auto; }
.opener-container-badge { font-size: .75rem; font-weight: 600; padding: .15rem .5rem; border-radius: 999px; background: #e0e7ff; color: #3730a3; }
.opener-container-badge-fixed { background: #dcfce7; color: #166534; }
.opener-container-status { font-size: .85rem; color: #64748b; }
.opener-container-links { margin: 0 0 .75rem; padding-left: 1.2rem; word-break: break-all; }
.opener-container-links li { margin: .25rem 0; }
.opener-container-actions { display: flex; gap: .75rem; align-items: center; }
</style>
