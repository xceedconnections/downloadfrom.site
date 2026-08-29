<?php

declare(strict_types=1);

/** @var App\AdManager|null $adManager */
/** @var string $adPageType */
/** @var string|null $adServiceId */
$adPageType = $adPageType ?? 'all';
$adServiceId = $adServiceId ?? null;
if (!isset($adManager) || !$adManager instanceof App\AdManager) {
    return;
}
echo $adManager->renderZone($placement ?? '', $adPageType, $adServiceId);
