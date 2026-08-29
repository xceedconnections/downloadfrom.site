<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();

$logDir = $config['storage']['logs'];
$logFiles = glob($logDir . '/*.log') ?: [];
rsort($logFiles);

$selectedLog = $_GET['file'] ?? ($logFiles[0] ?? '');
$logContent = '';
if ($selectedLog && is_file($logDir . '/' . basename($selectedLog))) {
    $lines = file($logDir . '/' . basename($selectedLog), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $logContent = implode("\n", array_slice($lines, -200));
}

$pageTitle = 'Logs';
require __DIR__ . '/layout/header.php';
?>

<h1>Logs</h1>

<form method="GET" class="admin-form-inline">
    <select name="file" onchange="this.form.submit()">
        <?php foreach ($logFiles as $file): ?>
        <option value="<?= App\Security::escape(basename($file)) ?>" <?= basename($file) === basename($selectedLog) ? 'selected' : '' ?>>
            <?= App\Security::escape(basename($file)) ?>
        </option>
        <?php endforeach; ?>
    </select>
</form>

<pre class="log-viewer"><?= App\Security::escape($logContent ?: 'No log entries.') ?></pre>

<?php require __DIR__ . '/layout/footer.php'; ?>
