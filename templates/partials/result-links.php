<?php
/** @var array<string, mixed> $data */
/** @var array<int, array<string, mixed>> $allLinks */
/** @var string $sectionLabel */
/** @var string $sectionServiceType */
/** @var string $platformId */

$downloadLinks = array_values(array_filter($allLinks, static fn(array $l): bool => !empty($l['download'])));
$otherLinks = array_values(array_filter($allLinks, static fn(array $l): bool => empty($l['download'])));
?>
<?php if (!empty($downloadLinks)): ?>
<div class="links-table">
    <h2><?= App\Security::escape($sectionLabel) ?></h2>
    <p class="download-hint">Files download with the video title as the filename (quality is included in the name).</p>
        <?php foreach ($downloadLinks as $idx => $link): ?>
    <?php
        $originalIndex = $idx;
        foreach ($data['links'] as $i => $candidate) {
            if (($candidate['url'] ?? '') === ($link['url'] ?? '')
                && ($candidate['label'] ?? '') === ($link['label'] ?? '')) {
                $originalIndex = $i;
                break;
            }
        }
        if (!empty($resultToken)) {
            $downloadUrl = $baseUrl . '/download/' . $resultToken . '/' . $originalIndex;
            $downloadTarget = '';
        } else {
            $downloadUrl = $link['url'] ?? '';
            $downloadTarget = '_blank';
        }
    ?>
    <div class="link-row link-row-download">
        <span class="link-label">
            <strong><?= App\Security::escape($link['label']) ?></strong>
            <?php if (!empty($link['quality']) && !str_ends_with((string) $link['quality'], 'k') && $link['quality'] !== 'audio'): ?>
            <span class="quality-badge"><?= App\Security::escape($link['quality']) ?></span>
            <?php endif; ?>
        </span>
        <?php if ($downloadUrl !== ''): ?>
        <button type="button"
            class="btn btn-primary btn-sm btn-download"
            data-download-url="<?= App\Security::escape($downloadUrl) ?>"
            data-download-service="<?= App\Security::escape($sectionServiceType) ?>"<?= $downloadTarget ? ' data-download-target="' . App\Security::escape($downloadTarget) . '"' : '' ?>>
            Download
        </button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($otherLinks)): ?>
<div class="links-table links-secondary">
    <h2>Other Options</h2>
    <?php foreach ($otherLinks as $link): ?>
    <div class="link-row">
        <span class="link-label"><?= App\Security::escape($link['label']) ?></span>
        <a href="<?= App\Security::escape($link['url']) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener noreferrer">Open</a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
