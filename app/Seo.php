<?php

declare(strict_types=1);

namespace App;

class Seo
{
    private array $config;
    private array $videoPlatforms;
    private array $audioPlatforms;

    public function __construct(array $config, array $videoPlatforms, array $audioPlatforms = [])
    {
        $this->config = $config;
        $this->videoPlatforms = $videoPlatforms;
        $this->audioPlatforms = $audioPlatforms;
        $this->platforms = $videoPlatforms;
    }

    public function baseUrl(): string
    {
        return rtrim($this->config['app']['url'], '/');
    }

    public function canonical(string $path = ''): string
    {
        return $this->baseUrl() . '/' . ltrim($path, '/');
    }

    public function homepageMeta(): array
    {
        return [
            'title' => 'Online Video URL Tool – Get Video Links From Supported Platforms',
            'description' => PlatformConfig::pasteUrlDescription(array_merge($this->videoPlatforms, $this->audioPlatforms)),
            'keywords' => PlatformConfig::keywordsFromPlatforms(array_merge($this->videoPlatforms, $this->audioPlatforms)),
            'h1' => 'Download / Get Video Links From Supported Platforms',
            'platform_list' => PlatformConfig::formatNameList(array_merge($this->videoPlatforms, $this->audioPlatforms)),
        ];
    }

    public function serviceMeta(string $serviceId, array $serviceNav): array
    {
        $name = (string) ($serviceNav['name'] ?? 'Converter');
        $platformNames = PlatformConfig::formatNameList($serviceNav['platforms'] ?? []);
        $pageSlug = ServiceConfig::servicePageSlug($serviceId);
        $isAudio = $serviceId === ServiceConfig::SERVICE_AUDIO;
        $description = $isAudio
            ? 'Convert and download audio from ' . $platformNames . '. Paste a link and generate MP3 download options instantly.'
            : 'Convert and download videos from ' . $platformNames . '. Paste a link and generate download options in multiple qualities.';

        return [
            'title' => $name . ' – Free Online ' . ($isAudio ? 'Audio' : 'Video') . ' Tool',
            'description' => $description,
            'h1' => $name,
            'page_slug' => $pageSlug,
            'meta_description' => $description,
        ];
    }

    public function platformMeta(string $slug): ?array
    {
        return ServiceConfig::findPlatformBySlug($slug, $this->videoPlatforms, $this->audioPlatforms);
    }
    public function jsonLdWebPage(array $meta, string $path): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $meta['title'] ?? $meta['h1'],
            'description' => $meta['meta_description'] ?? $meta['description'] ?? '',
            'url' => $this->canonical($path),
        ];
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function jsonLdFaq(array $faqItems): string
    {
        $entities = [];
        foreach ($faqItems as $item) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => Security::stripHtmlToPlaintext((string) ($item['a'] ?? '')),
                ],
            ];
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function jsonLdBreadcrumb(array $items): string
    {
        $list = [];
        foreach ($items as $i => $item) {
            $list[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function sitemapUrls(): array
    {
        $urls = [
            ['loc' => $this->canonical(''), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => $this->canonical(ServiceConfig::PAGE_FAQ), 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => $this->canonical(ServiceConfig::PAGE_VIDEO), 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => $this->canonical(ServiceConfig::PAGE_AUDIO), 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => $this->canonical('privacy'), 'priority' => '0.3', 'changefreq' => 'monthly'],
            ['loc' => $this->canonical('terms'), 'priority' => '0.3', 'changefreq' => 'monthly'],
            ['loc' => $this->canonical('dmca'), 'priority' => '0.3', 'changefreq' => 'monthly'],
            ['loc' => $this->canonical('contact'), 'priority' => '0.4', 'changefreq' => 'monthly'],
        ];

        foreach ($this->videoPlatforms as $platform) {
            $urls[] = [
                'loc' => $this->canonical($platform['slug']),
                'priority' => '0.8',
                'changefreq' => 'weekly',
            ];
        }

        foreach ($this->audioPlatforms as $platform) {
            $urls[] = [
                'loc' => $this->canonical($platform['slug']),
                'priority' => '0.75',
                'changefreq' => 'weekly',
            ];
        }

        $blogPosts = [
            'blog/how-to-save-tiktok-videos',
            'blog/how-to-save-youtube-videos',
            'blog/how-to-save-vimeo-videos',
        ];
        foreach ($blogPosts as $post) {
            $urls[] = [
                'loc' => $this->canonical($post),
                'priority' => '0.6',
                'changefreq' => 'monthly',
            ];
        }

        return $urls;
    }
}
