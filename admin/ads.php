<?php



declare(strict_types=1);



require __DIR__ . '/init.php';

$auth->requireAuth();



use App\AdManager;

use App\Security;

use App\UploadHelper;



$adManager = new AdManager($db, $config['app']['url']);

$message = '';

$error = '';

$tab = $_GET['tab'] ?? 'ads';

$action = $_GET['action'] ?? 'list';

$editId = $_GET['id'] ?? '';

$source = $_GET['source'] ?? '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {

        $error = 'Invalid CSRF token.';

    } elseif (isset($_POST['save_networks'])) {

        $settings = AdManager::defaultNetworkSettings();

        foreach (array_keys($settings) as $network) {

            $prefix = 'net_' . $network . '_';

            $settings[$network] = [

                'client_id' => trim($_POST[$prefix . 'client_id'] ?? ''),

                'slot_id' => trim($_POST[$prefix . 'slot_id'] ?? ''),

                'network_code' => trim($_POST[$prefix . 'network_code'] ?? ''),

                'width' => max(0, (int) ($_POST[$prefix . 'width'] ?? 728)),

                'height' => max(0, (int) ($_POST[$prefix . 'height'] ?? 90)),

            ];

        }

        $data = $adManager->getData();

        $data['enabled'] = isset($_POST['ads_enabled']);

        $data['download_modal_countdown'] = max(0, min(30, (int) ($_POST['download_modal_countdown'] ?? 5)));

        $data['network_settings'] = $settings;

        if ($adManager->save($data)) {

            $message = 'Network settings saved.';

            $tab = 'networks';

        }

    } elseif (isset($_POST['delete_ad'])) {

        $id = trim($_POST['ad_id'] ?? '');

        if ($id !== '' && $adManager->deleteAd($id)) {

            $message = 'Ad deleted.';

            $action = 'list';

            $tab = 'ads';

        }

    } elseif (isset($_POST['save_ad'])) {

        $id = trim($_POST['ad_id'] ?? '');

        if ($id === '') {

            $id = AdManager::generateId();

        }

        $existingAd = $adManager->getAd($id);

        $existingContent = is_array($existingAd['content'] ?? null) ? $existingAd['content'] : [];

        $projectRoot = dirname(__DIR__);

        $imageUrl = trim($_POST['image_url'] ?? '');

        if ($imageUrl === '') {

            $imageUrl = (string) ($existingContent['image_url'] ?? '');

        }

        if (!empty($_FILES['banner_upload']['tmp_name'])) {

            $upload = UploadHelper::storeAdImage($_FILES['banner_upload'], $projectRoot);

            if ($upload['success']) {

                $imageUrl = $upload['path'];

            } elseif ($error === '') {

                $error = 'Banner image upload failed. Use JPG, PNG, GIF, or WebP under 5 MB.';

            }

        }

        $videoUrl = trim($_POST['video_url'] ?? '');

        if ($videoUrl === '') {

            $videoUrl = (string) ($existingContent['video_url'] ?? '');

        }

        if (!empty($_FILES['video_upload']['tmp_name'])) {

            $upload = UploadHelper::storeAdVideo($_FILES['video_upload'], $projectRoot);

            if ($upload['success']) {

                $videoUrl = $upload['path'];

            } elseif ($error === '') {

                $error = 'Video upload failed. Use MP4 or WebM under 50 MB.';

            }

        }



        $placements = array_values(array_filter((array) ($_POST['placements'] ?? [])));

        $pages = array_values(array_filter((array) ($_POST['pages'] ?? [])));

        if ($pages === []) {

            $pages = ['all'];

        }



        $adSource = $_POST['ad_source'] ?? 'own';

        $type = $_POST['type'] ?? 'banner';

        if ($adSource === 'network') {

            $type = 'network';

        }



        $ad = [

            'id' => $id,

            'name' => trim($_POST['name'] ?? 'Untitled Ad'),

            'enabled' => isset($_POST['ad_enabled']),

            'source' => $adSource,

            'type' => $type,

            'network' => $_POST['network'] ?? 'custom',

            'placements' => $placements,

            'pages' => $pages,

            'priority' => (int) ($_POST['priority'] ?? 0),

            'content' => [

                'title' => trim($_POST['content_title'] ?? ''),

                'text' => '',

                'html' => Security::sanitizeAdminHtml(trim($_POST['content_html'] ?? '')),

                'image_url' => $imageUrl,

                'video_url' => $videoUrl,

                'link_url' => trim($_POST['link_url'] ?? ''),

                'alt' => trim($_POST['image_alt'] ?? 'Advertisement'),

                'network_code' => trim($_POST['network_code'] ?? ''),

                'client_id' => trim($_POST['client_id'] ?? ''),

                'slot_id' => trim($_POST['slot_id'] ?? ''),

                'width' => max(1, (int) ($_POST['ad_width'] ?? 728)),

                'height' => max(1, (int) ($_POST['ad_height'] ?? 90)),

            ],

            'popup' => [

                'delay_seconds' => max(0, min(60, (int) ($_POST['popup_delay'] ?? 3))),

                'show_once_per_session' => false,

                'closable' => isset($_POST['popup_closable']),

            ],

            'updated' => time(),

        ];



        if ($error === '' && $adManager->saveAd($ad)) {

            $message = 'Ad saved successfully.';

            $action = 'list';

            $tab = 'ads';

        } else {

            $error = 'Failed to save ad.';

        }

    }

}



