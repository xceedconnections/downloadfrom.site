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

if ($tab === 'networks') {

    $tab = 'map';

}

$action = $_GET['action'] ?? 'list';

$editId = $_GET['id'] ?? '';

$source = $_GET['source'] ?? '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {

        $error = 'Invalid CSRF token.';

    } elseif (isset($_POST['save_placement_map'])) {

        $map = is_array($_POST['placement_map'] ?? null) ? $_POST['placement_map'] : [];

        $adManager->saveGlobalSettings(
            isset($_POST['ads_enabled']),
            max(0, min(30, (int) ($_POST['download_modal_countdown'] ?? 5)))
        );

        if ($adManager->savePlacementMap($map)) {

            $message = 'Placement map saved.';

            $tab = 'map';

        } else {

            $error = 'Failed to save placement map.';

        }

    } elseif (isset($_POST['delete_ad'])) {

        $id = trim($_POST['ad_id'] ?? '');

        if ($id !== '' && $adManager->deleteAd($id)) {

            $map = $adManager->getPlacementMap();

            foreach ($map as $place => $mappedId) {

                if ($mappedId === $id) {

                    unset($map[$place]);

                }

            }

            $adManager->savePlacementMap($map);

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



        $placements = [];

        $pages = ['all'];



        $type = $_POST['type'] ?? 'banner';

        $rawHtml = trim($_POST['content_html'] ?? '');

        if (in_array($type, ['html', 'network', 'popup'], true)) {

            $contentHtml = $rawHtml !== '' ? $rawHtml : (string) ($existingContent['html'] ?? $existingContent['network_code'] ?? '');

        } elseif ($type === 'text') {

            $contentHtml = $rawHtml !== '' ? Security::sanitizeAdminHtml($rawHtml) : (string) ($existingContent['html'] ?? '');

        } else {

            $contentHtml = (string) ($existingContent['html'] ?? '');

        }



        $ad = [

            'id' => $id,

            'name' => trim($_POST['name'] ?? 'Untitled Ad'),

            'enabled' => isset($_POST['ad_enabled']),

            'source' => 'own',

            'type' => $type === 'network' ? 'html' : $type,

            'placements' => $placements,

            'pages' => $pages,

            'priority' => (int) ($_POST['priority'] ?? 0),

            'content' => [

                'title' => trim($_POST['content_title'] ?? ''),

                'text' => '',

                'html' => $contentHtml,

                'image_url' => $imageUrl,

                'video_url' => $videoUrl,

                'link_url' => trim($_POST['link_url'] ?? ''),

                'alt' => trim($_POST['image_alt'] ?? 'Advertisement'),

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

$editAd = $editId !== '' ? $adManager->getAd($editId) : null;

if ($action === 'edit' && $editAd === null && $editId !== '') {

    $error = 'Ad not found.';

    $action = 'list';

}



$pageTitle = 'Ad Management';

require __DIR__ . '/layout/header.php';

?>



<h1>Ad Management</h1>

<p class="admin-note">Create ads manually, then assign each one to a page zone in <a href="ads.php?tab=map">Placement Map</a>.</p>



<?php if ($message): ?><p class="admin-success"><?= Security::escape($message) ?></p><?php endif; ?>

<?php if ($error): ?><p class="admin-error"><?= Security::escape($error) ?></p><?php endif; ?>



<div class="admin-tabs">

    <a href="ads.php?tab=ads" class="admin-tab<?= $tab === 'ads' ? ' active' : '' ?>">Manage Ads</a>

    <a href="ads.php?tab=map" class="admin-tab<?= $tab === 'map' ? ' active' : '' ?>">Placement Map</a>

</div>



<?php if ($tab === 'map'): ?>

<div class="admin-tab-panel">
    <h2>Ad Placement Map</h2>
    <?php require __DIR__ . '/partials/ad-placement-maps.php'; ?>
</div>



<?php else: /* tab=ads */ ?>



<?php if ($action === 'create' || $action === 'edit'): ?>

<?php

$a = $editAd ?? [

    'name' => '', 'enabled' => true, 'type' => 'html',

    'placements' => [], 'pages' => ['all'], 'priority' => 0,

    'content' => [], 'popup' => ['delay_seconds' => 3, 'show_once_per_session' => false, 'closable' => true],

];

$c = $a['content'] ?? [];

$p = $a['popup'] ?? [];

$adType = (string) ($a['type'] ?? 'html');

if ($adType === 'network') {

    $adType = 'html';

}

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

    <h2><?= $action === 'edit' ? 'Edit Ad' : 'New Ad' ?></h2>

    <p class="admin-note">Assign this ad to page zones in <a href="ads.php?tab=map">Placement Map</a> after saving.</p>

    <form method="POST" enctype="multipart/form-data" class="admin-form admin-form-wide" id="ad-form" data-wysiwyg-form>

        <?= Security::csrfField($config) ?>

        <input type="hidden" name="save_ad" value="1">

        <input type="hidden" name="ad_id" value="<?= Security::escape($a['id'] ?? '') ?>">



        <fieldset class="admin-fieldset">

            <legend>Basic</legend>

            <label>Ad name (internal)

                <input type="text" name="name" required value="<?= Security::escape($a['name'] ?? '') ?>">

            </label>

            <label class="checkbox"><input type="checkbox" name="ad_enabled" <?= !isset($a['enabled']) || $a['enabled'] ? 'checked' : '' ?>> Active</label>

            <label>Ad type

                <select name="type" id="ad-type">

                    <option value="html" <?= $adType === 'html' ? 'selected' : '' ?>>HTML / Script (Google AdSense, etc.)</option>

                    <option value="banner" <?= $adType === 'banner' ? 'selected' : '' ?>>Image / Banner</option>

                    <option value="text" <?= $adType === 'text' ? 'selected' : '' ?>>Text (rich text)</option>

                    <option value="video" <?= $adType === 'video' ? 'selected' : '' ?>>Video</option>

                    <option value="popup" <?= $adType === 'popup' ? 'selected' : '' ?>>Popup overlay</option>

                </select>

            </label>

        </fieldset>



        <fieldset class="admin-fieldset ad-fields-html ad-fields-type">

            <legend>HTML / Script code</legend>

            <label>Paste full ad code (scripts, ins tags, iframes)

                <textarea name="content_html" id="ad-content-html" rows="12" class="ad-code-textarea" placeholder="Paste Google AdSense or any ad network HTML/JavaScript here"><?= Security::escape($c['html'] ?? ($c['network_code'] ?? '')) ?></textarea>

                <span class="admin-field-hint">Rendered exactly as HTML on the site — use this for AdSense, Media.net, etc.</span>

            </label>

        </fieldset>



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

            </label>

            <label>Or image URL <input type="text" name="image_url" value="<?= Security::escape($c['image_url'] ?? '') ?>"></label>

            <label>Click URL <input type="url" name="link_url" value="<?= Security::escape($c['link_url'] ?? '') ?>"></label>

            <label>Alt text <input type="text" name="image_alt" value="<?= Security::escape($c['alt'] ?? 'Advertisement') ?>"></label>

        </fieldset>



        <fieldset class="admin-fieldset ad-fields-text ad-fields-type">

            <legend>Text content</legend>

            <label>Title <input type="text" name="content_title" value="<?= Security::escape($c['title'] ?? '') ?>"></label>

            <label>Body

                <textarea name="content_html" class="wysiwyg" id="ad-content-text" rows="8"><?= Security::escape($c['html'] ?? ($c['text'] ?? '')) ?></textarea>

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

                <video src="<?= Security::escape($videoPreview) ?>" controls preload="metadata"></video>

            </div>

            <?php endif; ?>

            <label class="admin-upload-field">Upload video

                <input type="file" name="video_upload" accept="video/mp4,video/webm">

            </label>

            <label>Or video / YouTube URL <input type="text" name="video_url" value="<?= Security::escape($currentVideo) ?>"></label>

        </fieldset>



        <fieldset class="admin-fieldset ad-fields-popup ad-fields-type">

            <legend>Popup timing</legend>

            <label>Delay (seconds) <input type="number" name="popup_delay" min="0" max="60" value="<?= (int) ($p['delay_seconds'] ?? 3) ?>"></label>

            <label class="checkbox"><input type="checkbox" name="popup_closable" <?= !isset($p['closable']) || $p['closable'] ? 'checked' : '' ?>> Show close button</label>

            <p class="admin-note">Popup uses the HTML / Script code above. Assign to <code>popup</code> in Placement Map.</p>

        </fieldset>



        <button type="submit" class="btn btn-primary">Save Ad</button>

        <a href="ads.php?tab=ads" class="btn btn-secondary" style="margin-left:.5rem">Cancel</a>

    </form>

</div>



<script>

(function () {

    var typeSel = document.getElementById('ad-type');

    var htmlField = document.getElementById('ad-content-html');

    var textField = document.getElementById('ad-content-text');

    function toggleFields() {

        var t = typeSel.value;

        document.querySelectorAll('.ad-fields-type').forEach(function (el) { el.style.display = 'none'; });

        var show = document.querySelector('.ad-fields-' + t);

        if (show) show.style.display = 'block';

        if (htmlField) htmlField.disabled = (t === 'text');

        if (textField) textField.disabled = (t !== 'text');

        if (t === 'popup') {

            var htmlBlock = document.querySelector('.ad-fields-html');

            if (htmlBlock) htmlBlock.style.display = 'block';

            if (htmlField) htmlField.disabled = false;

        }

    }

    typeSel.addEventListener('change', toggleFields);

    toggleFields();

})();

</script>



<?php require __DIR__ . '/partials/wysiwyg-foot.php'; ?>

<?php else: /* list */ ?>

<div class="admin-tab-panel">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">

        <h2>Current Ads (<?= count($adManager->allAds()) ?>)</h2>

        <a href="ads.php?tab=ads&amp;action=create" class="btn btn-primary">+ Add New Ad</a>

    </div>



    <?php if (!$adData['enabled']): ?>

    <p class="admin-note" style="background:#fef3c7;padding:.75rem;border-radius:8px">Ads are <strong>disabled</strong>. Enable them in <a href="ads.php?tab=map">Placement Map</a>.</p>

    <?php endif; ?>



    <?php if ($adManager->allAds() === []): ?>

    <p>No ads yet. <a href="ads.php?tab=ads&amp;action=create">Add your first ad</a>.</p>

    <?php else: ?>

    <table class="admin-table">

        <thead>

            <tr><th>Name</th><th>Type</th><th>Used in zones</th><th>Status</th><th>Actions</th></tr>

        </thead>

        <tbody>

        <?php foreach ($adManager->allAds() as $ad):

            $aid = (string) ($ad['id'] ?? '');

            $usedIn = [];

            foreach ($adManager->getPlacementMap() as $place => $mappedId) {

                if ($mappedId === $aid) {

                    $usedIn[] = AdManager::PLACEMENTS[$place] ?? $place;

                }

            }

        ?>

        <tr>

            <td><?= Security::escape($ad['name'] ?? '') ?></td>

            <td><?= Security::escape(AdManager::AD_TYPES[$ad['type'] ?? ''] ?? $ad['type'] ?? '') ?></td>

            <td><?= Security::escape($usedIn !== [] ? implode(', ', $usedIn) : '— assign in Placement Map') ?></td>

            <td><?= !empty($ad['enabled']) ? 'Active' : 'Disabled' ?></td>

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

