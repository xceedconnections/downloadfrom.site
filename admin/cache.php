<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();

use App\Repositories\DownloadSessionRepository;
use App\Storage\DatabaseConnection;

$cache = new App\Cache($config);
$sessions = new DownloadSessionRepository(DatabaseConnection::get());
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!App\Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {
        $message = 'Invalid CSRF token.';
    } else {
        $cleared = $cache->clear();
        $message = "Cleared {$cleared} extraction cache file(s). Active result sessions expire when visitors leave the result page.";
    }
}

$stats = $cache->stats();
$activeSessions = $sessions->countActive();

$pageTitle = 'Cache Management';
require __DIR__ . '/layout/header.php';
?>

<h1>Cache Management</h1>

<div class="admin-stats">
    <div class="stat-card">
        <span class="stat-value"><?= (int) $stats['active_files'] ?></span>
        <span class="stat-label">Active extraction cache</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $activeSessions ?></span>
        <span class="stat-label">Active result sessions</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= (int) $stats['ttl'] ?>s</span>
        <span class="stat-label">Max cache TTL</span>
    </div>
</div>

<p class="admin-note">
    Extraction cache stores yt-dlp results temporarily (<code><?= App\Security::escape($stats['path']) ?></code>).
    Result sessions are removed automatically when a visitor leaves the result page or closes the tab.
    Seeing <strong>0</strong> here usually means cleanup is working — nothing is kept after visitors leave.
</p>

<p>
    Cache enabled: <strong><?= $stats['enabled'] ? 'Yes' : 'No' ?></strong> —
    Total files on disk: <strong><?= (int) $stats['total_files'] ?></strong>
</p>

<?php if ($message): ?><p class="admin-success"><?= App\Security::escape($message) ?></p><?php endif; ?>

<form method="POST">
    <?= App\Security::csrfField($config) ?>
    <button type="submit" class="btn btn-primary" onclick="return confirm('Clear all extraction cache files?')">Clear Extraction Cache</button>
</form>

<?php require __DIR__ . '/layout/footer.php'; ?>
