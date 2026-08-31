<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();

use App\Repositories\PageSeoRepository;
use App\Security;
use App\ServiceConfig;
use App\Storage\DatabaseConnection;

$pageTitle = 'SEO Pages';
$baseUrl = rtrim($config['app']['url'], '/');
$message = '';
$error = '';

$pageSeoRepo = new PageSeoRepository(DatabaseConnection::get());
$allPages = $pageSeoRepo->loadAllKeyed();

$editKey = trim((string) ($_GET['edit'] ?? ''));
if ($editKey === '' && $allPages !== []) {
    $editKey = 'home';
}
if ($editKey !== '' && !isset($allPages[$editKey])) {
    $editKey = array_key_first($allPages) ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {
        $error = 'Invalid CSRF token.';
    } else {
        $saveKey = trim((string) ($_POST['page_key'] ?? ''));
        if ($saveKey === '' || !isset($allPages[$saveKey])) {
            $error = 'Invalid page.';
        } elseif ($pageSeoRepo->save($saveKey, [
            'page_label' => (string) ($_POST['page_label'] ?? ''),
            'title' => (string) ($_POST['title'] ?? ''),
            'h1' => (string) ($_POST['h1'] ?? ''),
            'meta_description' => (string) ($_POST['meta_description'] ?? ''),
            'description' => (string) ($_POST['description'] ?? ''),
            'keywords' => (string) ($_POST['keywords'] ?? ''),
            'og_image' => (string) ($_POST['og_image'] ?? ''),
            'robots' => (string) ($_POST['robots'] ?? 'index, follow'),
            'seo_content' => (string) ($_POST['seo_content'] ?? ''),
        ])) {
            $message = 'SEO settings saved for ' . Security::escape($allPages[$saveKey]['page_label'] ?? $saveKey) . '.';
            $allPages = $pageSeoRepo->loadAllKeyed();
            $editKey = $saveKey;
        } else {
            $error = 'Could not save SEO settings.';
        }
    }
}

$editPage = $editKey !== '' ? ($allPages[$editKey] ?? null) : null;

/** @var array<int, array<string, mixed>> $providerRows */
$providerRows = [];
$serviceGroups = [
    ServiceConfig::SERVICE_VIDEO => [
        'platforms' => $videoPlatforms,
        'all' => $allVideoPlatforms,
        'type' => 'video',
        'settings_page' => 'providers.php',
    ],
    ServiceConfig::SERVICE_AUDIO => [
        'platforms' => $audioPlatforms,
        'all' => $allAudioPlatforms,
        'type' => 'audio',
        'settings_page' => 'audio-providers.php',
    ],
];

foreach ($serviceGroups as $serviceId => $group) {
    if (!ServiceConfig::isServiceEnabled($settings, $serviceId)) {
        continue;
    }
    $serviceLabel = ServiceConfig::serviceLabel($serviceId, $settings);
    foreach ($group['all'] as $providerId => $manifest) {
        if (!ServiceConfig::isProviderAssigned($settings, $serviceId, (string) $providerId)) {
            continue;
        }
        $merged = $group['platforms'][$providerId] ?? null;
        $enabled = $merged !== null && \App\PlatformConfig::isEnabled($settings, (string) $providerId, $group['type']);
        $slug = (string) ($merged['slug'] ?? $manifest['slug'] ?? $providerId);
        $providerRows[] = [
            'service_label' => $serviceLabel,
            'name' => (string) ($manifest['name'] ?? ucfirst((string) $providerId)),
            'title' => (string) ($merged['title'] ?? $manifest['title'] ?? ''),
            'slug' => $slug,
            'url' => $baseUrl . '/' . $slug,
            'enabled' => $enabled,
            'settings_page' => $group['settings_page'] . '?tab=' . rawurlencode((string) $providerId),
        ];
    }
}

require __DIR__ . '/layout/header.php';
?>

<h1>SEO Pages</h1>
<p class="admin-note">
    Edit titles, meta descriptions, keywords, and Open Graph settings for every public page.
    Platform landing pages (YouTube, TikTok, etc.) are edited under
    <a href="providers.php">Video Providers</a> and <a href="audio-providers.php">Audio Providers</a>.
</p>

<?php if ($message): ?><p class="admin-success"><?= $message ?></p><?php endif; ?>
<?php if ($error): ?><p class="admin-error"><?= Security::escape($error) ?></p><?php endif; ?>

<?php if ($allPages === []): ?>
<p class="admin-error">No SEO pages found. Reload the site once so the database can create the <code>page_seo</code> table.</p>
<?php else: ?>

