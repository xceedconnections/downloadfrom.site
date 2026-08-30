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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {
        $error = 'Invalid CSRF token.';
    } else {
        $currentSettings['site_name'] = trim($_POST['site_name'] ?? '');
        $currentSettings['site_url'] = rtrim(trim($_POST['site_url'] ?? ''), '/');
        $currentSettings['footer_text'] = trim($_POST['footer_text'] ?? '');
        $currentSettings['analytics_enabled'] = isset($_POST['analytics_enabled']);
        $currentSettings['maintenance_mode'] = isset($_POST['maintenance_mode']);
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

            if (!empty($_POST['new_password']) && strlen($_POST['new_password']) >= 8) {
                $adminRepo = new AdminRepository(DatabaseConnection::get());
                $adminRepo->updatePassword(password_hash($_POST['new_password'], PASSWORD_DEFAULT));
                $message .= ' Password updated.';
            }
        }
    }
}

$pageTitle = 'Site Settings';
require __DIR__ . '/layout/header.php';
?>

<h1>Site Settings</h1>
<?php if ($message): ?><p class="admin-success"><?= Security::escape($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="admin-error"><?= Security::escape($error) ?></p><?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="admin-form admin-form-wide">
    <?= Security::csrfField($config) ?>

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

    <fieldset class="admin-fieldset">
        <legend>Custom Codes</legend>
        <p class="admin-note">Paste verification or tracking snippets (Google AdSense, Monetag, Analytics, etc.). Head code is best for Monetag Vignette tags. Output on every public page as raw HTML.</p>
        <label>Head code <span class="hint">(inside &lt;head&gt;, before &lt;/head&gt;)</span>
            <textarea name="custom_codes_head" rows="8" class="code-area" spellcheck="false" placeholder="<script async src=&quot;https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-...&quot; crossorigin=&quot;anonymous&quot;></script>"><?= Security::escape($currentSettings['custom_codes']['head'] ?? '') ?></textarea>
        </label>
        <label>Body end code <span class="hint">(before &lt;/body&gt;)</span>
            <textarea name="custom_codes_body_end" rows="6" class="code-area" spellcheck="false" placeholder="Optional scripts that load at the end of the page"><?= Security::escape($currentSettings['custom_codes']['body_end'] ?? '') ?></textarea>
        </label>
    </fieldset>

    <fieldset class="admin-fieldset">
        <legend>Change Password</legend>
        <label>New Password (min 8 chars)
            <input type="password" name="new_password" autocomplete="new-password">
        </label>
    </fieldset>

    <button type="submit" class="btn btn-primary">Save Settings</button>
</form>

<p class="admin-note">Per-provider proxy, SEO, and channel blocks are in <a href="providers.php">Provider Settings</a>.</p>

<?php require __DIR__ . '/layout/footer.php'; ?>
