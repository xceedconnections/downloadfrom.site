<?php

declare(strict_types=1);

/**
 * Scaffolds app/audio-provider/{platform}/ plugins.
 * Run: php tools/scaffold-audio-providers.php
 */

$root = dirname(__DIR__);
$audioPath = $root . '/app/audio-provider';
$videoPath = $root . '/app/video-provider';

if (!is_dir($audioPath)) {
    mkdir($audioPath, 0750, true);
}

$providers = [
    'youtube' => [
        'name' => 'YouTube',
        'slug' => 'youtube-mp3',
        'title' => 'YouTube to MP3 Downloader – Convert YouTube Videos to MP3',
        'h1' => 'YouTube to MP3 Downloader',
        'meta_description' => 'Paste a YouTube URL to download MP3 audio. Free YouTube to MP3 converter with multiple quality options.',
        'description' => 'Download MP3 audio from YouTube videos. Paste any public YouTube, youtu.be, or Shorts link.',
        'keywords' => 'youtube mp3, youtube to mp3, youtube audio download',
        'supported_domains' => ['youtube.com', 'youtu.be', 'm.youtube.com', 'music.youtube.com'],
        'host_patterns' => ['/^youtube\\.com$/', '/^youtu\\.be$/', '/^music\\.youtube\\.com$/'],
        'allowed_domains' => ['youtube.com', 'youtu.be', 'googlevideo.com', 'i.ytimg.com'],
        'url_examples' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://youtu.be/dQw4w9WgXcQ'],
    ],
    'soundcloud' => [
        'name' => 'SoundCloud',
        'slug' => 'soundcloud-mp3',
        'title' => 'SoundCloud to MP3 Downloader',
        'h1' => 'SoundCloud MP3 Downloader',
        'meta_description' => 'Download MP3 from SoundCloud tracks. Paste a SoundCloud URL to get direct audio download links.',
        'description' => 'Download MP3 audio from public SoundCloud tracks and playlists.',
        'keywords' => 'soundcloud mp3, soundcloud downloader, soundcloud to mp3',
        'supported_domains' => ['soundcloud.com', 'on.soundcloud.com'],
        'host_patterns' => ['/^soundcloud\\.com$/', '/^on\\.soundcloud\\.com$/'],
        'allowed_domains' => ['soundcloud.com', 'sndcdn.com', 'scdn.co'],
        'url_examples' => ['https://soundcloud.com/artist/track-name'],
    ],
    'tiktok' => [
        'name' => 'TikTok',
        'slug' => 'tiktok-mp3',
        'title' => 'TikTok to MP3 Downloader',
        'h1' => 'TikTok MP3 Downloader',
        'meta_description' => 'Extract MP3 audio from TikTok videos. Paste a TikTok link to download the audio track.',
        'description' => 'Download MP3 audio from TikTok videos without watermark when available.',
        'keywords' => 'tiktok mp3, tiktok audio download, tiktok to mp3',
        'supported_domains' => ['tiktok.com', 'vm.tiktok.com', 'vt.tiktok.com'],
        'host_patterns' => ['/^tiktok\\.com$/', '/^vm\\.tiktok\\.com$/', '/^vt\\.tiktok\\.com$/'],
        'allowed_domains' => ['tiktok.com', 'vm.tiktok.com', 'vt.tiktok.com', 'tiktokcdn.com', 'tikwm.com'],
        'url_examples' => ['https://www.tiktok.com/@user/video/1234567890'],
    ],
];

function folderToNs(string $folder): string
{
    $parts = preg_split('/[-_]/', $folder) ?: [$folder];
    $parts = array_map(static fn(string $p): string => ucfirst(strtolower($p)), $parts);
    return 'App\\AudioProvider\\' . implode('', $parts);
}

function folderToClass(string $folder): string
{
    $parts = preg_split('/[-_]/', $folder) ?: [$folder];
    $parts = array_map(static fn(string $p): string => ucfirst(strtolower($p)), $parts);
    return implode('', $parts);
}

foreach ($providers as $folder => $meta) {
    $dir = $audioPath . '/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $ns = folderToNs($folder);
    $classPrefix = folderToClass($folder);
    $dlpFile = $folder . 'Dlp.php';
    $dlpClass = $classPrefix . 'Dlp';

    $videoDlp = $videoPath . '/' . $folder . '/' . $folder . 'Dlp.php';
    if (is_file($videoDlp)) {
        $dlpContent = file_get_contents($videoDlp);
        $dlpContent = str_replace('namespace App\\Provider\\' . $classPrefix, 'namespace ' . $ns, $dlpContent);
        $dlpContent = str_replace('class ' . $dlpClass, 'class ' . $dlpClass, $dlpContent);
        file_put_contents($dir . '/' . $dlpFile, $dlpContent);
    }

    $manifest = array_merge($meta, [
        'id' => $folder,
        'icon' => $folder,
        'service_type' => 'audio',
        'download_supported' => true,
        'downloads_only' => true,
        'how_to' => [
            'Copy the track or video URL from ' . $meta['name'] . '.',
            'Paste it into the input box on this page.',
            'Click Generate Links to extract MP3 download options.',
            'Choose your preferred audio quality and download.',
        ],
        'faq' => [
            ['q' => 'Is this ' . $meta['name'] . ' MP3 downloader free?', 'a' => 'Yes, it is free for public URLs.'],
            ['q' => 'What format do I get?', 'a' => 'MP3, M4A, or other audio formats when available from the source.'],
        ],
    ]);

    $manifestExport = var_export($manifest, true);
    file_put_contents($dir . '/manifest.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn {$manifestExport};\n");

    $providerPhp = <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

class Provider extends \\App\\Provider\\AbstractProvider
{
    public function getMetadata(string \$url): array
    {
        return [
            'success' => true,
            'data' => [
                'title' => '{$meta['name']} Audio',
                'author' => null,
                'thumbnail' => null,
                'duration' => null,
                'platform_name' => '{$meta['name']}',
            ],
        ];
    }

    public function getAvailableLinks(string \$url): array
    {
        return [];
    }
}

PHP;
    file_put_contents($dir . '/Provider.php', $providerPhp);

    $extractorPhp = <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use App\\HttpClient;

require_once __DIR__ . '/{$dlpFile}';

class Extractor
{
    private HttpClient \$http;
    private {$dlpClass} \$dlp;

    public function __construct(HttpClient \$http, array \$config = [])
    {
        \$this->http = \$http;
        \$this->dlp = new {$dlpClass}(\$config);
    }

    public function extract(string \$url): array
    {
        \$links = \$this->dlp->extract(\$url);
        return \$this->audioOnly(\$links);
    }

    /** @param array<int, array<string, mixed>> \$links @return array<int, array<string, mixed>> */
    private function audioOnly(array \$links): array
    {
        \$audio = [];
        foreach (\$links as \$link) {
            \$quality = (string) (\$link['quality'] ?? '');
            \$ext = strtolower((string) (\$link['ext'] ?? ''));
            if (\$quality === 'audio' || in_array(\$ext, ['mp3', 'm4a', 'opus', 'aac', 'ogg', 'wav'], true)) {
                if (\$ext === 'm4a' || \$ext === 'opus') {
                    \$link['label'] = str_replace(strtoupper(\$ext), 'MP3', (string) (\$link['label'] ?? 'Audio'));
                    \$link['ext'] = 'mp3';
                }
                \$audio[] = \$link;
            }
        }

        return \$audio;
    }
}

PHP;
    file_put_contents($dir . '/Extractor.php', $extractorPhp);

    echo "Scaffolded audio provider: {$folder}\n";
}

echo "Done.\n";
