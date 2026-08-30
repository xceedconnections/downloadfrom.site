<?php

declare(strict_types=1);

/**
 * Server diagnostic — run: php tools/diagnose.php
 */

$config = require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Storage\DatabaseConnection;
use App\Storage\DatabaseInstaller;
use App\Storage\StorageFactory;

header('Content-Type: text/plain; charset=utf-8');

echo "PHP " . PHP_VERSION . "\n";
echo "Site root: " . dirname(__DIR__) . "\n\n";

if (!is_file(dirname(__DIR__) . '/config/config.local.php')) {
    echo "FAIL: config/config.local.php missing\n";
    exit(1);
}

try {
    if (!DatabaseInstaller::ensureSchema($config)) {
        echo "FAIL: ensureSchema returned false\n";
        exit(1);
    }
    echo "OK: database schema\n";

    $db = StorageFactory::create($config);
    echo "OK: StorageFactory\n";

    $pdo = DatabaseConnection::get();
    $counts = DatabaseInstaller::tableCounts($config);
    foreach ($counts as $table => $count) {
        echo "  {$table}: {$count}\n";
    }

    $logDir = $config['storage']['logs'] ?? '';
    $logFile = $logDir . '/error-' . date('Y-m-d') . '.log';
    if (is_file($logFile)) {
        echo "\nLast log lines (" . basename($logFile) . "):\n";
        $lines = file($logFile, FILE_IGNORE_NEW_LINES) ?: [];
        echo implode("\n", array_slice($lines, -15));
        echo "\n";
    } else {
        echo "\nNo error log for today: {$logFile}\n";
    }

    echo "\nDiagnosis complete.\n";

    echo "\n--- yt-dlp / YouTube ---\n";
    $env = App\YtDlpHelper::environmentStatus($config);
    echo 'yt-dlp: ' . ($env['ytdlp'] ?? 'MISSING') . "\n";
    echo 'yt-dlp version: ' . ($env['ytdlp_version'] ?? 'n/a') . "\n";
    echo 'node: ' . ($env['node'] ?? 'MISSING') . "\n";
    echo 'node version: ' . ($env['node_version'] ?? 'n/a') . "\n";
    echo 'proc_open: ' . ($env['proc_open'] ? 'yes' : 'NO') . "\n";
    echo 'shell_exec: ' . ($env['shell_exec'] ? 'yes' : 'NO') . "\n";

    passthru('php ' . escapeshellarg(dirname(__DIR__) . '/tools/verify-ytdlp.php'));
} catch (Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}
