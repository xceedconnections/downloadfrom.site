<?php

declare(strict_types=1);

namespace App;

use App\Contracts\StorageInterface;
use App\Repositories\AdsRepository;
use App\Storage\DatabaseConnection;

/**
 * Ad storage, placement logic, and HTML rendering.
 */
class AdManager
{
    private StorageInterface $db;
    private AdsRepository $repo;
    private array $data;
    private string $baseUrl;

    public const PLACEMENTS = [
        'header_banner' => 'Header banner (all pages)',
        'footer_banner' => 'Footer banner (all pages)',
        'home_hero_sidebar' => 'Homepage – hero right sidebar',
        'home_after_form' => 'Homepage – below URL form',
        'home_middle' => 'Homepage – middle content',
        'home_bottom' => 'Homepage – bottom content',
        'platform_hero_sidebar' => 'Platform page – hero right sidebar',
        'platform_top' => 'Platform page – below form',
        'platform_bottom' => 'Platform page – bottom',
        'result_top' => 'Result page – top',
        'result_sidebar' => 'Result page – right sidebar',
        'result_bottom' => 'Result page – bottom',
        'download_modal' => 'Download click modal',
        'popup' => 'Popup overlay (timed)',
    ];

    public const PAGE_TYPES = [
        'all' => 'All pages',
        'home' => 'Homepage only',
        'result' => 'Result pages (video & audio)',
        'platform' => 'Platform pages (video & audio)',
        'video_result' => 'Video result pages only',
        'audio_result' => 'Audio result pages only',
        'video_platform' => 'Video provider pages only',
        'audio_platform' => 'Audio provider pages only',
    ];

    public const AD_TYPES = [
        'banner' => 'Image / Banner',
        'text' => 'Text (rich text)',
        'html' => 'HTML / Script (AdSense, etc.)',
        'video' => 'Video',
        'popup' => 'Popup content',
    ];

    public const NETWORKS = [
        'custom' => 'Custom HTML / JavaScript',
        'adsense' => 'Google AdSense',
        'adsense_auto' => 'Google AdSense (Auto ads)',
        'gam' => 'Google Ad Manager',
        'medianet' => 'Media.net',
        'propeller' => 'PropellerAds',
        'adsterra' => 'Adsterra',
        'ezoic' => 'Ezoic',
        'amazon' => 'Amazon Associates',
        'taboola' => 'Taboola',
        'outbrain' => 'Outbrain',
    ];

    public function __construct(StorageInterface $db, string $baseUrl)
    {
        $this->db = $db;
        $this->repo = new AdsRepository(DatabaseConnection::get());
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->reloadData();
    }

    private function reloadData(): void
    {
        $this->data = $this->repo->loadDocument($this->defaults());
    }

    public function isEnabled(): bool
    {
        return !empty($this->data['enabled']);
    }

    /** @return array<int, array> */
    public function allAds(): array
    {
        return $this->data['ads'] ?? [];
    }

    public function getAd(string $id): ?array
    {
        foreach ($this->allAds() as $ad) {
            if (($ad['id'] ?? '') === $id) {
                return $ad;
            }
        }
        return null;
    }

    public function save(array $data): bool
    {
        $this->data = array_replace_recursive($this->defaults(), $data);
        $this->data['updated'] = time();
        $saved = $this->repo->saveDocument($this->data, $this->defaults());
        if ($saved) {
            $this->reloadData();
        }
        return $saved;
    }

    /** @return array<string, mixed> */
    public function getNetworkSettings(): array
    {
        return $this->data['network_settings'] ?? self::defaultNetworkSettings();
    }

    /** @param array<string, mixed> $settings */
    public function saveNetworkSettings(array $settings): bool
    {
        $this->data['network_settings'] = array_replace_recursive(
            self::defaultNetworkSettings(),
            $settings
        );
        return $this->save($this->data);
    }

    public function saveAd(array $ad): bool
    {
        $ad['updated'] = time();
        if (!$this->repo->upsertAd($ad)) {
            return false;
        }
        $this->reloadData();
        return true;
    }

    public function deleteAd(string $id): bool
    {
        if (!$this->repo->deleteAd($id)) {
            return false;
        }
        $this->reloadData();
        return true;
    }

    /** @return array<string, string> placement key => ad id */
    public function getPlacementMap(): array
    {
        $map = $this->data['placement_map'] ?? [];
        return is_array($map) ? $map : [];
    }

