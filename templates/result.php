<?php



$pageTitle = ($data['title'] ?? 'Download Result') . ' – ' . $config['app']['name'];

$pageDescription = 'Download result';

$robotsMeta = 'noindex, nofollow';



$isCombined = !empty($data['combined']) && !empty($data['sections']);

$adPageType = 'result';

$adServiceId = ($data['service'] ?? '') === App\ServiceConfig::SERVICE_AUDIO

    ? App\ServiceConfig::SERVICE_AUDIO

    : (($data['service'] ?? '') === App\ServiceConfig::SERVICE_ALL ? App\ServiceConfig::SERVICE_ALL : App\ServiceConfig::SERVICE_VIDEO);

require __DIR__ . '/header.php';



$hasSidebarAds = isset($adManager) && $adManager->getForPlacement('result_sidebar', 'result', $adServiceId) !== [];

$platformId = (string) ($data['platform'] ?? '');

$resultPlatform = ['id' => $platformId, 'icon' => $platformId];
foreach (array_merge($videoPlatforms ?? [], $audioPlatforms ?? []) as $id => $plat) {
    if ((string) $id === $platformId) {
        $resultPlatform = $plat;
        break;
    }
}



$videoSection = null;

$audioSection = null;



if ($isCombined) {

    foreach ($data['sections'] as $section) {

        if (($section['service_type'] ?? '') === 'audio') {

            $audioSection = $section;

        } else {

            $videoSection = $section;

        }

    }

} else {

    $serviceId = (string) ($data['service'] ?? App\ServiceConfig::SERVICE_VIDEO);

    $singleSection = [

        'service' => $serviceId,

        'service_type' => ($data['service_type'] ?? '') === 'audio' ? 'audio' : 'video',

        'label' => $serviceId === App\ServiceConfig::SERVICE_AUDIO ? 'Download Audio' : 'Download Video',

        'links' => $data['links'] ?? [],

    ];

    if (($singleSection['service_type'] ?? '') === 'audio') {

        $audioSection = $singleSection;

    } else {

        $videoSection = $singleSection;

    }

}



$hasVideoLinks = !empty($videoSection['links']);

$hasAudioLinks = !empty($audioSection['links']);

$hasAnyLinks = $hasVideoLinks || $hasAudioLinks;

$showCombinedSplit = $isCombined && $hasVideoLinks && $hasAudioLinks;



?>

<section class="section result-section">

    <div class="container">

        <?php $placement = 'result_top'; require __DIR__ . '/partials/ad-zone.php'; ?>



        <div class="result-layout<?= $hasSidebarAds ? ' has-ad-sidebar' : '' ?><?= $showCombinedSplit ? ' has-combined-downloads' : '' ?>">

            <div class="result-main">

                <div class="result-card<?= $showCombinedSplit ? ' result-card-combined' : '' ?>">

                    <div class="result-top">

                        <?php if (!empty($data['thumbnail'])): ?>

                        <div class="result-thumb">

                            <img src="<?= App\Security::escape($data['thumbnail']) ?>" alt="<?= App\Security::escape($data['title'] ?? 'Thumbnail') ?>" loading="lazy" width="480" height="270">

                        </div>

                        <?php endif; ?>



                        <div class="result-meta">

                            <span class="platform-tag">
                                <?php $p = $resultPlatform; $iconSize = 'xs'; require __DIR__ . '/partials/platform-icon.php'; ?>
                                <span><?= App\Security::escape($data['platform_name'] ?? ucfirst($data['platform'] ?? '')) ?></span>
                            </span>

                            <h1 class="result-title"><?= App\Security::escape($data['title'] ?? 'Download') ?></h1>



                            <?php if (!empty($data['author'])): ?>

                            <p class="result-author">By <?= App\Security::escape($data['author']) ?></p>

                            <?php endif; ?>



                            <?php if (!empty($data['duration'])): ?>

                            <p class="result-duration">Duration: <?= App\Security::escape(gmdate('H:i:s', (int) $data['duration'])) ?></p>

                            <?php endif; ?>



                            <?php if (!$showCombinedSplit): ?>

                                <?php

                                $activeSection = $hasVideoLinks ? $videoSection : $audioSection;

                                if ($activeSection !== null && !empty($activeSection['links'])):

                                    $sectionLabel = (string) ($activeSection['label'] ?? 'Downloads');

                                    $sectionServiceType = (string) ($activeSection['service_type'] ?? 'video');

                                    $allLinks = $activeSection['links'];

                                    require __DIR__ . '/partials/result-links.php';

                                endif;

                                ?>



                                <?php if (!$hasAnyLinks && !empty($data['notice'])): ?>

                                <div class="notice-box">

                                    <p><?= App\Security::escape($data['notice']) ?></p>

                                </div>

                                <?php endif; ?>

                            <?php endif; ?>

                        </div>

                    </div>



                    <?php if ($showCombinedSplit): ?>

                    <div class="result-downloads-panel">

                        <div class="result-download-split">

                            <div class="result-download-col result-download-video">

                                <?php

                                    $sectionLabel = (string) ($videoSection['label'] ?? 'Download Video');

                                    $sectionServiceType = 'video';

                                    $allLinks = $videoSection['links'];

                                    require __DIR__ . '/partials/result-links.php';

                                ?>

                            </div>

                            <div class="result-download-col result-download-audio">

                                <?php

                                    $sectionLabel = (string) ($audioSection['label'] ?? 'Download Audio');

                                    $sectionServiceType = 'audio';

                                    $allLinks = $audioSection['links'];

                                    require __DIR__ . '/partials/result-links.php';

                                ?>

                            </div>

                        </div>

                    </div>

                    <?php endif; ?>



                    <div class="result-actions">

                        <a href="<?= App\Security::escape($baseUrl) ?>/" class="btn btn-secondary">Process Another URL</a>

                    </div>

                </div>

            </div>



            <?php if ($hasSidebarAds): ?>

            <aside class="ad-sidebar" aria-label="Advertisement">

                <div class="ad-sidebar-inner">

                    <?php $placement = 'result_sidebar'; require __DIR__ . '/partials/ad-zone.php'; ?>

                </div>

            </aside>

            <?php endif; ?>

        </div>



        <?php $placement = 'result_bottom'; require __DIR__ . '/partials/ad-zone.php'; ?>

    </div>

</section>



<?php require __DIR__ . '/footer.php'; ?>

