<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();

use App\PlatformConfig;
use App\Repositories\AdminRepository;
use App\Security;
use App\Storage\DatabaseConnection;
use App\UploadHelper;

$message = '';
$error = '';
$currentSettings = $settings->all();
$baseUrl = rtrim($config['app']['url'], '/');
$logoUrl = PlatformConfig::logoUrl($settings, $baseUrl);
$tab = $_GET['tab'] ?? 'general';
$allowedTabs = ['general', 'ads', 'codes', 'security'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'general';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {
        $error = 'Invalid CSRF token.';
    } else {
        $currentSettings['site_name'] = trim($_POST['site_name'] ?? '');
        $currentSettings['site_url'] = rtrim(trim($_POST['site_url'] ?? ''), '/');
        $currentSettings['footer_text'] = trim($_POST['footer_text'] ?? '');
        $currentSettings['analytics_enabled'] = isset($_POST['analytics_enabled']);
        $currentSettings['maintenance_mode'] = isset($_POST['maintenance_mode']);
        $currentSettings['ads_block_adblock'] = ($_POST['ads_block_adblock'] ?? '1') === '1';
        $currentSettings['admin_email'] = trim($_POST['admin_email'] ?? '');
        $currentSettings['custom_codes'] = [
            'head' => trim($_POST['custom_codes_head'] ?? ''),
            'body_end' => trim($_POST['custom_codes_body_end'] ?? ''),
        ];

        if (!empty($_FILES['logo']['tmp_name'])) {
            $upload = UploadHelper::storeLogo($_FILES['logo'], dirname(__DIR__));
            if ($upload['success']) {
                $currentSettings['logo_path'] = $upload['path'];
            } else {
                $error = match ($upload['error'] ?? '') {
                    'too_large' => 'Logo must be under 2 MB.',
                    'invalid_type' => 'Logo must be PNG, JPG, WebP, GIF, or SVG.',
                    default => 'Logo upload failed.',
                };
            }
        }

        if ($error === '') {
            $settings->save($currentSettings);
            $message = 'Settings saved.';
            $logoUrl = PlatformConfig::logoUrl($settings, $baseUrl);
            $tab = trim((string) ($_POST['settings_tab'] ?? $tab));
            if (!in_array($tab, $allowedTabs, true)) {
                $tab = 'general';
            }

            if (!empty($_POST['new_password']) && strlen($_POST['new_password']) >= 8) {
                $adminRepo = new AdminRepository(DatabaseConnection::get());
                $adminRepo->updatePassword(password_hash($_POST['new_password'], PASSWORD_DEFAULT));
                $message .= ' Password updated.';
            }
        }
    }
}

$blockAdblock = !empty($currentSettings['ads_block_adblock']);
$pageTitle = 'Site Settings';
require __DIR__ . '/layout/header.php';
?>