    /** @param array<string, string> $map */
    public function savePlacementMap(array $map): bool
    {
        $clean = [];
        foreach (array_keys(self::PLACEMENTS) as $placement) {
            $adId = trim((string) ($map[$placement] ?? ''));
            if ($adId !== '') {
                $clean[$placement] = $adId;
            }
        }
        $this->data['placement_map'] = $clean;
        if (!$this->repo->savePlacementMap($clean)) {
            return false;
        }
        $this->reloadData();
        return true;
    }

    public function saveGlobalSettings(bool $enabled, int $downloadModalCountdown): bool
    {
        $this->data['enabled'] = $enabled;
        $this->data['download_modal_countdown'] = max(0, min(30, $downloadModalCountdown));
        return $this->save($this->data);
    }

    /** @return array<int, array> */
    public function getForPlacement(string $placement, string $pageType = 'all', ?string $serviceId = null): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        foreach (self::placementAliases($placement) as $alias) {
            $adId = trim((string) ($this->getPlacementMap()[$alias] ?? ''));
            if ($adId !== '') {
                $ad = $this->getAd($adId);
                if ($ad !== null && !empty($ad['enabled'])) {
                    $pages = $ad['pages'] ?? ['all'];
                    if ($this->pageMatches($pages, $pageType, $serviceId)) {
                        return [$ad];
                    }
                }
            }
        }