$adData = $adManager->getData();

$networkSettings = $adManager->getNetworkSettings();

$editAd = $editId !== '' ? $adManager->getAd($editId) : null;

if ($action === 'edit' && $editAd === null && $editId !== '') {

    $error = 'Ad not found.';

    $action = 'list';

}



$pageTitle = 'Ad Management';

require __DIR__ . '/layout/header.php';

?>



<h1>Ad Management</h1>

<p class="admin-note">Configure ad networks in one place, then create and manage ads in the other tab.</p>



<?php if ($message): ?><p class="admin-success"><?= Security::escape($message) ?></p><?php endif; ?>

<?php if ($error): ?><p class="admin-error"><?= Security::escape($error) ?></p><?php endif; ?>



<div class="admin-tabs">

    <a href="ads.php?tab=networks" class="admin-tab<?= $tab === 'networks' ? ' active' : '' ?>">Network Settings</a>

    <a href="ads.php?tab=ads" class="admin-tab<?= $tab === 'ads' ? ' active' : '' ?>">Manage Ads</a>

    <a href="ads.php?tab=map" class="admin-tab<?= $tab === 'map' ? ' active' : '' ?>">Placement Map</a>

</div>



<?php if ($tab === 'map'): ?>

<div class="admin-tab-panel">
    <h2>Ad Placement Map</h2>
    <?php require __DIR__ . '/partials/ad-placement-maps.php'; ?>
</div>



<?php elseif ($tab === 'networks'): ?>

