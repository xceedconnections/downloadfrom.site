<?php

declare(strict_types=1);

use App\AdManager;
use App\Security;
use App\UploadHelper;

require __DIR__ . '/init.php';
$auth->requireAuth();

/** Normalize a single POST string (handles duplicate field names from multiple textareas). */
function adminPostString(string $key): string
{
    $value = $_POST[$key] ?? '';
    if (is_array($value)) {
        $value = end($value);
    }

    return trim((string) $value);
}

$adManager = new AdManager($db, $config['app']['url']);
$message = '';
$error = '';
$tab = $_GET['tab'] ?? 'ads';
if ($tab === 'networks') {
    $tab = 'map';
}
$action = $_GET['action'] ?? 'list';
$editId = $_GET['id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = 'Request too large for PHP post_max_size. Shorten the ad script or increase post_max_size in php.ini.';
    } elseif (!Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {
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
            foreach ($map as $place => $mappedIds) {
                $filtered = array_values(array_filter($mappedIds, static fn(string $mappedId): bool => $mappedId !== $id));
                if ($filtered === []) {
                    unset($map[$place]);
                } else {
                    $map[$place] = $filtered;
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
        $existingAd = $adManager->getAd($id) ?? [];
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

        $type = $_POST['type'] ?? 'banner';
        $rawHtml = adminPostString('content_html');
        $rawTextBody = adminPostString('content_text_body');
        $popupHtml = adminPostString('popup_html');
        $popupText = adminPostString('popup_text');
        $popupTitle = adminPostString('popup_title');
        $popupLink = adminPostString('popup_link_url');

        if ($type === 'popup') {
            $popupDisplay = in_array((string) ($_POST['popup_display'] ?? ''), ['window', 'modal'], true)
                ? (string) $_POST['popup_display']
                : 'modal';
            $popupMode = (string) ($_POST['popup_mode'] ?? 'html');
            if ($popupMode === 'link') {
                $popupMode = 'iframe';
            }

            if ($popupDisplay === 'window') {
                $contentHtml = '';
                $contentText = '';
                $contentTitle = '';
                $contentLink = trim($_POST['popup_window_url'] ?? '');
                if ($contentLink === '') {
                    $contentLink = (string) ($existingContent['link_url'] ?? '');
                }
                $contentWidth = (int) ($existingContent['width'] ?? 600);
                $contentHeight = (int) ($existingContent['height'] ?? 420);
                $popupMode = 'link';
            } elseif ($popupMode === 'html') {
                $contentHtml = $popupHtml !== '' ? $popupHtml : (string) ($existingContent['html'] ?? '');
                $contentText = '';
                $contentTitle = '';
                $contentLink = '';
                $contentWidth = max(200, (int) ($existingContent['width'] ?? 600));
                $contentHeight = max(150, (int) ($existingContent['height'] ?? 420));
            } elseif ($popupMode === 'iframe') {
                $contentHtml = '';
                $contentText = '';
                $contentTitle = '';
                $contentLink = $popupLink !== '' ? $popupLink : (string) ($existingContent['link_url'] ?? '');
                $contentWidth = max(200, (int) ($_POST['popup_iframe_width'] ?? ($existingContent['width'] ?? 600)));
                $contentHeight = max(150, (int) ($_POST['popup_iframe_height'] ?? ($existingContent['height'] ?? 420)));
            } elseif ($popupMode === 'text') {
                $contentHtml = $popupText !== '' ? Security::sanitizeAdminHtml($popupText) : (string) ($existingContent['html'] ?? '');
                $contentText = '';
                $contentTitle = $popupTitle;
                $contentLink = trim($_POST['popup_text_link'] ?? '');
                $contentWidth = max(200, (int) ($existingContent['width'] ?? 600));
                $contentHeight = max(150, (int) ($existingContent['height'] ?? 420));
            } elseif ($popupMode === 'image') {
                $contentHtml = '';
                $contentText = '';
                $contentTitle = '';
                $contentLink = trim($_POST['popup_image_link'] ?? '');
                $popupImageUrl = trim($_POST['popup_image_url'] ?? '');
                if ($popupImageUrl === '') {
                    $popupImageUrl = (string) ($existingContent['image_url'] ?? '');
                }
                if (!empty($_FILES['popup_image_upload']['tmp_name'])) {
                    $upload = UploadHelper::storeAdImage($_FILES['popup_image_upload'], $projectRoot);
                    if ($upload['success']) {
                        $popupImageUrl = $upload['path'];
                    } elseif ($error === '') {
                        $error = 'Popup image upload failed. Use JPG, PNG, GIF, or WebP under 5 MB.';
                    }
                }
                $imageUrl = $popupImageUrl;
                $imageAlt = trim($_POST['popup_image_alt'] ?? 'Advertisement');
                $contentWidth = max(200, (int) ($existingContent['width'] ?? 600));
                $contentHeight = max(150, (int) ($existingContent['height'] ?? 420));
            } elseif ($popupMode === 'video') {
                $contentHtml = '';
                $contentText = '';
                $contentTitle = '';
                $contentLink = '';
                $popupVideoUrl = trim($_POST['popup_video_url'] ?? '');
                if ($popupVideoUrl === '') {
                    $popupVideoUrl = (string) ($existingContent['video_url'] ?? '');
                }
                if (!empty($_FILES['popup_video_upload']['tmp_name'])) {
                    $upload = UploadHelper::storeAdVideo($_FILES['popup_video_upload'], $projectRoot);
                    if ($upload['success']) {
                        $popupVideoUrl = $upload['path'];
                    } elseif ($error === '') {
                        $error = 'Popup video upload failed. Use MP4 or WebM under 50 MB.';
                    }
                }
                $videoUrl = $popupVideoUrl;
                $contentWidth = max(200, (int) ($existingContent['width'] ?? 600));
                $contentHeight = max(150, (int) ($existingContent['height'] ?? 420));
            } else {
                $contentHtml = $popupHtml !== '' ? $popupHtml : (string) ($existingContent['html'] ?? '');
                $contentText = '';
                $contentTitle = '';
                $contentLink = '';
                $contentWidth = max(200, (int) ($existingContent['width'] ?? 600));
                $contentHeight = max(150, (int) ($existingContent['height'] ?? 420));
                $popupMode = 'html';
            }
        } elseif (in_array($type, ['html', 'network'], true)) {
            $contentHtml = $rawHtml !== '' ? $rawHtml : (string) ($existingContent['html'] ?? $existingContent['network_code'] ?? '');
            $contentText = '';
            $contentTitle = trim($_POST['content_title'] ?? '');
            $contentLink = trim($_POST['link_url'] ?? '');
            $contentWidth = max(1, (int) ($_POST['ad_width'] ?? 728));
            $contentHeight = max(1, (int) ($_POST['ad_height'] ?? 90));
        } elseif ($type === 'text') {
            $contentHtml = $rawTextBody !== '' ? Security::sanitizeAdminHtml($rawTextBody) : (string) ($existingContent['html'] ?? '');
            $contentText = '';
            $contentTitle = trim($_POST['content_title'] ?? '');
            $contentLink = trim($_POST['link_url'] ?? '');
            $contentWidth = max(1, (int) ($_POST['ad_width'] ?? 728));
            $contentHeight = max(1, (int) ($_POST['ad_height'] ?? 90));
        } else {
            $contentHtml = (string) ($existingContent['html'] ?? '');
            $contentText = '';
            $contentTitle = trim($_POST['content_title'] ?? '');
            $contentLink = trim($_POST['link_url'] ?? '');
            $contentWidth = max(1, (int) ($_POST['ad_width'] ?? 728));
            $contentHeight = max(1, (int) ($_POST['ad_height'] ?? 90));
        }

        $existingPopup = is_array($existingAd['popup'] ?? null) ? $existingAd['popup'] : [
            'delay_seconds' => 3,
            'show_once_per_session' => false,
            'closable' => true,
            'display' => 'modal',
            'content_mode' => 'html',
        ];

        $existingPlacements = is_array($existingAd['placements'] ?? null) ? $existingAd['placements'] : [];
        $existingPages = is_array($existingAd['pages'] ?? null) && ($existingAd['pages'] ?? []) !== []
            ? $existingAd['pages']
            : ['all'];

        $ad = [
            'id' => $id,
            'name' => trim($_POST['name'] ?? 'Untitled Ad'),
            'enabled' => isset($_POST['ad_enabled']),
            'source' => (string) ($existingAd['source'] ?? 'own'),
            'type' => $type === 'network' ? 'html' : $type,
            'network' => (string) ($existingAd['network'] ?? 'custom'),
            'placements' => $existingPlacements,
            'pages' => $existingPages,
            'priority' => (int) ($_POST['priority'] ?? 0),
            'content' => [
                'title' => $contentTitle,
                'text' => $contentText,
                'html' => $contentHtml,
                'image_url' => $imageUrl,
                'video_url' => $videoUrl,
                'link_url' => $contentLink,
                'alt' => trim($_POST['popup_image_alt'] ?? ($_POST['image_alt'] ?? ($existingContent['alt'] ?? 'Advertisement'))),
                'width' => $contentWidth,
                'height' => $contentHeight,
            ],
            'popup' => $type === 'popup' ? [
                'delay_seconds' => max(0, min(60, (int) ($_POST['popup_delay'] ?? 3))),
                'show_once_per_session' => isset($_POST['popup_once']),
                'closable' => isset($_POST['popup_closable']),
                'display' => in_array((string) ($_POST['popup_display'] ?? ''), ['window', 'modal'], true)
                    ? (string) $_POST['popup_display']
                    : 'modal',
                'content_mode' => (string) ($popupMode ?? 'html'),
            ] : $existingPopup,
            'updated' => time(),
        ];

        if ($error === '' && $adManager->saveAd($ad)) {
            $message = 'Ad saved successfully.';
            $action = 'edit';
            $tab = 'ads';
            $editId = $id;
        } elseif ($error === '') {
            $saveErr = $adManager->getLastSaveError();
            $error = 'Failed to save ad.' . ($saveErr !== '' ? ' ' . $saveErr : '');
            $action = 'edit';
            $tab = 'ads';
            $editId = $id;
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

<?php else: ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
<?php
$a = $editAd ?? [
    'name' => '', 'enabled' => true, 'type' => 'html',
    'placements' => [], 'pages' => ['all'], 'priority' => 0,
    'content' => [], 'popup' => ['delay_seconds' => 3, 'show_once_per_session' => false, 'closable' => true, 'display' => 'modal', 'content_mode' => 'html'],
];
$c = $a['content'] ?? [];
$p = $a['popup'] ?? [];
$adType = (string) ($a['type'] ?? 'html');
if ($adType === 'network') {
    $adType = 'html';
}
$popupDisplay = (string) ($p['display'] ?? 'modal');
$popupMode = (string) ($p['content_mode'] ?? 'html');
if ($popupMode === 'link') {
    $popupMode = 'iframe';
}
if ($adType === 'popup' && $popupDisplay === 'window') {
    $popupMode = 'link';
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
            <label>Click URL (for Download link opener zone)
                <input type="url" name="link_url" value="<?= Security::escape($c['link_url'] ?? '') ?>" placeholder="https://example.com/offer">
            </label>
            <p class="admin-field-hint">Required when this ad is assigned to <strong>Download link opener</strong> — not used for normal HTML display zones.</p>
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
                <textarea name="content_text_body" class="wysiwyg" id="ad-content-text" rows="8"><?= Security::escape($c['html'] ?? ($c['text'] ?? '')) ?></textarea>
            </label>
            <label>Click URL <input type="url" name="link_url" value="<?= Security::escape($c['link_url'] ?? '') ?>" placeholder="https://example.com/offer"></label>
            <p class="admin-field-hint">Required for <strong>Download link opener</strong> zones — opens in a new tab when the visitor clicks Download.</p>
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
            <legend>Popup type</legend>
            <p class="admin-note">After saving, assign this ad to the <strong>Popup</strong> zone in <a href="ads.php?tab=map">Placement Map → Global</a>.</p>

            <div class="popup-display-tabs">
                <label class="popup-display-tab">
                    <input type="radio" name="popup_display" value="window" <?= $popupDisplay === 'window' ? 'checked' : '' ?>>
                    <strong>New window</strong> — opens a link in a new browser tab/window
                </label>
                <label class="popup-display-tab">
                    <input type="radio" name="popup_display" value="modal" <?= $popupDisplay !== 'window' ? 'checked' : '' ?>>
                    <strong>On-page modal</strong> — centered overlay with X close button
                </label>
            </div>
        </fieldset>

        <fieldset class="admin-fieldset ad-fields-popup ad-fields-type popup-fields-window">
            <legend>New window link</legend>
            <label>Destination URL
                <input type="url" name="popup_window_url" value="<?= Security::escape($c['link_url'] ?? '') ?>" placeholder="https://example.com/offer">
            </label>
            <p class="admin-field-hint">Only a link URL is supported for this popup type.</p>
        </fieldset>

        <fieldset class="admin-fieldset ad-fields-popup ad-fields-type popup-fields-modal">
            <legend>Modal content</legend>

            <div class="popup-mode-tabs">
                <label class="popup-mode-tab"><input type="radio" name="popup_mode" value="html" <?= $popupMode === 'html' ? 'checked' : '' ?>> HTML / Script</label>
                <label class="popup-mode-tab"><input type="radio" name="popup_mode" value="iframe" <?= $popupMode === 'iframe' ? 'checked' : '' ?>> URL (iframe)</label>
                <label class="popup-mode-tab"><input type="radio" name="popup_mode" value="text" <?= $popupMode === 'text' ? 'checked' : '' ?>> Text</label>
                <label class="popup-mode-tab"><input type="radio" name="popup_mode" value="image" <?= $popupMode === 'image' ? 'checked' : '' ?>> Image</label>
                <label class="popup-mode-tab"><input type="radio" name="popup_mode" value="video" <?= $popupMode === 'video' ? 'checked' : '' ?>> Video</label>
            </div>

            <div class="popup-mode-panel popup-mode-html">
                <label>HTML / script code
                    <textarea name="popup_html" rows="10" class="ad-code-textarea" placeholder="Paste HTML, AdSense, or any embed code"><?= Security::escape($c['html'] ?? '') ?></textarea>
                </label>
            </div>

            <div class="popup-mode-panel popup-mode-iframe">
                <label>Page URL to embed in iframe
                    <input type="url" name="popup_link_url" value="<?= Security::escape($c['link_url'] ?? '') ?>" placeholder="https://example.com/landing-page">
                </label>
                <div class="admin-form-row">
                    <?php
                    $iframeW = (int) ($c['width'] ?? 600);
                    $iframeH = (int) ($c['height'] ?? 420);
                    if ($adType !== 'popup' || $popupMode !== 'iframe') {
                        $iframeW = 600;
                        $iframeH = 420;
                    }
                    $iframeW = max(280, min(1200, $iframeW));
                    $iframeH = max(200, min(900, $iframeH));
                    ?>
                    <label>Iframe width (px) <input type="number" name="popup_iframe_width" min="280" max="1200" value="<?= $iframeW ?>"></label>
                    <label>Iframe height (px) <input type="number" name="popup_iframe_height" min="200" max="900" value="<?= $iframeH ?>"></label>
                </div>
                <p class="admin-field-hint">Some websites block iframe embedding. If the page stays blank, use HTML mode instead.</p>
            </div>

            <div class="popup-mode-panel popup-mode-text">
                <label>Title <input type="text" name="popup_title" value="<?= Security::escape($c['title'] ?? '') ?>"></label>
                <label>Message
                    <textarea name="popup_text" class="wysiwyg" id="ad-popup-text" rows="6"><?= Security::escape($c['html'] ?? ($c['text'] ?? '')) ?></textarea>
                </label>
                <label>Optional link URL <input type="url" name="popup_text_link" value="<?= Security::escape($popupMode === 'text' ? ($c['link_url'] ?? '') : '') ?>" placeholder="https://…"></label>
            </div>

            <div class="popup-mode-panel popup-mode-image">
                <?php if (!empty($c['image_url'])): ?>
                <div class="admin-media-preview">
                    <span class="admin-field-hint">Current image:</span><br>
                    <img src="<?= Security::escape($mediaPreview((string) $c['image_url'])) ?>" alt="Current popup image preview">
                </div>
                <?php endif; ?>
                <label class="admin-upload-field">Upload image
                    <input type="file" name="popup_image_upload" accept="image/png,image/jpeg,image/gif,image/webp">
                </label>
                <label>Or image URL <input type="text" name="popup_image_url" value="<?= Security::escape($c['image_url'] ?? '') ?>"></label>
                <label>Click URL (optional) <input type="url" name="popup_image_link" value="<?= Security::escape($c['link_url'] ?? '') ?>"></label>
                <label>Alt text <input type="text" name="popup_image_alt" value="<?= Security::escape($c['alt'] ?? 'Advertisement') ?>"></label>
            </div>

            <div class="popup-mode-panel popup-mode-video">
                <?php
                $popupVideo = (string) ($c['video_url'] ?? '');
                $popupVideoPreview = $popupVideo !== '' && !preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)#', $popupVideo) ? $mediaPreview($popupVideo) : '';
                ?>
                <?php if ($popupVideoPreview !== ''): ?>
                <div class="admin-media-preview">
                    <video src="<?= Security::escape($popupVideoPreview) ?>" controls preload="metadata"></video>
                </div>
                <?php endif; ?>
                <label class="admin-upload-field">Upload video
                    <input type="file" name="popup_video_upload" accept="video/mp4,video/webm">
                </label>
                <label>Or video / YouTube URL <input type="text" name="popup_video_url" value="<?= Security::escape($popupVideo) ?>"></label>
            </div>
        </fieldset>

        <fieldset class="admin-fieldset ad-fields-popup ad-fields-type popup-fields-modal">
            <legend>Modal options</legend>
            <label class="checkbox"><input type="checkbox" name="popup_closable" <?= !isset($p['closable']) || $p['closable'] ? 'checked' : '' ?>> Show close (X) button</label>
        </fieldset>

        <fieldset class="admin-fieldset ad-fields-popup ad-fields-type">
            <legend>Popup timing</legend>
            <label>Delay (seconds) <input type="number" name="popup_delay" min="0" max="60" value="<?= (int) ($p['delay_seconds'] ?? 3) ?>"></label>
            <label class="checkbox"><input type="checkbox" name="popup_once" <?= !empty($p['show_once_per_session']) ? 'checked' : '' ?>> Show once per browser session</label>
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
    var adForm = document.getElementById('ad-form');

    function setContainerFieldsDisabled(container, disabled) {
        if (!container) {
            return;
        }
        container.querySelectorAll('input, select, textarea, button').forEach(function (el) {
            if (el.type === 'hidden') {
                return;
            }
            el.disabled = disabled;
        });
    }

    function toggleFields() {
        var t = typeSel.value;
        document.querySelectorAll('.ad-fields-type').forEach(function (el) {
            el.style.display = 'none';
            setContainerFieldsDisabled(el, true);
        });
        var show = document.querySelector('.ad-fields-' + t);
        if (show) {
            show.style.display = 'block';
            setContainerFieldsDisabled(show, false);
        }
        if (htmlField) htmlField.disabled = (t === 'text' || t === 'popup');
        if (textField) textField.disabled = (t !== 'text');
        if (t === 'text') {
            if (textField && window.AdminWysiwyg) {
                AdminWysiwyg.initElement(textField);
            }
        } else if (textField && window.AdminWysiwyg) {
            AdminWysiwyg.removeIn(textField.closest('fieldset') || document);
        }
        if (t === 'popup') {
            document.querySelectorAll('.ad-fields-popup').forEach(function (el) {
                el.style.display = 'block';
                setContainerFieldsDisabled(el, false);
            });
            togglePopupDisplay();
            togglePopupMode();
        } else {
            document.querySelectorAll('.ad-fields-popup, .popup-fields-window, .popup-fields-modal, .popup-mode-panel').forEach(function (el) {
                setContainerFieldsDisabled(el, true);
            });
        }
    }
    function togglePopupDisplay() {
        var display = document.querySelector('input[name="popup_display"]:checked');
        var val = display ? display.value : 'modal';
        document.querySelectorAll('.popup-fields-window').forEach(function (el) {
            var active = val === 'window';
            el.style.display = active ? 'block' : 'none';
            setContainerFieldsDisabled(el, !active);
        });
        document.querySelectorAll('.popup-fields-modal').forEach(function (el) {
            var active = val === 'modal';
            el.style.display = active ? 'block' : 'none';
            setContainerFieldsDisabled(el, !active);
        });
    }
    function togglePopupMode() {
        var mode = document.querySelector('input[name="popup_mode"]:checked');
        var val = mode ? mode.value : 'html';
        document.querySelectorAll('.popup-mode-panel').forEach(function (el) {
            el.style.display = 'none';
            setContainerFieldsDisabled(el, true);
        });
        var panel = document.querySelector('.popup-mode-' + val);
        if (panel) {
            panel.style.display = 'block';
            setContainerFieldsDisabled(panel, false);
        }
    }
    document.querySelectorAll('input[name="popup_mode"]').forEach(function (radio) {
        radio.addEventListener('change', togglePopupMode);
    });
    document.querySelectorAll('input[name="popup_display"]').forEach(function (radio) {
        radio.addEventListener('change', togglePopupDisplay);
    });
    typeSel.addEventListener('change', toggleFields);
    if (adForm) {
        adForm.addEventListener('submit', function () {
            toggleFields();
            if (typeSel.value === 'popup') {
                togglePopupDisplay();
                togglePopupMode();
            }
        });
    }
    toggleFields();
})();
</script>

<?php require __DIR__ . '/partials/wysiwyg-foot.php'; ?>
<?php else: ?>

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
            <tr><th>Impressions</th><th>Name</th><th>Type</th><th>Used in zones</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($adManager->allAds() as $ad):
            $aid = (string) ($ad['id'] ?? '');
            $usedIn = [];
            foreach ($adManager->getPlacementMap() as $place => $mappedIds) {
                if (!is_array($mappedIds)) {
                    $mappedIds = [$mappedIds];
                }
                if (!in_array($aid, $mappedIds, true)) {
                    continue;
                }
                $parsed = AdManager::parsePlacementMapKey($place);
                $label = AdManager::PLACEMENTS[$parsed['placement']] ?? $parsed['placement'];
                if ($parsed['provider_id'] !== null) {
                    $label .= ' (' . $parsed['provider_id'] . ')';
                } elseif ($parsed['service_id'] !== null) {
                    $label .= ' (' . $parsed['service_id'] . ')';
                }
                $usedIn[] = $label;
            }
        ?>
        <tr>
            <td><?= number_format((int) ($ad['impression_count'] ?? 0)) ?></td>
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
