<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();

use App\PlatformConfig;
use App\Security;
use App\ServiceConfig;

$pageTitle = 'SEO Pages';
$baseUrl = rtrim($config['app']['url'], '/');

/** @var array<int, array<string, mixed>> $pageRows */
$pageRows = [];

$serviceGroups = [
    ServiceConfig::SERVICE_VIDEO => [
        'platforms' => $videoPlatforms,
        'all' => $allVideoPlatforms,
        'type' => 'video',
        'settings_page' => 'providers.php',
        'folder' => 'app/video-provider',
    ],
    ServiceConfig::SERVICE_AUDIO => [
        'platforms' => $audioPlatforms,
        'all' => $allAudioPlatforms,
        'type' => 'audio',
        'settings_page' => 'audio-providers.php',
        'folder' => 'app/audio-provider',
    ],
];

foreach ($serviceGroups as $serviceId => $group) {
    if (!ServiceConfig::isServiceEnabled($settings, $serviceId)) {
        continue;
    }

    $serviceLabel = ServiceConfig::serviceLabel($serviceId, $settings);

    foreach ($group['all'] as $providerId => $manifest) {
        $assigned = ServiceConfig::isProviderAssigned($settings, $serviceId, (string) $providerId);
        $enabled = PlatformConfig::isEnabled($settings, (string) $providerId, $group['type']);
        $merged = $group['platforms'][$providerId] ?? null;
        $ps = PlatformConfig::providerSettings($settings, (string) $providerId, $group['type']);

        if (!$assigned) {
            continue;
        }

        $slug = (string) ($merged['slug'] ?? $ps['slug'] ?? $manifest['slug'] ?? $providerId);
        $title = (string) ($merged['title'] ?? $ps['title'] ?? $manifest['title'] ?? $manifest['name'] ?? ucfirst($providerId));

        $pageRows[] = [
            'service_id' => $serviceId,
            'service_label' => $serviceLabel,
            'provider_id' => (string) $providerId,
            'name' => (string) ($manifest['name'] ?? ucfirst($providerId)),
            'title' => $title,
            'slug' => $slug,
            'url' => $baseUrl . '/' . $slug,
            'enabled' => $enabled && $merged !== null,
            'folder' => (string) ($manifest['folder'] ?? $providerId),
            'plugin_folder' => $group['folder'],
            'settings_page' => $group['settings_page'],
        ];
    }
}

usort($pageRows, static function (array $a, array $b): int {
    $service = strcmp($a['service_label'], $b['service_label']);
    return $service !== 0 ? $service : strcmp($a['name'], $b['name']);
});

require __DIR__ . '/layout/header.php';
?>

<h1>SEO Pages</h1>
<p class="admin-note">
    Public landing pages generated from each provider's <code>manifest.php</code> using
    <code>templates/platform.php</code>. Listed below are all providers assigned to
    <strong>enabled services</strong> (video and audio). Edit SEO in
    <a href="providers.php">Video Providers</a> or <a href="audio-providers.php">Audio Providers</a>.
</p>

<?php if ($pageRows === []): ?>
<p class="admin-error">No SEO pages available. Enable at least one provider under <a href="providers.php">Video Providers</a> or <a href="audio-providers.php">Audio Providers</a>.</p>
<?php else: ?>

<table class="admin-table">
    <thead>
        <tr>
            <th>Service</th>
            <th>Provider</th>
            <th>Page title (SEO)</th>
            <th>Slug</th>
            <th>Plugin folder</th>
            <th>Status</th>
            <th>URL</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($pageRows as $row): ?>
    <tr<?= $row['enabled'] ? '' : ' style="opacity:.55"' ?>>
        <td><?= Security::escape($row['service_label']) ?></td>
        <td>
            <a href="<?= Security::escape($row['settings_page']) ?>?tab=<?= Security::escape($row['provider_id']) ?>">
                <?= Security::escape($row['name']) ?>
            </a>
        </td>
        <td><?= Security::escape($row['title']) ?></td>
        <td><code><?= Security::escape($row['slug']) ?></code></td>
        <td><code><?= Security::escape($row['plugin_folder']) ?>/<?= Security::escape($row['folder']) ?>/</code></td>
        <td><?= $row['enabled'] ? 'Live' : 'Disabled' ?></td>
        <td>
            <?php if ($row['enabled']): ?>
            <a href="<?= Security::escape($row['url']) ?>" target="_blank" rel="noopener noreferrer">View</a>
            <?php else: ?>
            <span class="admin-note">—</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php
$videoCount = count(array_filter($pageRows, static fn(array $r): bool => $r['service_id'] === ServiceConfig::SERVICE_VIDEO && $r['enabled']));
$audioCount = count(array_filter($pageRows, static fn(array $r): bool => $r['service_id'] === ServiceConfig::SERVICE_AUDIO && $r['enabled']));
?>
<p class="admin-note">
    <?= count($pageRows) ?> page(s) total —
    <?= $videoCount ?> live video,
    <?= $audioCount ?> live audio.
    Enable providers under <a href="providers.php">Video Providers</a> and <a href="audio-providers.php">Audio Providers</a>.
</p>

<?php endif; ?>

<?php require __DIR__ . '/layout/footer.php'; ?>