<div class="admin-tab-panel">

    <h2>Ad Network Keys &amp; Global Settings</h2>

    <p class="admin-note">Save your Google AdSense, Media.net, PropellerAds, and other network credentials here. Network ads you create will use these keys automatically.</p>



    <form method="POST" class="admin-form admin-form-wide">

        <?= Security::csrfField($config) ?>

        <input type="hidden" name="save_networks" value="1">



        <fieldset class="admin-fieldset">

            <legend>Global</legend>

            <label class="checkbox">

                <input type="checkbox" name="ads_enabled" <?= !empty($adData['enabled']) ? 'checked' : '' ?>>

                Enable ads on the website

            </label>

            <label>Download modal countdown (seconds)

                <input type="number" name="download_modal_countdown" min="0" max="30" value="<?= (int) ($adData['download_modal_countdown'] ?? 5) ?>">

                <span class="hint">Seconds before download unlocks when user clicks a download button</span>

            </label>

        </fieldset>



        <?php foreach (AdManager::NETWORKS as $nk => $nlabel):

            $ns = $networkSettings[$nk] ?? [];

            $help = AdManager::networkHelp($nk);

            $prefix = 'net_' . $nk . '_';

        ?>

        <fieldset class="admin-fieldset">

            <legend><?= Security::escape($nlabel) ?></legend>

            <p class="admin-note"><strong><?= Security::escape($help['title']) ?></strong> — <?= Security::escape($help['help']) ?></p>

            <?php if (in_array($nk, ['adsense', 'adsense_auto', 'gam', 'medianet'], true)): ?>

            <label>Publisher / Client ID

                <input type="text" name="<?= Security::escape($prefix) ?>client_id" value="<?= Security::escape($ns['client_id'] ?? '') ?>" placeholder="ca-pub-XXXXXXXX">

            </label>

            <label>Slot / Unit ID

                <input type="text" name="<?= Security::escape($prefix) ?>slot_id" value="<?= Security::escape($ns['slot_id'] ?? '') ?>">

            </label>

            <label>Size (W × H px)

                <span style="display:flex;gap:.5rem">

                    <input type="number" name="<?= Security::escape($prefix) ?>width" value="<?= (int) ($ns['width'] ?? 728) ?>" style="width:100px"> ×

                    <input type="number" name="<?= Security::escape($prefix) ?>height" value="<?= (int) ($ns['height'] ?? 90) ?>" style="width:100px">

                </span>

            </label>

            <?php endif; ?>

            <label>Full ad tag / script (optional — overrides auto-generated code)

                <textarea name="<?= Security::escape($prefix) ?>network_code" rows="4" placeholder="Paste full tag from your ad dashboard"><?= Security::escape($ns['network_code'] ?? '') ?></textarea>

            </label>

        </fieldset>

        <?php endforeach; ?>



        <button type="submit" class="btn btn-primary">Save Network Settings</button>

    </form>

</div>



<?php else: /* tab=ads */ ?>



<?php if ($action === 'create' && $source === ''): ?>

<div class="admin-tab-panel">

    <h2>Add New Ad</h2>

    <p class="admin-note">Choose what kind of ad you want to create:</p>

    <div class="ad-source-picker" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;max-width:640px;margin:1.5rem 0">

        <a href="ads.php?tab=ads&amp;action=create&amp;source=own" class="admin-card" style="padding:1.5rem;text-align:center;text-decoration:none;border:2px solid #e5e7eb;border-radius:12px">

            <strong style="font-size:1.2rem;display:block;margin-bottom:.5rem">My Own Ad</strong>

            <span style="color:#64748b">Banner image, text/HTML, video, or popup you control</span>

        </a>

        <a href="ads.php?tab=ads&amp;action=create&amp;source=network" class="admin-card" style="padding:1.5rem;text-align:center;text-decoration:none;border:2px solid #e5e7eb;border-radius:12px">

            <strong style="font-size:1.2rem;display:block;margin-bottom:.5rem">Ad Network</strong>

            <span style="color:#64748b">Google AdSense, Media.net, PropellerAds, etc. using saved keys</span>

        </a>

    </div>

    <a href="ads.php?tab=ads" class="btn btn-secondary">← Back to list</a>

</div>



<?php elseif ($action === 'create' || $action === 'edit'): ?>

<?php

$adSource = $editAd['source'] ?? $source ?: 'own';

$a = $editAd ?? [

    'name' => '', 'enabled' => true, 'type' => $adSource === 'network' ? 'network' : 'banner',

    'source' => $adSource, 'network' => 'adsense',

    'placements' => ['home_hero_sidebar', 'home_after_form'], 'pages' => ['all'], 'priority' => 0,

    'content' => [], 'popup' => ['delay_seconds' => 3, 'show_once_per_session' => false, 'closable' => true],

];

$c = $a['content'] ?? [];

$p = $a['popup'] ?? [];

$isNetwork = ($a['source'] ?? $adSource) === 'network' || ($a['type'] ?? '') === 'network';

