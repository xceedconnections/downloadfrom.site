<?php



declare(strict_types=1);



require __DIR__ . '/init.php';

$auth->requireAuth();



$pageTitle = 'Platforms';

require __DIR__ . '/layout/header.php';



use App\PlatformConfig;

use App\Security;

?>



<h1>Platform Overview</h1>

<p>Active platforms on the public site. Full configuration is in <a href="providers.php">Provider Settings</a>.</p>



<table class="admin-table">

    <thead><tr><th>Name</th><th>Folder</th><th>Slug</th><th>Proxy</th><th>Download</th></tr></thead>

    <tbody>

    <?php foreach ($allPlatforms as $key => $p):

        $ps = PlatformConfig::providerSettings($settings, $key);

        $enabled = !isset($ps['enabled']) || $ps['enabled'] !== false;

        $proxy = PlatformConfig::isProxyEnabled($settings, $key, $config);

    ?>

    <tr<?= $enabled ? '' : ' style="opacity:.5"' ?>>

        <td><?= Security::escape($p['name']) ?><?= $enabled ? '' : ' (disabled)' ?></td>

        <td><code>app/provider/<?= Security::escape($p['folder'] ?? $key) ?>/</code></td>

        <td><?= Security::escape($platforms[$key]['slug'] ?? $p['slug']) ?></td>

        <td><?= $proxy ? 'Server proxy' : 'Direct CDN' ?></td>

        <td><?= !empty($p['download_supported']) ? 'Yes' : 'No' ?></td>

    </tr>

    <?php endforeach; ?>

    </tbody>

</table>



<?php require __DIR__ . '/layout/footer.php'; ?>

