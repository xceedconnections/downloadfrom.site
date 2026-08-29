<?php

declare(strict_types=1);

/**
 * One-time migration: build app/provider/{platform}/ plugin folders from legacy providers.
 */

$root = dirname(__DIR__);
$platforms = require $root . '/config/platforms.php';

$hostPatterns = [
    'youtube' => ['/^youtube\.com$/', '/^youtu\.be$/', '/^music\.youtube\.com$/'],
    'tiktok' => ['/^tiktok\.com$/', '/^vm\.tiktok\.com$/', '/^vt\.tiktok\.com$/'],
    'vimeo' => ['/^vimeo\.com$/', '/^player\.vimeo\.com$/'],
    'dailymotion' => ['/^dailymotion\.com$/', '/^dai\.ly$/'],
    'instagram' => ['/^instagram\.com$/'],
    'facebook' => ['/^facebook\.com$/', '/^fb\.watch$/'],
    'twitter' => ['/^twitter\.com$/', '/^x\.com$/'],
    'reddit' => ['/^reddit\.com$/', '/^v\.redd\.it$/'],
    'twitch' => ['/^twitch\.tv$/', '/^clips\.twitch\.tv$/'],
    'pinterest' => ['/^pinterest\.com$/', '/^pin\.it$/'],
];

$allowedExtra = [
    'youtube' => ['googlevideo.com', 'i.ytimg.com', 's.ytimg.com'],
    'instagram' => ['cdninstagram.com'],
    'facebook' => ['fbcdn.net'],
    'twitter' => ['publish.twitter.com', 'video.twimg.com', 'twimg.com'],
    'reddit' => ['i.redd.it'],
];

$mediaExtractPlatforms = ['tiktok', 'vimeo', 'dailymotion', 'reddit', 'twitter', 'instagram', 'facebook'];

$extraPlatforms = [
    'snapchat' => [
        'name' => 'Snapchat',
        'slug' => 'snapchat-video-downloader',
        'title' => 'Snapchat Video Link Tool',
        'h1' => 'Snapchat Video Link Tool',
        'meta_description' => 'Paste a Snapchat URL to identify content and view permitted information.',
        'description' => 'Submit a Snapchat link. We identify the platform and provide viewing guidance.',
        'icon' => 'snapchat',
        'keywords' => 'snapchat video, snapchat link',
        'supported_domains' => ['snapchat.com', 'www.snapchat.com'],
        'how_to' => ['Copy the Snapchat link.', 'Paste it here.', 'Click Generate Links.'],
        'faq' => [],
        'url_examples' => ['https://www.snapchat.com/add/example'],
        'download_supported' => false,
    ],
    'linkedin' => [
        'name' => 'LinkedIn',
        'slug' => 'linkedin-video-downloader',
        'title' => 'LinkedIn Video Link Tool',
        'h1' => 'LinkedIn Video Link Tool',
        'meta_description' => 'Paste a LinkedIn video URL to identify content and view permitted information.',
        'description' => 'Submit a LinkedIn video link for platform identification and guidance.',
        'icon' => 'linkedin',
        'keywords' => 'linkedin video, linkedin link',
        'supported_domains' => ['linkedin.com', 'www.linkedin.com'],
        'how_to' => ['Copy the LinkedIn post URL.', 'Paste it here.', 'Click Generate Links.'],
        'faq' => [],
        'url_examples' => ['https://www.linkedin.com/posts/example'],
        'download_supported' => false,
    ],
];

$hostPatterns['snapchat'] = ['/^snapchat\.com$/'];
$hostPatterns['linkedin'] = ['/^linkedin\.com$/'];

$platforms = array_merge($platforms, $extraPlatforms);

$legacyMap = [
    'youtube' => 'YouTubeProvider.php',
    'tiktok' => 'TikTokProvider.php',
    'vimeo' => 'VimeoProvider.php',
    'dailymotion' => 'DailymotionProvider.php',
    'instagram' => 'InstagramProvider.php',
    'facebook' => 'FacebookProvider.php',
    'twitter' => 'TwitterProvider.php',
    'reddit' => 'RedditProvider.php',
    'twitch' => 'TwitchProvider.php',
    'pinterest' => 'PinterestProvider.php',
    'snapchat' => 'SnapchatProvider.php',
    'linkedin' => 'LinkedInProvider.php',
];