$mediaPreview = static function (string $path): string {

    if ($path === '') {

        return '';

    }

    if (preg_match('#^https?://#i', $path)) {

        return $path;

    }

    return '../' . ltrim($path, '/');

};

?>

<div class="admin-tab-panel">

    <h2><?= $action === 'edit' ? 'Edit Ad' : ($isNetwork ? 'New Network Ad' : 'New Own Ad') ?></h2>

    <form method="POST" enctype="multipart/form-data" class="admin-form admin-form-wide" id="ad-form" data-wysiwyg-form>

        <?= Security::csrfField($config) ?>

        <input type="hidden" name="save_ad" value="1">

        <input type="hidden" name="ad_id" value="<?= Security::escape($a['id'] ?? '') ?>">

        <input type="hidden" name="ad_source" value="<?= Security::escape($isNetwork ? 'network' : 'own') ?>">



        <fieldset class="admin-fieldset">

            <legend>Basic</legend>

            <label>Ad name (internal)

                <input type="text" name="name" required value="<?= Security::escape($a['name'] ?? '') ?>">

            </label>

            <label class="checkbox"><input type="checkbox" name="ad_enabled" <?= !isset($a['enabled']) || $a['enabled'] ? 'checked' : '' ?>> Active</label>

            <?php if (!$isNetwork): ?>

            <label>Ad type

                <select name="type" id="ad-type">

                    <option value="banner" <?= ($a['type'] ?? '') === 'banner' ? 'selected' : '' ?>>Image / Banner</option>

                    <option value="text" <?= ($a['type'] ?? '') === 'text' ? 'selected' : '' ?>>Text / HTML</option>

                    <option value="video" <?= ($a['type'] ?? '') === 'video' ? 'selected' : '' ?>>Video</option>

                    <option value="popup" <?= ($a['type'] ?? '') === 'popup' ? 'selected' : '' ?>>Popup</option>

                </select>

            </label>

            <?php else: ?>

            <input type="hidden" name="type" value="network">

            <label>Ad network

                <select name="network" id="ad-network">

                    <?php foreach (AdManager::NETWORKS as $nk => $nlabel): ?>

                    <option value="<?= Security::escape($nk) ?>" <?= ($a['network'] ?? 'adsense') === $nk ? 'selected' : '' ?>><?= Security::escape($nlabel) ?></option>

                    <?php endforeach; ?>

                </select>

            </label>

            <div id="network-help" class="admin-note"></div>

            <p class="admin-note">Uses keys from <a href="ads.php?tab=networks">Network Settings</a>. Override below if needed.</p>

            <label>Client ID (override)

                <input type="text" name="client_id" value="<?= Security::escape($c['client_id'] ?? '') ?>" placeholder="Leave blank to use saved key">

            </label>

            <label>Slot ID (override)

                <input type="text" name="slot_id" value="<?= Security::escape($c['slot_id'] ?? '') ?>">

            </label>

            <label>Custom tag override

                <textarea name="network_code" rows="4" placeholder="Leave blank to auto-generate from saved keys"><?= Security::escape($c['network_code'] ?? '') ?></textarea>

            </label>

            <?php endif; ?>

            <label>Priority

                <input type="number" name="priority" value="<?= (int) ($a['priority'] ?? 0) ?>">

            </label>

        </fieldset>



        <fieldset class="admin-fieldset">

            <legend>Where to show</legend>

            <div class="ad-placement-grid">

                <?php foreach (AdManager::PLACEMENTS as $pk => $plabel): ?>

                <label class="checkbox">

                    <input type="checkbox" name="placements[]" value="<?= Security::escape($pk) ?>" <?= in_array($pk, $a['placements'] ?? [], true) ? 'checked' : '' ?>>

                    <?= Security::escape($plabel) ?>

                </label>

                <?php endforeach; ?>

            </div>

            <p class="admin-note" style="margin-top:1rem">Pages:</p>

            <div class="ad-placement-grid">

                <?php foreach (AdManager::PAGE_TYPES as $pk => $plabel): ?>

                <label class="checkbox">

                    <input type="checkbox" name="pages[]" value="<?= Security::escape($pk) ?>" <?= in_array($pk, $a['pages'] ?? ['all'], true) ? 'checked' : '' ?>>

                    <?= Security::escape($plabel) ?>

                </label>

                <?php endforeach; ?>

            </div>

        </fieldset>



        <?php if (!$isNetwork): ?>

        <fieldset class="admin-fieldset ad-fields-banner ad-fields-type">

            <legend>Banner / Image</legend>

            <?php if (!empty($c['image_url'])): ?>

            <div class="admin-media-preview">

                <span class="admin-field-hint">Current banner:</span><br>

                <img src="<?= Security::escape($mediaPreview((string) $c['image_url'])) ?>" alt="Current banner preview">

            </div>

            <?php endif; ?>

            <label class="admin-upload-field">Upload banner image

                <input type="file" name="banner_upload" accept="image/png,image/jpeg,image/gif,image/webp">

                <span class="admin-field-hint">JPG, PNG, GIF, or WebP — max 5 MB. Upload replaces the current image.</span>

            </label>

            <label>Or image URL <input type="text" name="image_url" value="<?= Security::escape($c['image_url'] ?? '') ?>" placeholder="https://... or leave blank when uploading"></label>

            <label>Click URL <input type="url" name="link_url" value="<?= Security::escape($c['link_url'] ?? '') ?>"></label>

            <label>Alt text <input type="text" name="image_alt" value="<?= Security::escape($c['alt'] ?? 'Advertisement') ?>"></label>

        </fieldset>



        <fieldset class="admin-fieldset ad-fields-text ad-fields-type">

            <legend>Text / HTML</legend>

            <label>Title <input type="text" name="content_title" value="<?= Security::escape($c['title'] ?? '') ?>"></label>

            <label>Content

                <textarea name="content_html" class="wysiwyg" id="ad-content-html" rows="8"><?= Security::escape($c['html'] ?? ($c['text'] ?? '')) ?></textarea>

                <span class="admin-field-hint">Rich text shown for Text/HTML ads and popup body content.</span>

            </label>

        </fieldset>



        <fieldset class="admin-fieldset ad-fields-video ad-fields-type">

            <legend>Video</legend>

            <?php

            $currentVideo = (string) ($c['video_url'] ?? '');

            $videoPreview = $currentVideo !== '' && !preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)#', $currentVideo) ? $mediaPreview($currentVideo) : '';

            ?>

            <?php if ($videoPreview !== ''): ?>

            <div class="admin-media-preview">

                <span class="admin-field-hint">Current uploaded video:</span><br>

                <video src="<?= Security::escape($videoPreview) ?>" controls preload="metadata"></video>

            </div>

            <?php endif; ?>

            <label class="admin-upload-field">Upload video file

                <input type="file" name="video_upload" accept="video/mp4,video/webm">

                <span class="admin-field-hint">MP4 or WebM — max 50 MB.</span>

            </label>

            <label>Or video / YouTube URL <input type="text" name="video_url" value="<?= Security::escape($currentVideo) ?>" placeholder="https://youtube.com/... or uploaded path"></label>

        </fieldset>



        <fieldset class="admin-fieldset ad-fields-popup ad-fields-type">

            <legend>Popup timing</legend>

            <label>Delay (seconds) <input type="number" name="popup_delay" min="0" max="60" value="<?= (int) ($p['delay_seconds'] ?? 3) ?>"></label>

            <p class="admin-note">Popup ads appear on every page load after the delay.</p>

            <label class="checkbox"><input type="checkbox" name="popup_closable" <?= !isset($p['closable']) || $p['closable'] ? 'checked' : '' ?>> Show close button</label>

        </fieldset>

        <?php endif; ?>



        <button type="submit" class="btn btn-primary">Save Ad</button>

        <a href="ads.php?tab=ads" class="btn btn-secondary" style="margin-left:.5rem">Cancel</a>

    </form>