<div class="admin-tabs">
    <?php foreach ($allPages as $key => $page): ?>
    <a href="pages.php?edit=<?= Security::escape($key) ?>" class="admin-tab<?= $key === $editKey ? ' active' : '' ?>">
        <?= Security::escape($page['page_label'] ?: $key) ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($editPage !== null): ?>
<div class="admin-tab-panel">
    <h2><?= Security::escape($editPage['page_label']) ?></h2>
    <p class="admin-note">
        Public URL:
        <a href="<?= Security::escape($baseUrl . '/' . ($editKey === 'home' ? '' : $editKey)) ?>" target="_blank" rel="noopener noreferrer">
            <?= Security::escape($baseUrl . '/' . ($editKey === 'home' ? '' : $editKey)) ?>
        </a>
    </p>

    <form method="POST" class="admin-form admin-form-wide">
        <?= Security::csrfField($config) ?>
        <input type="hidden" name="page_key" value="<?= Security::escape($editKey) ?>">

        <fieldset class="admin-fieldset">
            <legend>Search engine metadata</legend>
            <label>Admin label
                <input type="text" name="page_label" value="<?= Security::escape($editPage['page_label']) ?>">
            </label>
            <label>SEO Title <span class="hint">55–65 chars, primary keyword first</span>
                <input type="text" name="title" value="<?= Security::escape($editPage['title']) ?>" maxlength="512">
            </label>
            <label>H1 Heading
                <input type="text" name="h1" value="<?= Security::escape($editPage['h1']) ?>" maxlength="255">
            </label>
            <label>Meta Description <span class="hint">140–155 chars for Google/Yahoo snippets</span>
                <textarea name="meta_description" rows="3" maxlength="500"><?= Security::escape($editPage['meta_description']) ?></textarea>
            </label>
            <label>Hero / intro text
                <textarea name="description" rows="3"><?= Security::escape($editPage['description']) ?></textarea>
            </label>
            <label>Keywords <span class="hint">Comma-separated target phrases</span>
                <input type="text" name="keywords" value="<?= Security::escape($editPage['keywords']) ?>">
            </label>
            <label>Robots
                <select name="robots">
                    <?php foreach (['index, follow', 'noindex, follow', 'index, nofollow', 'noindex, nofollow'] as $robotsOption): ?>
                    <option value="<?= Security::escape($robotsOption) ?>"<?= ($editPage['robots'] ?? '') === $robotsOption ? ' selected' : '' ?>>
                        <?= Security::escape($robotsOption) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>OG / Twitter image URL <span class="hint">Optional — falls back to site logo</span>
                <input type="url" name="og_image" value="<?= Security::escape($editPage['og_image']) ?>" placeholder="https://example.com/og-image.jpg">
            </label>
        </fieldset>

        <?php if ($editKey === 'home'): ?>
        <fieldset class="admin-fieldset">
            <legend>Homepage SEO content block</legend>
            <p class="admin-note">HTML allowed (h2, h3, p, ul, li, strong). Shown at the bottom of the homepage for search engines.</p>
            <label>SEO body content
                <textarea name="seo_content" rows="12"><?= Security::escape($editPage['seo_content']) ?></textarea>
            </label>
        </fieldset>
        <?php else: ?>
        <input type="hidden" name="seo_content" value="<?= Security::escape($editPage['seo_content']) ?>">
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Save SEO Settings</button>
    </form>
</div>
<?php endif; ?>

<h2 class="admin-section-title">Platform landing pages</h2>
<p class="admin-note">Each provider has its own SEO fields stored in MySQL (<code>video_providers</code> / <code>audio_providers</code>).</p>

<?php if ($providerRows === []): ?>
<p class="admin-note">No platform pages assigned. Enable providers under Video or Audio Providers.</p>
<?php else: ?>
<table class="admin-table">
    <thead>
        <tr>
            <th>Service</th>
            <th>Provider</th>
            <th>SEO Title</th>
            <th>Slug</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($providerRows as $row): ?>
    <tr<?= $row['enabled'] ? '' : ' style="opacity:.55"' ?>>
        <td><?= Security::escape($row['service_label']) ?></td>
        <td><?= Security::escape($row['name']) ?></td>
        <td><?= Security::escape($row['title']) ?></td>
        <td><code>/<?= Security::escape($row['slug']) ?></code></td>
        <td><?= $row['enabled'] ? 'Live' : 'Disabled' ?></td>
        <td>
            <a href="<?= Security::escape($row['settings_page']) ?>">Edit SEO</a>
            <?php if ($row['enabled']): ?>
            · <a href="<?= Security::escape($row['url']) ?>" target="_blank" rel="noopener noreferrer">View</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/layout/footer.php'; ?>
