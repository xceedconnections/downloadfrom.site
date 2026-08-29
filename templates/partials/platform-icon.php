<?php

declare(strict_types=1);

/** @var array<string, mixed> $p */
/** @var string $baseUrl */

$iconSize = $iconSize ?? 'md';
$p = $p ?? [];
$iconUrl = App\PlatformConfig::iconUrl($p, $baseUrl);
if ($iconUrl === null) {
    return;
}

$sizeMap = [
    'xs' => 16,
    'sm' => 20,
    'md' => 24,
    'lg' => 32,
];
$px = $sizeMap[$iconSize] ?? $sizeMap['md'];

?>
<img
    src="<?= App\Security::escape($iconUrl) ?>"
    alt=""
    class="platform-icon platform-icon-<?= App\Security::escape($iconSize) ?>"
    width="<?= (int) $px ?>"
    height="<?= (int) $px ?>"
    loading="lazy"
    decoding="async"
    aria-hidden="true"
>
