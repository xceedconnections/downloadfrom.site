<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();

$analytics = new App\Analytics($db, $config);
$cache = new App\Cache($config);
$summary = $analytics->getSummary(7);
$rateStats = $rateLimiter->getStats();

$pageTitle = 'Dashboard';
require __DIR__ . '/layout/header.php';
?>

<h1>Dashboard</h1>

<div class="admin-stats">
    <div class="stat-card">
        <span class="stat-value"><?= (int) $summary['total'] ?></span>
        <span class="stat-label">Requests (7 days)</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= (int) $summary['success'] ?></span>
        <span class="stat-label">Successful</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= (int) $summary['failed'] ?></span>
        <span class="stat-label">Failed</span>
    </div>
    <div class="stat-card">
        <?php
        $cacheStats = $cache->stats();
        $sessionRepo = new App\Repositories\DownloadSessionRepository(App\Storage\DatabaseConnection::get());
        ?>
        <span class="stat-value"><?= (int) $cacheStats['active_files'] ?></span>
        <span class="stat-label">Extraction cache</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $sessionRepo->countActive() ?></span>
        <span class="stat-label">Active sessions</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= (int) $rateStats['requests_24h'] ?></span>
        <span class="stat-label">Rate-limited (24h)</span>
    </div>
</div>

<h2>Daily Breakdown</h2>
<table class="admin-table">
    <thead><tr><th>Date</th><th>Total</th><th>Success</th><th>Failed</th><th>Avg Response (ms)</th></tr></thead>
    <tbody>
    <?php foreach ($summary['days'] as $date => $day): ?>
    <tr>
        <td><?= App\Security::escape($date) ?></td>
        <td><?= (int) $day['total'] ?></td>
        <td><?= (int) $day['success'] ?></td>
        <td><?= (int) $day['failed'] ?></td>
        <td><?= App\Security::escape((string) $day['avg_response_ms']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/layout/footer.php'; ?>
