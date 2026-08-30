<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();

use App\Security;
use App\VisitorAnalytics;
use App\VisitorAnalyticsDisplay;

$visitorAnalytics = new VisitorAnalytics($config, $settings);
$message = '';
$error = '';

$days = max(1, min(90, (int) ($_GET['days'] ?? 7)));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$siteHost = (string) (parse_url($config['app']['url'] ?? '', PHP_URL_HOST) ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {
        $error = 'Invalid CSRF token.';
    } elseif (isset($_POST['clear_legacy_hashed'])) {
        $cleared = $visitorAnalytics->clearLegacyHashed();
        $message = 'Removed ' . number_format($cleared) . ' legacy hashed IP record(s). New visits will store real IPv4 addresses.';
        $page = 1;
    } elseif (isset($_POST['clear_visitor_analytics'])) {
        $cleared = $visitorAnalytics->clearAll();
        $message = 'Cleared ' . number_format($cleared) . ' visitor record(s).';
        $page = 1;
    }
}

$summary = $visitorAnalytics->getSummary($days);
$list = $visitorAnalytics->listVisits($days, $page, $perPage);
$totalPages = max(1, (int) ceil($list['total'] / $perPage));

$formatDuration = static function (int $seconds): string {
    if ($seconds <= 0) {
        return '—';
    }
    if ($seconds < 60) {
        return $seconds . 's';
    }
    $mins = intdiv($seconds, 60);
    $secs = $seconds % 60;

    return $mins . 'm ' . $secs . 's';
};

$formatWhen = static function (int $timestamp): string {
    if ($timestamp <= 0) {
        return '—';
    }

    return gmdate('M j, Y g:i A', $timestamp) . ' UTC';
};

$pageTitle = 'Visitor Analytics';
require __DIR__ . '/layout/header.php';
?>

<h1>Visitor Analytics</h1>
<p class="admin-note">
    Real visitor IPv4/IPv6 addresses, traffic sources, countries, and time on page.
    If you see long hex strings instead of IPs, those are <strong>old hashed records</strong> — remove them with the button below, then visit the public site again.
    Tracking can be toggled in <a href="settings.php">Site Settings → Enable Analytics</a>.
</p>

