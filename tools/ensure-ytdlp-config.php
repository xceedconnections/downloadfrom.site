<?php

declare(strict_types=1);

/**
 * Ensures config.local.php contains production yt-dlp + node paths after deploy.
 * Safe to run on every deploy — preserves existing DB credentials and other settings.
 */
$root = dirname(__DIR__);
$localFile = $root . '/config/config.local.php';

if (!is_file($localFile)) {
    fwrite(STDERR, "config.local.php not found — skipping yt-dlp config merge.\n");
    exit(0);
}

/** @var array<string, mixed> $local */
$local = require $localFile;

$ytdlpBin = $root . '/bin/yt-dlp';
$nodePath = '/usr/bin/node';
if (is_executable('/usr/bin/node')) {
    $nodePath = '/usr/bin/node';
} elseif (is_executable('/usr/local/bin/node')) {
    $nodePath = '/usr/local/bin/node';
} else {
    $which = trim((string) shell_exec('command -v node 2>/dev/null'));
    if ($which !== '' && is_executable($which)) {
        $nodePath = $which;
    }
}

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

$export = var_export($local, true);
$content = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Local/server overrides — not committed to git.\n */\nreturn {$export};\n";

if (file_put_contents($localFile, $content) === false) {
    fwrite(STDERR, "Failed to write config.local.php\n");
    exit(1);
}

echo "Updated config.local.php ytdlp.path={$ytdlpBin} node_path={$nodePath}\n";
