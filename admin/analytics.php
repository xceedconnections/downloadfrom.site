<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();

use App\Security;
use App\VisitorAnalytics;

$visitorAnalytics = new VisitorAnalytics($config, $settings);
$message = '';
$error = '';

$days = max(1, min(90, (int) ($_GET['days'] ?? 7)));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {
        $error = 'Invalid CSRF token.';
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

    return gmdate('Y-m-d H:i:s', $timestamp) . ' UTC';
};

$pageTitle = 'Visitor Analytics';
require __DIR__ . '/layout/header.php';
?>

<h1>Visitor Analytics</h1>
<p class="admin-note">
    Tracks every public page visit: IP, country, URL, referrer, browser, OS, device, and time on page.
    Country is detected from Cloudflare (<code>CF-IPCountry</code>) when available.
    Enable or disable tracking in <a href="settings.php">Site Settings → Enable Analytics</a>.
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

<div class="admin-grid-3" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin:1.5rem 0">
    <div>
        <h2>Top countries</h2>
        <?php if (($summary['top_countries'] ?? []) === []): ?>
        <p class="admin-note">No data yet.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Country</th><th>Views</th></tr></thead>
            <tbody>
            <?php foreach ($summary['top_countries'] as $row): ?>
            <tr>
                <td><?= Security::escape($row['label'] !== '' ? $row['label'] : 'Unknown') ?></td>
                <td><?= number_format((int) $row['count']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div>
        <h2>Top pages</h2>
        <?php if (($summary['top_pages'] ?? []) === []): ?>
        <p class="admin-note">No data yet.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Path</th><th>Views</th></tr></thead>
            <tbody>
            <?php foreach ($summary['top_pages'] as $row): ?>
            <tr>
                <td><code><?= Security::escape($row['label']) ?></code></td>
                <td><?= number_format((int) $row['count']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div>
        <h2>Top browsers</h2>
        <?php if (($summary['top_browsers'] ?? []) === []): ?>
        <p class="admin-note">No data yet.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Browser</th><th>Views</th></tr></thead>
            <tbody>
            <?php foreach ($summary['top_browsers'] as $row): ?>
            <tr>
                <td><?= Security::escape($row['label']) ?></td>
                <td><?= number_format((int) $row['count']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<h2>Visit log</h2>
<p class="admin-note"><?= number_format($list['total']) ?> record(s) in the last <?= (int) $days ?> day(s).</p>

<?php if ($list['rows'] === []): ?>
<p>No visitor activity recorded yet. Open the public site in a browser to generate data.</p>
<?php else: ?>
<div class="admin-table-wrap" style="overflow-x:auto">
<table class="admin-table">
    <thead>
        <tr>
            <th>When</th>
            <th>IP</th>
            <th>Country</th>
            <th>Page</th>
            <th>Referrer</th>
            <th>Browser</th>
            <th>OS</th>
            <th>Device</th>
            <th>Duration</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($list['rows'] as $row): ?>
    <?php
        $countryLabel = (string) ($row['country_name'] ?? '');
        if ($countryLabel === '' && ($row['country_code'] ?? '') !== '') {
            $countryLabel = (string) $row['country_code'];
        }
        $referrer = (string) ($row['referrer_url'] ?? '');
        $pagePath = (string) ($row['page_path'] ?? '');
        $pageUrl = (string) ($row['page_url'] ?? '');
    ?>
    <tr>
        <td><?= Security::escape($formatWhen((int) ($row['visited_at'] ?? 0))) ?></td>
        <td><code><?= Security::escape((string) ($row['ip_address'] ?? '')) ?></code></td>
        <td><?= Security::escape($countryLabel !== '' ? $countryLabel : '—') ?></td>
        <td>
            <?php if ($pageUrl !== ''): ?>
            <a href="<?= Security::escape($pageUrl) ?>" target="_blank" rel="noopener noreferrer"><code><?= Security::escape($pagePath) ?></code></a>
            <?php else: ?>
            <code><?= Security::escape($pagePath) ?></code>
            <?php endif; ?>
        </td>
        <td style="max-width:220px;word-break:break-all">
            <?php if ($referrer !== ''): ?>
            <a href="<?= Security::escape($referrer) ?>" target="_blank" rel="noopener noreferrer"><?= Security::escape($referrer) ?></a>
            <?php else: ?>
            —
            <?php endif; ?>
        </td>
        <td><?= Security::escape((string) ($row['browser'] ?? '—')) ?></td>
        <td><?= Security::escape((string) ($row['os_name'] ?? '—')) ?></td>
        <td><?= Security::escape((string) ($row['device_type'] ?? '—')) ?></td>
        <td><?= Security::escape($formatDuration((int) ($row['duration_seconds'] ?? 0))) ?></td>
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

<hr style="margin:2rem 0">

<form method="POST" onsubmit="return confirm('Delete ALL visitor analytics records? This cannot be undone.')">
    <?= Security::csrfField($config) ?>
    <input type="hidden" name="clear_visitor_analytics" value="1">
    <button type="submit" class="btn btn-secondary">Clear All Visitor Data</button>
</form>

<?php require __DIR__ . '/layout/footer.php'; ?>
