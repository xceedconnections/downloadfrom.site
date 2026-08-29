<?php

declare(strict_types=1);

/** @var string $serviceId */
/** @var string $baseUrl */

$iconSize = $iconSize ?? 'sm';
$iconUrl = App\PlatformConfig::serviceIconUrl($serviceId, $baseUrl);
if ($iconUrl === null) {
    return;
}

$sizeMap = [
    'xs' => 16,
    'sm' => 20,
    'md' => 24,
    'lg' => 32,
];
$px = $sizeMap[$iconSize] ?? $sizeMap['sm'];

?>
<img
    src="<?= App\Security::escape($iconUrl) ?>"
    alt=""
    class="service-icon service-icon-<?= App\Security::escape($iconSize) ?>"
    width="<?= (int) $px ?>"
    height="<?= (int) $px ?>"
    loading="lazy"
    decoding="async"
    aria-hidden="true"
>
