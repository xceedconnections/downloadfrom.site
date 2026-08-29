<?php

declare(strict_types=1);

namespace App\Provider;

use App\Logger;
use App\ProviderRegistry;

/**
 * Discovers provider plugins from app/video-provider/ or app/audio-provider/ directories.
 * Delete or rename a folder and it disappears from the portal automatically.
 */
class ProviderLoader
{
    private string $providersPath;

    public function __construct(?string $providersPath = null, private string $namespaceRoot = 'App\\Provider')
    {
        $this->providersPath = $providersPath ?? dirname(__DIR__) . '/provider';
    }

    /**
     * @return array<int, array{manifest: array, dir: string, folder: string}>
     */
    public function discover(): array
    {
        if (!is_dir($this->providersPath)) {
            return [];
        }

        $plugins = [];
        $dirs = glob($this->providersPath . '/*', GLOB_ONLYDIR) ?: [];

        sort($dirs);

        foreach ($dirs as $dir) {
            $folder = basename($dir);
            if (str_starts_with($folder, '.') || str_starts_with($folder, '_')) {
                continue;
            }

            $manifestFile = $dir . '/manifest.php';
            $providerFile = $dir . '/Provider.php';

            if (!is_file($manifestFile) || !is_file($providerFile)) {
                Logger::info("Provider skipped (missing manifest or Provider.php): {$folder}");
                continue;
            }

            $manifest = require $manifestFile;
            if (!is_array($manifest)) {
                Logger::error("Invalid manifest in provider: {$folder}");
                continue;
            }

            if (($manifest['enabled'] ?? true) === false) {
                continue;
            }

            $manifest['id'] = $manifest['id'] ?? $folder;
            $manifest['folder'] = $folder;
            $manifest['path'] = $dir;

            if (empty($manifest['slug'])) {
                $manifest['slug'] = $folder;
            }

            if (empty($manifest['name'])) {
                $manifest['name'] = ucfirst($manifest['id']);
            }

            $plugins[] = [
                'manifest' => $manifest,
                'dir' => $dir,
                'folder' => $folder,
            ];
        }

        return $plugins;
    }

    public function folderToNamespace(string $folder): string
    {
        $parts = preg_split('/[-_]/', $folder) ?: [$folder];
        $parts = array_map(static fn(string $p): string => ucfirst(strtolower($p)), $parts);

        return $this->namespaceRoot . '\\' . implode('', $parts);
    }

    public function getNamespaceRoot(): string
    {
        return $this->namespaceRoot;
    }

    public function getProvidersPath(): string
    {
        return $this->providersPath;
    }
}