function folderToNamespace(string $folder): string
{
    $parts = preg_split('/[-_]/', $folder) ?: [$folder];
    $parts = array_map(static fn(string $p): string => ucfirst(strtolower($p)), $parts);

    return 'App\\Provider\\' . implode('', $parts);
}

function adaptProviderSource(string $source, string $ns): string
{
    $source = preg_replace('/namespace App\\\\Providers;/', "namespace {$ns};", $source);
    $source = preg_replace('/extends AbstractProvider/', 'extends \\App\\Provider\\AbstractProvider', $source);
    $source = preg_replace('/\n\s*public function getPlatform\(\): string\s*\{[^}]+\}\s*/', "\n", $source);

    return $source;
}

function writeManifest(string $dir, string $id, array $platform, array $hostPatterns, array $allowedExtra): void
{
    $allowed = array_values(array_unique(array_merge(
        $platform['supported_domains'] ?? [],
        $allowedExtra[$id] ?? []
    )));

    $downloadsOnly = $id === 'youtube';
    $downloadSupported = $id === 'youtube' ? true : (bool) ($platform['download_supported'] ?? false);

    if ($id === 'youtube') {
        $platform['download_supported'] = true;
        $platform['meta_description'] = 'Paste a YouTube URL to download videos in multiple qualities (1080p, 720p, 480p, and more) or view metadata.';
        $platform['description'] = 'Download YouTube videos in HD and other quality options. Paste any public YouTube, youtu.be, or Shorts link.';
    }

    $lines = ["<?php\n", "declare(strict_types=1);\n\n", "return [\n"];
    $export = array_merge($platform, [
        'id' => $id,
        'host_patterns' => $hostPatterns[$id] ?? [],
        'allowed_domains' => $allowed,
        'downloads_only' => $downloadsOnly,
        'download_supported' => $downloadSupported,
    ]);

    foreach ($export as $key => $value) {
        $lines[] = '    ' . var_export($key, true) . ' => ' . var_export($value, true) . ",\n";
    }
    $lines[] = "];\n";

    file_put_contents($dir . '/manifest.php', implode('', $lines));
}

$providerRoot = $root . '/app/provider';
if (!is_dir($providerRoot)) {
    mkdir($providerRoot, 0755, true);
}

foreach ($platforms as $id => $platform) {
    $dir = $providerRoot . '/' . $id;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    writeManifest($dir, $id, $platform, $hostPatterns, $allowedExtra);

    $legacyFile = $root . '/app/Providers/' . ($legacyMap[$id] ?? '');
    if (is_file($legacyFile)) {
        $ns = folderToNamespace($id);
        $providerSource = adaptProviderSource(file_get_contents($legacyFile), $ns);
        file_put_contents($dir . '/Provider.php', $providerSource);
    }

    if ($id === 'youtube') {
        $ytSource = file_get_contents($root . '/app/YouTubeExtractor.php');
        $ytSource = preg_replace('/namespace App;/', 'namespace App\\Provider\\Youtube;', $ytSource);
        $ytSource = preg_replace(
            '/public function __construct\(HttpClient \$http, \?string \$ytdlpPath = null\)\s*\{[^}]+\}/s',
            "public function __construct(HttpClient \$http, array \$config = [])\n    {\n        \$this->http = \$http;\n        \$ytdlpPath = null;\n        if (!empty(\$config['ytdlp']['enabled']) && !empty(\$config['ytdlp']['path']) && is_file(\$config['ytdlp']['path'])) {\n            \$ytdlpPath = \$config['ytdlp']['path'];\n        }\n        \$this->ytdlpPath = \$ytdlpPath ?? \$this->detectYtDlp();\n    }",
            $ytSource
        );
        $ytSource = str_replace(
            'class YouTubeExtractor',
            "use App\\HttpClient;\nuse App\\Logger;\n\nclass Extractor",
            $ytSource
        );
        file_put_contents($dir . '/Extractor.php', $ytSource);
    } elseif (in_array($id, $mediaExtractPlatforms, true)) {
        $ns = folderToNamespace($id);
        $extractor = <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use App\HttpClient;
use App\MediaExtractor;

class Extractor
{
    private MediaExtractor \$media;

    public function __construct(HttpClient \$http, array \$config = [])
    {
        \$this->media = new MediaExtractor(\$http);
    }

    public function extract(string \$url): array
    {
        return \$this->media->extract('{$id}', \$url);
    }
}

PHP;
        file_put_contents($dir . '/Extractor.php', $extractor);
    }

    echo "Created provider: {$id}\n";
}

echo "Done.\n";