<?php if ($message): ?><p class="admin-success"><?= Security::escape($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="admin-error"><?= Security::escape($error) ?></p><?php endif; ?>

<div class="admin-tabs">
    <?php foreach ([1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $label): ?>
    <a href="analytics.php?days=<?= $d ?>" class="admin-tab<?= $days === $d ? ' active' : '' ?>"><?= Security::escape($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="admin-stats">
    <div class="stat-card">
        <span class="stat-value"><?= number_format((int) ($summary['total_visits'] ?? 0)) ?></span>
        <span class="stat-label">Page views</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= number_format((int) ($summary['unique_ips'] ?? 0)) ?></span>
        <span class="stat-label">Unique IPs</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= number_format((int) ($summary['unique_sessions'] ?? 0)) ?></span>
        <span class="stat-label">Sessions</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= Security::escape((string) ($summary['avg_duration'] ?? 0)) ?>s</span>
        <span class="stat-label">Avg. time on page</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $formatDuration((int) ($summary['max_duration'] ?? 0)) ?></span>
        <span class="stat-label">Longest visit</span>
    </div>
</div>

<div class="va-summary-grid">
    <div class="va-summary-card">
        <h2>Top countries</h2>
        <?php if (($summary['top_countries'] ?? []) === []): ?>
        <p class="admin-note">No data yet.</p>
        <?php else: ?>
        <ul class="va-rank-list">
            <?php foreach ($summary['top_countries'] as $row):
                $code = strtoupper((string) ($row['code'] ?? ''));
                $flag = VisitorAnalyticsDisplay::countryFlag($code);
                $name = (string) ($row['label'] ?? '');
                if ($name === '' && $code !== '') {
                    $name = \App\GeoLookup::countryName($code);
                }
            ?>
            <li>
                <span class="va-rank-label"><?= $flag !== '' ? $flag . ' ' : '🌍 ' ?><?= Security::escape($name !== '' ? $name : 'Unknown') ?></span>
                <span class="va-rank-count"><?= number_format((int) $row['count']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <div class="va-summary-card">
        <h2>Top pages</h2>
        <?php if (($summary['top_pages'] ?? []) === []): ?>
        <p class="admin-note">No data yet.</p>
        <?php else: ?>
        <ul class="va-rank-list">
            <?php foreach ($summary['top_pages'] as $row): ?>
            <li>
                <span class="va-rank-label"><?= Security::escape((string) ($row['label'] ?? '')) ?></span>
                <span class="va-rank-count"><?= number_format((int) $row['count']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <div class="va-summary-card">
        <h2>Traffic sources</h2>
        <?php if (($summary['top_referrers'] ?? []) === []): ?>
        <p class="admin-note">No data yet.</p>
        <?php else: ?>
        <ul class="va-rank-list">
            <?php foreach ($summary['top_referrers'] as $row): ?>
            <li>
                <span class="va-rank-label"><span class="<?= Security::escape(VisitorAnalyticsDisplay::referrerBadgeClass((string) ($row['label'] ?? ''))) ?>"><?= Security::escape((string) ($row['label'] ?? '')) ?></span></span>
                <span class="va-rank-count"><?= number_format((int) $row['count']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <div class="va-summary-card">
        <h2>Top browsers</h2>
        <?php if (($summary['top_browsers'] ?? []) === []): ?>
        <p class="admin-note">No data yet.</p>
        <?php else: ?>
        <ul class="va-rank-list">
            <?php foreach ($summary['top_browsers'] as $row): ?>
            <li>
                <span class="va-rank-label"><?= Security::escape((string) ($row['label'] ?? '')) ?></span>
                <span class="va-rank-count"><?= number_format((int) $row['count']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<h2>Visit log</h2>
<p class="admin-note"><?= number_format($list['total']) ?> real page visit(s) in the last <?= (int) $days ?> day(s). Bot scans and favicon requests are excluded.</p>

<?php if ($list['rows'] === []): ?>
<p>No visitor activity recorded yet. Open the public site in a browser to generate data.</p>
<?php else: ?>
<div class="va-log-wrap">
<table class="admin-table va-log-table">
    <thead>
        <tr>
            <th>When</th>
            <th>Visitor</th>
            <th>Country</th>
            <th>Page visited</th>
            <th>How they arrived</th>
            <th>Device</th>
            <th>Duration</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($list['rows'] as $row): ?>
    <?php
        $countryCode = strtoupper((string) ($row['country_code'] ?? ''));
        $countryName = (string) ($row['country_name'] ?? '');
        if ($countryName === '' && $countryCode !== '') {
            $countryName = \App\GeoLookup::countryName($countryCode);
        }
        $flag = VisitorAnalyticsDisplay::countryFlag($countryCode);
        $referrer = (string) ($row['referrer_url'] ?? '');
        $refSource = (string) ($row['referrer_source'] ?? '');
        if ($refSource === '' && $referrer !== '') {
            $refSource = VisitorAnalyticsDisplay::referrerInfo($referrer, $siteHost)['source'];
        } elseif ($refSource === '') {
            $refSource = 'Direct';
        }
        $refInfo = VisitorAnalyticsDisplay::referrerInfo($referrer, $siteHost);
        $pagePath = (string) ($row['page_path'] ?? '');
        $pageUrl = (string) ($row['page_url'] ?? '');
        $pageTitle = (string) ($row['page_title'] ?? '');
        if ($pageTitle === '') {
            $pageTitle = VisitorAnalyticsDisplay::pageLabel($pagePath, $videoPlatforms, $audioPlatforms);
        }
        $ip = VisitorAnalyticsDisplay::formatIp((string) ($row['ip_address'] ?? ''));
        $browser = (string) ($row['browser'] ?? '—');
        $os = (string) ($row['os_name'] ?? '—');
        $device = (string) ($row['device_type'] ?? 'desktop');
    ?>
    <tr>
        <td class="va-when"><?= Security::escape($formatWhen((int) ($row['visited_at'] ?? 0))) ?></td>
        <td class="va-ip"><code><?= Security::escape($ip) ?></code></td>
        <td class="va-country">
            <?php if ($countryName !== '' || $flag !== ''): ?>
            <span class="va-country-pill"><?= $flag !== '' ? $flag . ' ' : '🌍 ' ?><?= Security::escape($countryName !== '' ? $countryName : 'Unknown') ?></span>
            <?php else: ?>
            <span class="va-country-pill va-muted">Unknown</span>
            <?php endif; ?>
        </td>
        <td class="va-page">
            <strong><?= Security::escape($pageTitle) ?></strong>
            <?php if ($pageUrl !== ''): ?>
            <a class="va-page-url" href="<?= Security::escape($pageUrl) ?>" target="_blank" rel="noopener noreferrer"><?= Security::escape($pageUrl) ?></a>
            <?php elseif ($pagePath !== ''): ?>
            <span class="va-page-url"><?= Security::escape($pagePath) ?></span>
            <?php endif; ?>
        </td>
        <td class="va-referrer">
            <span class="<?= Security::escape(VisitorAnalyticsDisplay::referrerBadgeClass($refSource)) ?>"><?= Security::escape($refSource) ?></span>
            <?php if ($refInfo['external'] && $referrer !== ''): ?>
            <a class="va-ref-url" href="<?= Security::escape($referrer) ?>" target="_blank" rel="noopener noreferrer" title="<?= Security::escape($referrer) ?>"><?= Security::escape(parse_url($referrer, PHP_URL_HOST) ?: $referrer) ?></a>
            <?php elseif ($refSource === 'Direct'): ?>
            <span class="va-ref-url">No referrer — direct visit</span>
            <?php elseif ($refInfo['detail'] !== '' && !$refInfo['external']): ?>
            <span class="va-ref-url"><?= Security::escape($refInfo['detail']) ?></span>
            <?php endif; ?>
        </td>
        <td class="va-device">
            <span class="va-device-type"><?= Security::escape(ucfirst($device)) ?></span>
            <span class="va-device-meta"><?= Security::escape($browser) ?></span>
            <span class="va-device-meta"><?= Security::escape($os) ?></span>
        </td>
        <td class="va-duration"><?= Security::escape($formatDuration((int) ($row['duration_seconds'] ?? 0))) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<p class="admin-pagination">
    <?php if ($page > 1): ?>
    <a href="analytics.php?days=<?= $days ?>&amp;page=<?= $page - 1 ?>">&larr; Previous</a>
    <?php endif; ?>
    <span>Page <?= (int) $page ?> of <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
    <a href="analytics.php?days=<?= $days ?>&amp;page=<?= $page + 1 ?>">Next &rarr;</a>
    <?php endif; ?>
</p>
<?php endif; ?>
<?php endif; ?>

<hr class="va-divider">

<form method="POST" style="display:inline-block;margin-right:.75rem" onsubmit="return confirm('Remove all visitor rows with hashed IPs (old format)?')">
    <?= Security::csrfField($config) ?>
    <input type="hidden" name="clear_legacy_hashed" value="1">
    <button type="submit" class="btn btn-primary">Remove Legacy Hashed IPs</button>
</form>

<form method="POST" style="display:inline-block" onsubmit="return confirm('Delete ALL visitor analytics records? This cannot be undone.')">
    <?= Security::csrfField($config) ?>
    <input type="hidden" name="clear_visitor_analytics" value="1">
    <button type="submit" class="btn btn-secondary">Clear All Visitor Data</button>
</form>

<?php require __DIR__ . '/layout/footer.php'; ?>