</div>



<?php if (!$isNetwork): ?>

<script>

(function () {

    var typeSel = document.getElementById('ad-type');

    function toggleFields() {

        var t = typeSel.value;

        document.querySelectorAll('.ad-fields-type').forEach(function (el) { el.style.display = 'none'; });

        var show = document.querySelector('.ad-fields-' + t);

        if (show) show.style.display = 'block';

        if (t === 'popup') {

            ['banner', 'text'].forEach(function (x) {

                var el = document.querySelector('.ad-fields-' + x);

                if (el) el.style.display = 'block';

            });

        }

    }

    typeSel.addEventListener('change', toggleFields);

    toggleFields();

})();

</script>

<?php else: ?>

<script>

(function () {

    var networkSel = document.getElementById('ad-network');

    var helpEl = document.getElementById('network-help');

    var networkHelp = <?= json_encode(array_combine(array_keys(AdManager::NETWORKS), array_map(static fn(string $k): array => AdManager::networkHelp($k), array_keys(AdManager::NETWORKS))), JSON_UNESCAPED_UNICODE) ?>;

    function updateHelp() {

        var h = networkHelp[networkSel.value] || { title: '', help: '' };

        helpEl.innerHTML = '<strong>' + h.title + '</strong>: ' + h.help;

    }

    networkSel.addEventListener('change', updateHelp);

    updateHelp();

})();

