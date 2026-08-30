<?php

declare(strict_types=1);

/**
 * Ensures config.local.php contains production yt-dlp + node paths after deploy.
 * Safe to run on every deploy — preserves existing DB credentials and other settings.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

$root = dirname(__DIR__);
$localFile = $root . '/config/config.local.php';

if (!is_file($localFile)) {
    fwrite(STDERR, "config.local.php not found — skipping yt-dlp config merge.\n");
    exit(0);
}

/** @var array<string, mixed> $local */
$local = require $localFile;

$ytdlpBin = $root . '/bin/yt-dlp';
$resolvedNode = App\YtDlpHelper::resolveNodePath($config);
$nodePath = $resolvedNode ?? '/usr/bin/node';

$local['ytdlp'] = array_replace(
    [
        'path' => $ytdlpBin,
        'node_path' => $nodePath,
        'enabled' => true,
    ],
    is_array($local['ytdlp'] ?? null) ? $local['ytdlp'] : []
);

$local['ytdlp']['path'] = $ytdlpBin;
$local['ytdlp']['node_path'] = $nodePath;
$local['ytdlp']['enabled'] = !empty($local['ytdlp']['enabled']);

if ($resolvedNode === null) {
    fwrite(STDERR, "WARNING: Node.js not found in PATH or common locations — YouTube may return 360p only.\n");
    fwrite(STDERR, "Install Node: curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && apt install -y nodejs\n");
} else {
    $nodeVer = trim((string) App\YtDlpHelper::exec(escapeshellarg($resolvedNode) . ' --version 2>&1'));
    echo "Node verified: {$resolvedNode} ({$nodeVer})\n";
}

$export = var_export($local, true);
$content = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Local/server overrides — not committed to git.\n */\nreturn {$export};\n";

if (file_put_contents($localFile, $content) === false) {
    fwrite(STDERR, "Failed to write config.local.php\n");
    exit(1);
}

echo "Updated config.local.php ytdlp.path={$ytdlpBin} node_path={$nodePath}\n";
