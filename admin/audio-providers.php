<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();

use App\PlatformConfig;
use App\Security;

$message = '';
$error = '';
$allPlatforms = $allAudioPlatforms;
$settingsKey = 'audio_providers';
$serviceType = 'audio';
$folderLabel = 'app/audio-provider';

$currentSettings = $settings->all();
$providerSettings = is_array($currentSettings[$settingsKey] ?? null) ? $currentSettings[$settingsKey] : [];

$activeTab = $_GET['tab'] ?? array_key_first($allPlatforms) ?? 'youtube';
if (!isset($allPlatforms[$activeTab])) {
    $activeTab = array_key_first($allPlatforms) ?? 'youtube';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {
        $error = 'Invalid CSRF token.';
    } else {
        $saveTab = trim($_POST['save_tab'] ?? $activeTab);
        if (!isset($allPlatforms[$saveTab])) {
            $error = 'Invalid provider.';
        } else {
            $posted = $_POST['audio_providers'][$saveTab] ?? [];
            $blockedRaw = trim((string) ($posted['blocked_channels_text'] ?? ''));
            $blockedLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $blockedRaw) ?: [])));

            $providerSettings[$saveTab] = [
                'enabled' => isset($posted['enabled']),
                'show_as_new' => isset($posted['show_as_new']),
                'proxy_enabled' => isset($posted['proxy_enabled']),
                'title' => trim((string) ($posted['title'] ?? '')),
                'h1' => trim((string) ($posted['h1'] ?? '')),
                'meta_description' => trim((string) ($posted['meta_description'] ?? '')),
                'description' => trim((string) ($posted['description'] ?? '')),
                'keywords' => trim((string) ($posted['keywords'] ?? '')),
                'slug' => trim((string) ($posted['slug'] ?? '')),
                'blocked_channels' => $blockedLines,
            ];

            $currentSettings[$settingsKey] = $providerSettings;
            $settings->save($currentSettings);
            $message = ucfirst($allPlatforms[$saveTab]['name']) . ' audio settings saved.';
            $activeTab = $saveTab;
        }
    }
}

$currentSettings = $settings->all();
$providerSettings = is_array($currentSettings[$settingsKey] ?? null) ? $currentSettings[$settingsKey] : [];

$pageTitle = 'Audio Provider Settings';
require __DIR__ . '/layout/header.php';
?>

<h1>Audio Provider Settings</h1>
<p class="admin-note">Configure MP3/audio downloaders — proxy mode, SEO, blocked channels. Plugins live in <code><?= Security::escape($folderLabel) ?>/{platform}/</code>.</p>

<?php if ($message): ?><p class="admin-success"><?= Security::escape($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="admin-error"><?= Security::escape($error) ?></p><?php endif; ?>

<div class="admin-tabs">
    <?php foreach ($allPlatforms as $id => $platform): ?>
    <a href="audio-providers.php?tab=<?= Security::escape($id) ?>" class="admin-tab<?= $id === $activeTab ? ' active' : '' ?>">
        <?= Security::escape($platform['name']) ?>
    </a>
    <?php endforeach; ?>
</div>

<?php
$tabPlatform = $allPlatforms[$activeTab];
$ps = $providerSettings[$activeTab] ?? [];
$merged = PlatformConfig::applyOverrides($tabPlatform, $settings, $activeTab, $serviceType);
$blockedText = implode("\n", PlatformConfig::blockedChannels($settings, $activeTab, $serviceType));
$proxyOn = array_key_exists('proxy_enabled', $ps) ? !empty($ps['proxy_enabled']) : !empty($config['download']['proxy_enabled']);
$enabledOn = !isset($ps['enabled']) || $ps['enabled'] !== false;
$showAsNewOn = !empty($ps['show_as_new']);
?>

<div class="admin-tab-panel">
    <h2><?= Security::escape($tabPlatform['name']) ?> MP3 Downloader</h2>
    <p class="admin-note">Plugin folder: <code><?= Security::escape($folderLabel) ?>/<?= Security::escape($tabPlatform['folder'] ?? $activeTab) ?>/</code></p>

    <form method="POST" class="admin-form admin-form-wide">
        <?= Security::csrfField($config) ?>
        <input type="hidden" name="save_tab" value="<?= Security::escape($activeTab) ?>">

        <fieldset class="admin-fieldset">
            <legend>General</legend>
            <label class="checkbox">
                <input type="checkbox" name="audio_providers[<?= Security::escape($activeTab) ?>][enabled]" <?= $enabledOn ? 'checked' : '' ?>>
                Enable this audio provider on the site
            </label>
            <label class="checkbox">
                <input type="checkbox" name="audio_providers[<?= Security::escape($activeTab) ?>][show_as_new]" <?= $showAsNewOn ? 'checked' : '' ?>>
                Show <strong>New</strong> badge on header, homepage, and footer
            </label>
            <label class="checkbox">
                <input type="checkbox" name="audio_providers[<?= Security::escape($activeTab) ?>][proxy_enabled]" <?= $proxyOn ? 'checked' : '' ?>>
                Stream downloads through server (uses your bandwidth). Uncheck for direct CDN links.
            </label>
        </fieldset>

        <fieldset class="admin-fieldset">
            <legend>SEO &amp; Landing Page</legend>
            <label>Page slug <span class="hint">URL: /<?= Security::escape($merged['slug']) ?></span>
                <input type="text" name="audio_providers[<?= Security::escape($activeTab) ?>][slug]" value="<?= Security::escape($ps['slug'] ?? $tabPlatform['slug']) ?>" placeholder="<?= Security::escape($tabPlatform['slug']) ?>">
            </label>
            <label>SEO Title
                <input type="text" name="audio_providers[<?= Security::escape($activeTab) ?>][title]" value="<?= Security::escape($ps['title'] ?? $tabPlatform['title'] ?? '') ?>">
            </label>
            <label>H1 Heading
                <input type="text" name="audio_providers[<?= Security::escape($activeTab) ?>][h1]" value="<?= Security::escape($ps['h1'] ?? $tabPlatform['h1'] ?? '') ?>">
            </label>
            <label>Meta Description
                <textarea name="audio_providers[<?= Security::escape($activeTab) ?>][meta_description]" rows="3"><?= Security::escape($ps['meta_description'] ?? $tabPlatform['meta_description'] ?? '') ?></textarea>
            </label>
            <label>Page Description
                <textarea name="audio_providers[<?= Security::escape($activeTab) ?>][description]" rows="4"><?= Security::escape($ps['description'] ?? $tabPlatform['description'] ?? '') ?></textarea>
            </label>
            <label>Keywords
                <input type="text" name="audio_providers[<?= Security::escape($activeTab) ?>][keywords]" value="<?= Security::escape($ps['keywords'] ?? $tabPlatform['keywords'] ?? '') ?>">
            </label>
        </fieldset>

        <fieldset class="admin-fieldset">
            <legend>Blocked Channels / Creators</legend>
            <label>Block list
                <textarea name="audio_providers[<?= Security::escape($activeTab) ?>][blocked_channels_text]" rows="8" placeholder="Artist Name&#10;@channel"><?= Security::escape($blockedText) ?></textarea>
            </label>
        </fieldset>

        <button type="submit" class="btn btn-primary">Save <?= Security::escape($tabPlatform['name']) ?> Audio Settings</button>
    </form>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