</script>

<?php endif; ?>



<?php require __DIR__ . '/partials/wysiwyg-foot.php'; ?>

<?php else: /* list */ ?>

<div class="admin-tab-panel">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">

        <h2>Current Ads (<?= count($adManager->allAds()) ?>)</h2>

        <a href="ads.php?tab=ads&amp;action=create" class="btn btn-primary">+ Add New Ad</a>

    </div>



    <?php if (!$adData['enabled']): ?>

    <p class="admin-note" style="background:#fef3c7;padding:.75rem;border-radius:8px">Ads are <strong>disabled</strong>. Enable them in <a href="ads.php?tab=networks">Network Settings</a>.</p>

    <?php endif; ?>



    <?php if ($adManager->allAds() === []): ?>

    <p>No ads yet. <a href="ads.php?tab=ads&amp;action=create">Add your first ad</a>.</p>

    <?php else: ?>

    <table class="admin-table">

        <thead>

            <tr><th>Name</th><th>Source</th><th>Type</th><th>Placements</th><th>Status</th><th>Actions</th></tr>

        </thead>

        <tbody>

        <?php foreach ($adManager->allAds() as $ad):

            $src = ($ad['source'] ?? '') === 'network' || ($ad['type'] ?? '') === 'network' ? 'Network' : 'Own';

        ?>

        <tr>

            <td><?= Security::escape($ad['name'] ?? '') ?></td>

            <td><?= Security::escape($src) ?></td>

            <td><?= Security::escape(AdManager::AD_TYPES[$ad['type'] ?? ''] ?? $ad['type'] ?? '') ?></td>

            <td><?= Security::escape(implode(', ', array_map(static fn($p) => AdManager::PLACEMENTS[$p] ?? $p, $ad['placements'] ?? []))) ?></td>

            <td><?= !empty($ad['enabled']) ? '✓ Active' : 'Disabled' ?></td>

            <td>

                <a href="ads.php?tab=ads&amp;action=edit&amp;id=<?= Security::escape($ad['id'] ?? '') ?>">Edit</a>

                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this ad?')">

                    <?= Security::csrfField($config) ?>

                    <input type="hidden" name="delete_ad" value="1">

                    <input type="hidden" name="ad_id" value="<?= Security::escape($ad['id'] ?? '') ?>">

                    <button type="submit" class="btn-link danger">Delete</button>

                </form>

            </td>

        </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

    <?php endif; ?>

</div>

<?php endif; ?>

<?php endif; ?>



<?php require __DIR__ . '/layout/footer.php'; ?>

