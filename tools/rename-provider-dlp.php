<?php

declare(strict_types=1);

/**
 * Renames YtDlp.php to {provider}Dlp.php and updates Extractor references.
 * Run: php tools/rename-provider-dlp.php
 */

$root = dirname(__DIR__);
$providersPath = $root . '/app/provider';
if (!is_dir($providersPath)) {
    $providersPath = $root . '/app/video-provider';
}

function folderToClassPrefix(string $folder): string
{
    $parts = preg_split('/[-_]/', $folder) ?: [$folder];
    $parts = array_map(static fn(string $p): string => ucfirst(strtolower($p)), $parts);

    return implode('', $parts);
}

$dirs = glob($providersPath . '/*', GLOB_ONLYDIR) ?: [];

foreach ($dirs as $dir) {
    $folder = basename($dir);
    if (str_starts_with($folder, '.') || str_starts_with($folder, '_')) {
        continue;
    }
    if (!is_file($dir . '/Provider.php')) {
        continue;
    }

    $oldFile = $dir . '/YtDlp.php';
    if (!is_file($oldFile)) {
        echo "Skip {$folder}: no YtDlp.php\n";
        continue;
    }

    $className = folderToClassPrefix($folder) . 'Dlp';
    $newFileName = $folder . 'Dlp.php';
    $newFile = $dir . '/' . $newFileName;
    $ns = folderToClassPrefix($folder);

    $content = file_get_contents($oldFile);
    if ($content === false) {
        echo "Failed to read {$oldFile}\n";
        continue;
    }

    $content = str_replace('class YtDlp', 'class ' . $className, $content);
    $content = preg_replace(
        '/yt-dlp extraction for .+ — provider-local, not shared\./',
        'yt-dlp extraction for ' . $folder . ' — provider-local (' . $className . ').',
        $content
    );

    file_put_contents($newFile, $content);
    @unlink($oldFile);

    $extractorFile = $dir . '/Extractor.php';
    if (is_file($extractorFile)) {
        $extractor = file_get_contents($extractorFile);
        if ($extractor !== false) {
            $extractor = str_replace('YtDlp', $className, $extractor);
            if (!str_contains($extractor, 'require_once')) {
                $extractor = preg_replace(
                    '/(namespace App\\\\Provider\\\\' . preg_quote($ns, '/') . ';)\s*/',
                    "$1\n\nrequire_once __DIR__ . '/" . $newFileName . "';\n",
                    $extractor,
                    1
                );
            }
            file_put_contents($extractorFile, $extractor);
        }
    }

    echo "Renamed {$folder}/YtDlp.php -> {$newFileName} (class {$className})\n";
}

echo "Done.\n";
