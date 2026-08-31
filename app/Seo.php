<?php

declare(strict_types=1);

namespace App;

use App\Repositories\PageSeoRepository;

class Seo
{
    private array $config;
    private array $videoPlatforms;
    private array $audioPlatforms;
    private ?PageSeoRepository $pageSeoRepo;

    /** @var array<string, array<string, string>>|null */
    private ?array $pageSeoCache = null;

    public function __construct(
        array $config,
        array $videoPlatforms,
        array $audioPlatforms = [],
        ?PageSeoRepository $pageSeoRepo = null
    ) {
        $this->config = $config;
        $this->videoPlatforms = $videoPlatforms;
        $this->audioPlatforms = $audioPlatforms;
        $this->pageSeoRepo = $pageSeoRepo;
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

    /** @param array<string, mixed> $defaults */
    public function resolvePageMeta(string $pageKey, array $defaults = []): array
    {
        $dbRow = $this->pageSeoRepo?->get($pageKey);
        $merged = $defaults;

        if ($dbRow !== null) {
            foreach ($dbRow as $field => $value) {
                if ($value !== '' && !in_array($field, ['page_key', 'page_label', 'page_type'], true)) {
                    $merged[$field] = $value;
                }
            }
        }

        return $this->normalizeMeta($merged, $pageKey);
    }

    public function homepageMeta(): array
    {
        $defaults = [
            'title' => 'YouTube Video Downloader – Free MP4 & MP3 Online',
            'description' => PlatformConfig::pasteUrlDescription(array_merge($this->videoPlatforms, $this->audioPlatforms)),
            'meta_description' => PlatformConfig::pasteUrlDescription(array_merge($this->videoPlatforms, $this->audioPlatforms)),
            'keywords' => PlatformConfig::keywordsFromPlatforms(array_merge($this->videoPlatforms, $this->audioPlatforms)),
            'h1' => 'Free YouTube Video Downloader & MP3 Converter',
            'platform_list' => PlatformConfig::formatNameList(array_merge($this->videoPlatforms, $this->audioPlatforms)),
        ];

        return $this->resolvePageMeta('home', $defaults);
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

        $defaults = [
            'title' => $name . ' – Free Online ' . ($isAudio ? 'Audio' : 'Video') . ' Tool',
            'description' => $description,
            'meta_description' => $description,
            'h1' => $name,
            'page_slug' => $pageSlug,
        ];

        return $this->resolvePageMeta($pageSlug, $defaults);
    }

    public function platformMeta(string $slug): ?array
    {
        return ServiceConfig::findPlatformBySlug($slug, $this->videoPlatforms, $this->audioPlatforms);
    }

    /** @param array<string, mixed> $meta */
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

    public function jsonLdWebSite(string $siteName): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $this->canonical(''),
            'description' => 'Free online YouTube video downloader and YouTube to MP3 converter.',
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

    /** @return list<array<string, string>> */
    public function listManagedPages(): array
    {
        if ($this->pageSeoRepo === null) {
            return PageSeoDefaults::all();
        }

        if ($this->pageSeoCache === null) {
            $this->pageSeoCache = $this->pageSeoRepo->loadAllKeyed();
        }

        return array_values($this->pageSeoCache);
    }

    /** @param array<string, mixed> $meta */
    private function normalizeMeta(array $meta, string $pageKey): array
    {
        $metaDescription = trim((string) ($meta['meta_description'] ?? ''));
        $description = trim((string) ($meta['description'] ?? ''));

        if ($metaDescription === '' && $description !== '') {
            $metaDescription = $description;
        }
        if ($description === '' && $metaDescription !== '') {
            $description = $metaDescription;
        }

        return [
            'page_key' => $pageKey,
            'title' => trim((string) ($meta['title'] ?? '')),
            'h1' => trim((string) ($meta['h1'] ?? $meta['title'] ?? '')),
            'meta_description' => $metaDescription,
            'description' => $description,
            'keywords' => trim((string) ($meta['keywords'] ?? '')),
            'og_image' => trim((string) ($meta['og_image'] ?? '')),
            'robots' => trim((string) ($meta['robots'] ?? 'index, follow')) ?: 'index, follow',
            'seo_content' => trim((string) ($meta['seo_content'] ?? '')),
            'page_slug' => trim((string) ($meta['page_slug'] ?? $pageKey)),
            'platform_list' => (string) ($meta['platform_list'] ?? ''),
        ];
    }
}