        $matches = [];
        foreach ($this->allAds() as $ad) {
            if (empty($ad['enabled'])) {
                continue;
            }
            $placements = $ad['placements'] ?? [];
            $matched = false;
            foreach (self::placementAliases($placement) as $alias) {
                if (in_array($alias, $placements, true)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }
            $pages = $ad['pages'] ?? ['all'];
            if (!$this->pageMatches($pages, $pageType, $serviceId)) {
                continue;
            }
            $matches[] = $ad;
        }

        usort($matches, static fn(array $a, array $b): int => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

        return $matches;
    }

    /** @param string[] $adPages */
    private function pageMatches(array $adPages, string $pageType, ?string $serviceId): bool
    {
        if (in_array('all', $adPages, true)) {
            return true;
        }

        foreach ($this->pageTypeCandidates($pageType, $serviceId) as $candidate) {
            if (in_array($candidate, $adPages, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    private function pageTypeCandidates(string $pageType, ?string $serviceId): array
    {
        $candidates = [$pageType];

        if ($pageType === 'result') {
            $candidates[] = 'video_result';
            $candidates[] = 'audio_result';
        }

        if ($pageType === 'platform') {
            $candidates[] = 'video_platform';
            $candidates[] = 'audio_platform';
        }

        if ($serviceId === ServiceConfig::SERVICE_AUDIO) {
            if ($pageType === 'result') {
                $candidates[] = 'audio_result';
            }
            if ($pageType === 'platform') {
                $candidates[] = 'audio_platform';
            }
        }

        if ($serviceId === ServiceConfig::SERVICE_VIDEO) {
            if ($pageType === 'result') {
                $candidates[] = 'video_result';
            }
            if ($pageType === 'platform') {
                $candidates[] = 'video_platform';
            }
        }

        return array_values(array_unique($candidates));
    }

    public function hasPlacement(string $placement, string $pageType = 'all', ?string $serviceId = null): bool
    {
        return $this->getForPlacement($placement, $pageType, $serviceId) !== [];
    }

    public function renderZone(string $placement, string $pageType = 'all', ?string $serviceId = null): string
    {
        $ads = $this->getForPlacement($placement, $pageType, $serviceId);
        if ($ads === []) {
            return '';
        }

        $html = '<div class="ad-zone ad-zone-' . Security::escape($placement) . '">';
        foreach ($ads as $ad) {
            $html .= '<span class="ad-label">Advertisement</span>';
            $html .= $this->renderAd($ad, $placement);
        }
        $html .= '</div>';

        return $html;
    }

    /** @return array<int, array> */
    public function getDownloadModalAds(?string $serviceId = null): array
    {
        return $this->getForPlacement('download_modal', 'result', $serviceId);
    }

    /** @return array<int, array> */
    public function getPopupAds(?string $serviceId = null): array
    {
        return $this->getForPlacement('popup', 'all', $serviceId);
    }

    public function renderAd(array $ad, string $placement = ''): string
    {
        $type = $ad['type'] ?? 'banner';
        $id = Security::escape($ad['id'] ?? uniqid('ad', true));
        $content = $ad['content'] ?? [];

        $wrapStart = '<div class="ad-unit ad-type-' . Security::escape($type) . '" data-ad-id="' . $id . '">';
        $wrapEnd = '</div>';

        return match ($type) {
            'banner' => $wrapStart . $this->renderBanner($content) . $wrapEnd,
            'text' => $wrapStart . $this->renderText($content) . $wrapEnd,
            'html' => $wrapStart . $this->renderHtml($content) . $wrapEnd,
            'video' => $wrapStart . $this->renderVideo($content) . $wrapEnd,
            'network' => $wrapStart . $this->renderHtml($content) . $wrapEnd,
            'popup' => $this->renderPopupUnit($ad, $content),
            default => '',
        };
    }

    private function resolveMediaUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/');
    }

    private function renderBanner(array $content): string
    {
        $img = $this->resolveMediaUrl((string) ($content['image_url'] ?? ''));
        if ($img === '') {
            return '';
        }
        $link = trim((string) ($content['link_url'] ?? ''));
        $alt = Security::escape($content['alt'] ?? 'Advertisement');
        $imgTag = '<img src="' . Security::escape($img) . '" alt="' . $alt . '" class="ad-banner-img" loading="lazy">';

        if ($link !== '') {
            return '<a href="' . Security::escape($link) . '" class="ad-link" target="_blank" rel="noopener noreferrer sponsored">' . $imgTag . '</a>';
        }

        return $imgTag;
    }

    private function renderText(array $content): string
    {
        $html = trim((string) ($content['html'] ?? ''));
        if ($html !== '') {
            return '<div class="ad-text-html">' . $html . '</div>';
        }

        $text = trim((string) ($content['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        $link = trim((string) ($content['link_url'] ?? ''));
        $title = Security::escape($content['title'] ?? '');
        $body = Security::escape($text);

        $inner = ($title !== '' ? '<strong class="ad-text-title">' . $title . '</strong>' : '') . '<p class="ad-text-body">' . $body . '</p>';

        if ($link !== '') {
            return '<a href="' . Security::escape($link) . '" class="ad-text-link" target="_blank" rel="noopener noreferrer sponsored">' . $inner . '</a>';
        }

        return '<div class="ad-text">' . $inner . '</div>';
    }

    private function renderHtml(array $content): string
    {
        $code = trim((string) ($content['html'] ?? ''));
        if ($code === '') {
            $code = trim((string) ($content['network_code'] ?? ''));
        }
        if ($code === '') {
            return '';
        }

        return '<div class="ad-html">' . $code . '</div>';
    }

    private function renderVideo(array $content): string
    {
        $url = trim((string) ($content['video_url'] ?? ''));
        if ($url === '') {
            return '';
        }

        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]+)#', $url, $m)) {
            return '<div class="ad-video-embed"><iframe src="https://www.youtube.com/embed/' . Security::escape($m[1]) . '" title="Advertisement" loading="lazy" allowfullscreen></iframe></div>';
        }

        $src = $this->resolveMediaUrl($url);

        return '<video class="ad-video" controls preload="none" src="' . Security::escape($src) . '"></video>';
    }

    private function renderNetwork(array $ad, array $content): string
    {
        $network = $ad['network'] ?? 'custom';
        $content = $this->mergeNetworkCredentials($network, $content);

        $code = trim((string) ($content['network_code'] ?? ''));
        if ($code === '') {
            $code = $this->networkTemplate($network, $content);
        }

        if ($code === '') {
            return '<div class="ad-network-placeholder">Ad slot – configure in Ad Management.</div>';
        }

        return '<div class="ad-network" data-network="' . Security::escape($network) . '">' . $code . '</div>';
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function mergeNetworkCredentials(string $network, array $content): array
    {
        $saved = $this->getNetworkSettings()[$network] ?? [];
        if (!is_array($saved)) {
            return $content;
        }

        foreach (['client_id', 'slot_id', 'network_code', 'width', 'height'] as $key) {
            if (empty($content[$key]) && !empty($saved[$key])) {
                $content[$key] = $saved[$key];
            }
        }

        return $content;
    }

    private function renderPopupUnit(array $ad, array $content): string
    {
        // Popup units are rendered via JS from footer script block
        return '';
    }

    /** @param array<string, mixed> $content */
    private function networkTemplate(string $network, array $content): string
    {
        $slot = Security::escape($content['slot_id'] ?? '');
        $client = Security::escape($content['client_id'] ?? '');
        $width = (int) ($content['width'] ?? 728);
        $height = (int) ($content['height'] ?? 90);

        return match ($network) {
            'adsense' => $client && $slot
                ? '<ins class="adsbygoogle" style="display:inline-block;width:' . $width . 'px;height:' . $height . 'px" data-ad-client="' . $client . '" data-ad-slot="' . $slot . '"></ins><script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>'
                : '',
            'adsense_auto' => $client
                ? '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . $client . '" crossorigin="anonymous"></script>'
                : '',
            'medianet' => $slot
                ? '<div id="' . $slot . '"></div><script>window._mNHandle=window._mNHandle||{};window._mNHandle.queue=window._mNHandle.queue||[];</script>'
                : '',
            default => '',
        };
    }

    /** @return array<string, mixed> */
    public function getConfig(?string $pageType = 'all', ?string $serviceId = null): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'download_modal' => [
                'enabled' => $this->getDownloadModalAds($serviceId) !== [],
                'countdown' => (int) ($this->data['download_modal_countdown'] ?? 5),
            ],
            'popup' => $this->buildPopupConfig($serviceId),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function buildPopupConfig(?string $serviceId = null): array
    {
        $items = [];
        foreach ($this->getPopupAds($serviceId) as $ad) {
            $popup = $ad['popup'] ?? [];
            $items[] = [
                'id' => $ad['id'],
                'delay' => (int) ($popup['delay_seconds'] ?? 3),
                'once' => false,
                'closable' => !isset($popup['closable']) || $popup['closable'],
                'html' => $this->renderAdInner($ad),
            ];
        }
        return $items;
    }

    private function renderAdInner(array $ad): string
    {
        $type = $ad['type'] ?? 'banner';
        $content = $ad['content'] ?? [];

        return match ($type) {
            'banner' => $this->renderBanner($content),
            'text' => $this->renderText($content),
            'html' => $this->renderHtml($content),
            'video' => $this->renderVideo($content),
            'network' => $this->renderHtml($content),
            'popup' => $this->renderHtml($content) ?: $this->renderText($content) ?: $this->renderBanner($content),
            default => '',
        };
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return $this->data;
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'enabled' => false,
            'download_modal_countdown' => 5,
            'updated' => time(),
            'placement_map' => [],
            'ads' => [],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function defaultNetworkSettings(): array
    {
        $empty = ['client_id' => '', 'slot_id' => '', 'network_code' => '', 'width' => 728, 'height' => 90];
        return [
            'adsense' => array_merge($empty, ['width' => 728, 'height' => 90]),
            'adsense_auto' => ['client_id' => '', 'slot_id' => '', 'network_code' => '', 'width' => 0, 'height' => 0],
            'gam' => $empty,
            'medianet' => $empty,
            'propeller' => $empty,
            'adsterra' => $empty,
            'ezoic' => $empty,
            'amazon' => $empty,
            'taboola' => $empty,
            'outbrain' => $empty,
            'custom' => $empty,
        ];
    }

    public static function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /** @return array<string, string> */
    public static function networkHelp(string $network): array
    {
        return match ($network) {
            'adsense' => [
                'title' => 'Google AdSense',
                'help' => 'Enter your AdSense Publisher ID (ca-pub-XXXXXXXX) as Client ID and your ad unit Slot ID.',
            ],
            'adsense_auto' => [
                'title' => 'Google AdSense Auto Ads',
                'help' => 'Enter only your Publisher ID. Auto ads script will be injected.',
            ],
            'gam' => [
                'title' => 'Google Ad Manager',
                'help' => 'Paste your GPT / GAM ad unit JavaScript tag in the Network Code field.',
            ],
            'medianet' => [
                'title' => 'Media.net',
                'help' => 'Paste your Media.net div ID or full tag in Network Code.',
            ],
            'propeller' => [
                'title' => 'PropellerAds',
                'help' => 'Paste your PropellerAds zone tag in Network Code.',
            ],
            'adsterra' => [
                'title' => 'Adsterra',
                'help' => 'Paste your Adsterra banner or popunder code in Network Code.',
            ],
            'ezoic' => [
                'title' => 'Ezoic',
                'help' => 'Paste Ezoic placeholder or script code in Network Code.',
            ],
            'amazon' => [
                'title' => 'Amazon Associates',
                'help' => 'Paste your Amazon ad creative HTML in Network Code.',
            ],
            'taboola' => [
                'title' => 'Taboola',
                'help' => 'Paste Taboola widget code in Network Code.',
            ],
            'outbrain' => [
                'title' => 'Outbrain',
                'help' => 'Paste Outbrain widget code in Network Code.',
            ],
            default => [
                'title' => 'Custom',
                'help' => 'Paste any HTML, iframe, or script from your ad provider.',
            ],
        };
    }

    /** @return array<string, array{title: string, description: string, zones: array<int, array{key: string, label: string, hint: string}>}> */
    public static function placementMapPages(): array
    {
        return [
            'global' => [
                'title' => 'All pages (global)',
                'description' => 'Header and footer ads appear on every public page including home, platform, result, blog, and legal pages.',
                'zones' => [
                    ['key' => 'header_banner', 'label' => 'Ad 1', 'hint' => 'Below site header, above main content'],
                    ['key' => 'footer_banner', 'label' => 'Ad 2', 'hint' => 'Above site footer on every page'],
                    ['key' => 'popup', 'label' => 'Popup', 'hint' => 'Timed overlay on page load (not a fixed zone)'],
                ],
            ],
            'home' => [
                'title' => 'Homepage',
                'description' => 'Main landing page with the URL form and platform lists.',
                'zones' => [
                    ['key' => 'header_banner', 'label' => 'Ad 1', 'hint' => 'Header banner'],
                    ['key' => 'home_hero_sidebar', 'label' => 'Ad 2', 'hint' => 'Right side of blue hero — next to title & URL form'],
                    ['key' => 'home_after_form', 'label' => 'Ad 3', 'hint' => 'Below Generate Links button'],
                    ['key' => 'home_middle', 'label' => 'Ad 4', 'hint' => 'Between How It Works and Supported Platforms'],
                    ['key' => 'home_bottom', 'label' => 'Ad 5', 'hint' => 'After FAQ section'],
                    ['key' => 'footer_banner', 'label' => 'Ad 6', 'hint' => 'Footer banner'],
                ],
            ],
            'platform' => [
                'title' => 'Platform page (e.g. YouTube, TikTok MP3)',
                'description' => 'Individual provider landing pages with URL form and FAQ.',
                'zones' => [
                    ['key' => 'header_banner', 'label' => 'Ad 1', 'hint' => 'Header banner'],
                    ['key' => 'platform_hero_sidebar', 'label' => 'Ad 2', 'hint' => 'Right side of hero next to form'],
                    ['key' => 'platform_top', 'label' => 'Ad 3', 'hint' => 'Below URL form in hero'],
                    ['key' => 'platform_bottom', 'label' => 'Ad 4', 'hint' => 'Before Other Platforms section'],
                    ['key' => 'footer_banner', 'label' => 'Ad 5', 'hint' => 'Footer banner'],
                ],
            ],
            'result' => [
                'title' => 'Result / download page',
                'description' => 'Shown after Generate Links — video and/or audio download options.',
                'zones' => [
                    ['key' => 'header_banner', 'label' => 'Ad 1', 'hint' => 'Header banner'],
                    ['key' => 'result_top', 'label' => 'Ad 2', 'hint' => 'Top of result content'],
                    ['key' => 'result_sidebar', 'label' => 'Ad 3', 'hint' => 'Right sidebar beside thumbnail & links'],
                    ['key' => 'result_bottom', 'label' => 'Ad 4', 'hint' => 'Below download columns'],
                    ['key' => 'download_modal', 'label' => 'Modal', 'hint' => 'When user clicks Download button'],
                    ['key' => 'footer_banner', 'label' => 'Ad 5', 'hint' => 'Footer banner'],
                ],
            ],
        ];
    }

    /** Legacy placement aliases. @return string[] */
    public static function placementAliases(string $placement): array
    {
        return match ($placement) {
            'home_after_form' => ['home_after_form', 'home_top'],
            'home_top' => ['home_after_form', 'home_top'],
            default => [$placement],
        };
    }
}
