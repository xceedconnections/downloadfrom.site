<?php

declare(strict_types=1);

/** @var App\AdManager|null $adManager */
/** @var string $adPageType */
/** @var string|null $adServiceId */
/** @var string|null $adProviderId */
$adPageType = $adPageType ?? 'all';
$adServiceId = $adServiceId ?? null;
$adProviderId = $adProviderId ?? null;
if (!isset($adManager) || !$adManager instanceof App\AdManager) {
    return;
}
echo $adManager->renderZone($placement ?? '', $adPageType, $adServiceId, $adProviderId);