<h1>Site Settings</h1>
<?php if ($message): ?><p class="admin-success"><?= Security::escape($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="admin-error"><?= Security::escape($error) ?></p><?php endif; ?>

<div class="admin-tabs">
    <a href="settings.php?tab=general" class="admin-tab<?= $tab === 'general' ? ' active' : '' ?>">General</a>
    <a href="settings.php?tab=ads" class="admin-tab<?= $tab === 'ads' ? ' active' : '' ?>">Ads &amp; Access</a>
    <a href="settings.php?tab=codes" class="admin-tab<?= $tab === 'codes' ? ' active' : '' ?>">Custom Codes</a>
    <a href="settings.php?tab=security" class="admin-tab<?= $tab === 'security' ? ' active' : '' ?>">Security</a>
</div>

<form method="POST" enctype="multipart/form-data" class="admin-form admin-form-wide">
    <?= Security::csrfField($config) ?>
    <input type="hidden" name="settings_tab" value="<?= Security::escape($tab) ?>">
    <input type="hidden" name="ads_block_adblock" id="ads-block-adblock-value" value="<?= $blockAdblock ? '1' : '0' ?>">

    <div class="admin-tab-panel"<?= $tab !== 'general' ? ' hidden' : '' ?>>
        <fieldset class="admin-fieldset">
            <legend>Branding</legend>
            <label>Site Name
                <input type="text" name="site_name" value="<?= Security::escape($currentSettings['site_name'] ?? '') ?>">
            </label>
            <label>Site URL
                <input type="url" name="site_url" value="<?= Security::escape($currentSettings['site_url'] ?? '') ?>">
            </label>
            <label>Footer Text
                <textarea name="footer_text" rows="4"><?= Security::escape($currentSettings['footer_text'] ?? '') ?></textarea>
            </label>
            <label>Site Logo
                <?php if ($logoUrl): ?>
                <div class="logo-preview"><img src="<?= Security::escape($logoUrl) ?>" alt="Current logo" height="48"></div>
                <?php endif; ?>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">
                <span class="hint">PNG, JPG, WebP, GIF or SVG — max 2 MB</span>
            </label>
        </fieldset>

        <fieldset class="admin-fieldset">
            <legend>General</legend>
            <label>Admin Email
                <input type="email" name="admin_email" value="<?= Security::escape($currentSettings['admin_email'] ?? '') ?>">
            </label>
            <label class="checkbox"><input type="checkbox" name="analytics_enabled" <?= !empty($currentSettings['analytics_enabled']) ? 'checked' : '' ?>> Enable Analytics</label>
            <label class="checkbox"><input type="checkbox" name="maintenance_mode" <?= !empty($currentSettings['maintenance_mode']) ? 'checked' : '' ?>> Maintenance Mode</label>
        </fieldset>
    </div>

    <div class="admin-tab-panel"<?= $tab !== 'ads' ? ' hidden' : '' ?>>
        <fieldset class="admin-fieldset">
            <legend>Ad Blocker Gate</legend>
            <p class="admin-note">When enabled, visitors with an ad blocker see a full-screen notice and cannot use the downloader until they disable it. Leave disabled when using pop/vignette script tags in Custom Codes below.</p>
            <div class="settings-radio-group">
                <label class="settings-radio-card">
                    <input type="radio" name="ads_block_adblock_ui" value="1" <?= $blockAdblock ? 'checked' : '' ?> data-ads-block-value="1">
                    <strong>Active</strong>
                    <span>Block the website when an ad blocker is detected</span>
                </label>
                <label class="settings-radio-card">
                    <input type="radio" name="ads_block_adblock_ui" value="0" <?= !$blockAdblock ? 'checked' : '' ?> data-ads-block-value="0">
                    <strong>Inactive</strong>
                    <span>Do not block — allow everyone to use the site</span>
                </label>
            </div>
        </fieldset>

        <fieldset class="admin-fieldset">
            <legend>Ad Management</legend>
            <p class="admin-note">Create ads and assign them to page zones in the ads admin area.</p>
            <p><a href="ads.php?tab=ads" class="btn btn-secondary">Manage Ads</a>
            <a href="ads.php?tab=map" class="btn btn-secondary" style="margin-left:.5rem">Placement Map</a></p>
        </fieldset>
    </div>

    <div class="admin-tab-panel"<?= $tab !== 'codes' ? ' hidden' : '' ?>>
        <fieldset class="admin-fieldset">
            <legend>Custom Codes</legend>
            <p class="admin-note">Put pop/vignette script tags in <strong>Body scripts</strong> below. Vignette loads first; popunder tags wait until after the first click so desktop shows the same modal as mobile. Keep pop tags here only — do not duplicate them in Ad zones.</p>
            <label>Head code <span class="hint">(inside &lt;head&gt; — analytics, meta tags)</span>
                <textarea name="custom_codes_head" rows="8" class="code-area" spellcheck="false" placeholder="<script async src=&quot;https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-...&quot; crossorigin=&quot;anonymous&quot;></script>"><?= Security::escape($currentSettings['custom_codes']['head'] ?? '') ?></textarea>
            </label>
            <label>Body scripts <span class="hint">(top of &lt;body&gt; — pop/vignette tags)</span>
                <textarea name="custom_codes_body_end" rows="6" class="code-area" spellcheck="false" placeholder="Pop/vignette / popunder script tags"><?= Security::escape($currentSettings['custom_codes']['body_end'] ?? '') ?></textarea>
            </label>
            <p class="admin-field-hint">Vignette modal shows on first click. Popunder tabs load after that click — allow popups for this site in Chrome/Firefox. Firefox Enhanced Tracking Protection may still block some ad domains on desktop.</p>
        </fieldset>
    </div>

    <div class="admin-tab-panel"<?= $tab !== 'security' ? ' hidden' : '' ?>>
        <fieldset class="admin-fieldset">
            <legend>Change Password</legend>
            <label>New Password (min 8 chars)
                <input type="password" name="new_password" autocomplete="new-password">
            </label>
        </fieldset>
    </div>

    <button type="submit" class="btn btn-primary">Save Settings</button>
</form>

<p class="admin-note">Per-provider proxy, SEO, and channel blocks are in <a href="providers.php">Provider Settings</a>.</p>

<script>
(function () {
    var hidden = document.getElementById('ads-block-adblock-value');
    if (!hidden) {
        return;
    }
    document.querySelectorAll('input[name="ads_block_adblock_ui"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (radio.checked) {
                hidden.value = radio.getAttribute('data-ads-block-value') || radio.value;
            }
        });
    });
    var form = hidden.closest('form');
    if (form) {
        form.addEventListener('submit', function () {
            var checked = document.querySelector('input[name="ads_block_adblock_ui"]:checked');
            if (checked) {
                hidden.value = checked.getAttribute('data-ads-block-value') || checked.value;
            }
        });
    }
})();
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
