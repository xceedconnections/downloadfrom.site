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
    private bool $relayScripts = true;

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

    /**
     * Recommended creative sizes per zone (desktop-first; sidebars hidden below 992px).
     *
     * @var array<string, array{width: int, height: int, note: string}>
     */
    public const PLACEMENT_SIZES = [
        'header_banner' => [
            'width' => 728,
            'height' => 90,
            'note' => 'Full-width banner (max ~1168px). Best: 728×90 or 970×90.',
        ],
        'footer_banner' => [
            'width' => 728,
            'height' => 90,
            'note' => 'Full-width banner (max ~1168px). Best: 728×90 or 970×90.',
        ],
        'home_hero_sidebar' => [
            'width' => 300,
            'height' => 250,
            'note' => 'Desktop sidebar only (260–320px wide, min box 280px tall). Hidden on mobile.',
        ],
        'home_after_form' => [
            'width' => 728,
            'height' => 90,
            'note' => 'Below URL form (~760px max wide). Best: 728×90 or responsive width.',
        ],
        'home_middle' => [
            'width' => 728,
            'height' => 90,
            'note' => 'Content area full width (max ~1168px). Best: 728×90 or 970×250.',
        ],
        'home_bottom' => [
            'width' => 728,
            'height' => 90,
            'note' => 'Content area full width (max ~1168px). Best: 728×90 or 970×250.',
        ],
        'platform_hero_sidebar' => [
            'width' => 300,
            'height' => 250,
            'note' => 'Desktop sidebar only (260–320px wide, min box 280px tall). Hidden on mobile.',
        ],
        'platform_top' => [
            'width' => 728,
            'height' => 90,
            'note' => 'Below form on provider page (~760px max wide). Best: 728×90.',
        ],
        'platform_bottom' => [
            'width' => 728,
            'height' => 90,
            'note' => 'Content area full width (max ~1168px). Best: 728×90 or 970×250.',
        ],
        'result_top' => [
            'width' => 728,
            'height' => 90,
            'note' => 'Above result content (max ~1168px). Best: 728×90.',
        ],
        'result_sidebar' => [
            'width' => 300,
            'height' => 250,
            'note' => 'Desktop right column (300px wide, sticky). Hidden on mobile.',
        ],
        'result_bottom' => [
            'width' => 728,
            'height' => 90,
            'note' => 'Below download links (max ~1168px). Best: 728×90 or 970×250.',
        ],
        'download_modal' => [
            'width' => 250,
            'height' => 250,
            'note' => 'Download modal side slot (~248px wide on desktop). Stacks full-width on mobile.',
        ],
        'popup' => [
            'width' => 600,
            'height' => 400,
            'note' => 'Overlay popup (max ~600px text / ~820px iframe). Best: 600×400 or 820×460.',
        ],
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

    public function setRelayScripts(bool $enabled): void
    {
        $this->relayScripts = $enabled;
    }

    public function relayScriptsEnabled(): bool
    {
        return $this->relayScripts;
    }

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

    /** @return array<string, array<int, string>> placement key => ad ids */
    public function getPlacementMap(): array
    {
        $map = $this->data['placement_map'] ?? [];
        if (!is_array($map)) {
            return [];
        }

        $normalized = [];
        foreach ($map as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            if (is_array($value)) {
                $ids = array_values(array_filter(array_map(static fn($id): string => trim((string) $id), $value)));
            } else {
                $single = trim((string) $value);
                $ids = $single !== '' ? [$single] : [];
            }
            if ($ids !== []) {
                $normalized[$key] = $ids;
            }
        }

        return $normalized;
    }

    /** @param array<string, array<int, string>|string> $map */
    public function savePlacementMap(array $map): bool
    {
        $clean = [];
        foreach ($map as $key => $adIds) {
            $key = trim((string) $key);
            if ($key === '' || !self::isValidPlacementMapKey($key)) {
                continue;
            }
            $ids = is_array($adIds) ? $adIds : [trim((string) $adIds)];
            $normalized = [];
            foreach ($ids as $adId) {
                $adId = trim((string) $adId);
                if ($adId !== '' && !in_array($adId, $normalized, true)) {
                    $normalized[] = $adId;
                }
            }
            if ($normalized !== []) {
                $clean[$key] = $normalized;
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
    public function getForPlacement(string $placement, string $pageType = 'all', ?string $serviceId = null, ?string $providerId = null): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        foreach (self::placementMapLookupKeys($placement, $serviceId, $providerId) as $mapKey) {
            $adIds = $this->getPlacementMap()[$mapKey] ?? [];
            if ($adIds === []) {
                continue;
            }
            $ads = $this->resolveEnabledAds($adIds);
            if ($ads === []) {
                continue;
            }

            if ($placement === 'popup') {
                return $ads;
            }

            return $ads;
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

        if ($matches === []) {
            return [];
        }

        if ($placement === 'popup') {
            return $matches;
        }

        return $matches;
    }

    /** @return array<int, array> */
    public function getAllAdsForPlacement(string $placement, string $pageType = 'all', ?string $serviceId = null, ?string $providerId = null): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        foreach (self::placementMapLookupKeys($placement, $serviceId, $providerId) as $mapKey) {
            $adIds = $this->getPlacementMap()[$mapKey] ?? [];
            if ($adIds === []) {
                continue;
            }
            $ads = $this->resolveEnabledAds($adIds);
            if ($ads !== []) {
                return $ads;
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

    /** @return array<int, string> */
    public static function placementsForPage(string $pageType): array
    {
        return match ($pageType) {
            'home' => ['header_banner', 'home_hero_sidebar', 'home_after_form', 'home_middle', 'home_bottom', 'footer_banner'],
            'platform', 'video_platform', 'audio_platform' => ['header_banner', 'platform_hero_sidebar', 'platform_top', 'platform_bottom', 'footer_banner'],
            'result', 'video_result', 'audio_result' => ['header_banner', 'result_top', 'result_sidebar', 'result_bottom', 'footer_banner'],
            default => ['header_banner', 'footer_banner'],
        };
    }

    public static function placementDomKey(string $placement): string
    {
        return match ($placement) {
            'header_banner' => 'hdr',
            'footer_banner' => 'ftr',
            'home_hero_sidebar' => 'hhs',
            'home_after_form' => 'haf',
            'home_middle' => 'hm',
            'home_bottom' => 'hb',
            'platform_hero_sidebar' => 'phs',
            'platform_top' => 'pt',
            'platform_bottom' => 'pb',
            'result_top' => 'rt',
            'result_sidebar' => 'rs',
            'result_bottom' => 'rb',
            'download_modal' => 'dm',
            default => substr(md5($placement), 0, 6),
        };
    }

    /** @return array<string, mixed> */
    public function buildOwnedConfig(?string $pageType = 'all', ?string $serviceId = null, ?string $providerId = null): array
    {
        $slots = [];
        foreach (self::placementsForPage((string) $pageType) as $placement) {
            $html = $this->renderOwnedSlotContent($placement, (string) $pageType, $serviceId, $providerId);
            if ($html !== '') {
                $slots[self::placementDomKey($placement)] = $html;
            }
        }

        return [
            'slots' => $slots,
            'mounts' => [
                'hhs' => '.hero-side-slot',
                'phs' => '.hero-side-slot',
                'rs' => '.result-side-slot',
            ],
        ];
    }

    /** @param array<int, string> $adIds @return array<int, array> */
    private function resolveEnabledAds(array $adIds): array
    {
        $ads = [];
        foreach ($adIds as $adId) {
            $ad = $this->getAd($adId);
            if ($ad !== null && !empty($ad['enabled'])) {
                $ads[] = $ad;
            }
        }

        return $ads;
    }

    /** @param array<int, array> $ads */
    private function pickRotatedAd(array $ads, string $rotationKey): array
    {
        if ($ads === []) {
            return [];
        }
        if (count($ads) === 1) {
            return $ads[0];
        }

        $weights = [];
        $total = 0;
        foreach ($ads as $index => $ad) {
            $weight = max(1, (int) ($ad['priority'] ?? 0) + 1);
            $weights[$index] = $weight;
            $total += $weight;
        }

        $pick = random_int(0, max(0, $total - 1));
        $running = 0;
        foreach ($weights as $index => $weight) {
            $running += $weight;
            if ($pick < $running) {
                return $ads[$index];
            }
        }

        return $ads[array_key_last($ads)];
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

    public function hasPlacement(string $placement, string $pageType = 'all', ?string $serviceId = null, ?string $providerId = null): bool
    {
        return $this->getForPlacement($placement, $pageType, $serviceId, $providerId) !== [];
    }

    public function promoBadgeUrl(): string
    {
        $file = dirname(__DIR__) . '/assets/img/advertisement-label.png';
        $url = rtrim($this->baseUrl, '/') . '/assets/img/advertisement-label.png';
        if (is_file($file)) {
            $url .= '?v=' . (string) filemtime($file);
        }

        return $url;
    }

    public function renderPromoBadge(): string
    {
        $file = dirname(__DIR__) . '/assets/img/advertisement-label.png';
        $width = 177;
        $height = 64;
        if (is_file($file)) {
            $size = getimagesize($file);
            if (is_array($size)) {
                $width = (int) ($size[0] ?? $width);
                $height = (int) ($size[1] ?? $height);
            }
        }

        return '<div class="dfz-mark"><img src="' . Security::escape($this->promoBadgeUrl()) . '" alt="" class="dfz-mark-img" width="' . $width . '" height="' . $height . '" decoding="async"></div>';
    }

    public function renderZone(string $placement, string $pageType = 'all', ?string $serviceId = null, ?string $providerId = null): string
    {
        $content = $this->renderOwnedSlotContent($placement, $pageType, $serviceId, $providerId);
        if ($content === '') {
            return '';
        }

        $key = self::placementDomKey($placement);

        return '<div class="dfz-wrap" data-dfp-wrap="' . Security::escape($key) . '">'
            . $this->renderPromoBadge()
            . '<div class="dfz" data-dfp="' . Security::escape($key) . '">'
            . '<div class="dfz-body">' . $content . '</div></div></div>';
    }

    public static function placementFromDomKey(string $key): ?string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (array_keys(self::PLACEMENTS) as $placement) {
                $map[self::placementDomKey($placement)] = $placement;
            }
        }

        return $map[$key] ?? null;
    }

    public function renderOwnedSlotContent(string $placement, string $pageType = 'all', ?string $serviceId = null, ?string $providerId = null): string
    {
        $ads = $this->getForPlacement($placement, $pageType, $serviceId, $providerId);
        if ($ads === []) {
            return '';
        }

        $html = '';
        foreach ($ads as $ad) {
            $html .= $this->renderAd($ad, $placement);
        }

        return $html;
    }

    public function renderOwnedSlotByKey(string $key, string $pageType = 'all', ?string $serviceId = null, ?string $providerId = null): string
    {
        $placement = self::placementFromDomKey($key);
        if ($placement === null) {
            return '';
        }

        return $this->renderOwnedSlotContent($placement, $pageType, $serviceId, $providerId);
    }

    /** @return array<int, array> */
    public function getDownloadModalAds(?string $serviceId = null, ?string $providerId = null): array
    {
        return $this->getForPlacement('download_modal', 'result', $serviceId, $providerId);
    }

    /** @return array<int, array> */
    public function getPopupAds(?string $serviceId = null, ?string $providerId = null): array
    {
        return $this->getForPlacement('popup', 'all', $serviceId, $providerId);
    }

    public function renderAd(array $ad, string $placement = ''): string
    {
        $type = $ad['type'] ?? 'banner';
        $id = Security::escape($ad['id'] ?? uniqid('z', true));
        $content = $ad['content'] ?? [];

        $wrapStart = '<div class="dfz-u dfz-t-' . Security::escape($type) . '" data-zid="' . $id . '">';
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
        $alt = Security::escape($content['alt'] ?? 'Featured');
        $imgTag = '<img src="' . Security::escape($img) . '" alt="' . $alt . '" class="dfz-img" loading="lazy">';

        if ($link !== '') {
            return '<a href="' . Security::escape($link) . '" class="dfz-lk" target="_blank" rel="noopener noreferrer">' . $imgTag . '</a>';
        }

        return $imgTag;
    }

    private function renderText(array $content): string
    {
        $html = trim((string) ($content['html'] ?? ''));
        if ($html !== '') {
            return '<div class="dfz-tx-html">' . $html . '</div>';
        }

        $text = trim((string) ($content['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        $link = trim((string) ($content['link_url'] ?? ''));
        $title = Security::escape($content['title'] ?? '');
        $body = Security::escape($text);

        $inner = ($title !== '' ? '<strong class="dfz-tx-title">' . $title . '</strong>' : '') . '<p class="dfz-tx-body">' . $body . '</p>';

        if ($link !== '') {
            return '<a href="' . Security::escape($link) . '" class="dfz-tx-lk" target="_blank" rel="noopener noreferrer">' . $inner . '</a>';
        }

        return '<div class="dfz-tx">' . $inner . '</div>';
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

        $code = AdScriptRelay::rewriteMarkup($code, $this->baseUrl, $this->relayScripts);

        if (self::needsScriptIsolation($code)) {
            return '<div class="dfz-raw dfz-raw-isolated">' . $this->renderIsolatedScriptFrame($code) . '</div>';
        }

        return '<div class="dfz-raw">' . $code . '</div>';
    }

    private static function needsScriptIsolation(string $code): bool
    {
        if (!preg_match('/<script\b/i', $code)) {
            return false;
        }

        return preg_match('/atOptions\s*=/i', $code) === 1
            || preg_match('/invoke\.js/i', $code) === 1
            || preg_match_all('/<script\b/i', $code) > 1;
    }

    private function renderIsolatedScriptFrame(string $code): string
    {
        $dims = self::parseInlineAdDimensions($code);
        $width = $dims['width'] > 0 ? $dims['width'] : 300;
        $height = $dims['height'] > 0 ? $dims['height'] : 250;
        $document = '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>html,body{margin:0;padding:0;overflow:hidden;background:transparent;}</style>'
            . '</head><body>' . $code . '</body></html>';
        $srcdoc = htmlspecialchars($document, ENT_QUOTES | ENT_HTML5);

        return '<iframe class="dfz-script-frame" title="Advertisement" loading="lazy" '
            . 'sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox allow-forms allow-same-origin" '
            . 'srcdoc="' . $srcdoc . '" '
            . 'width="' . $width . '" height="' . $height . '" '
            . 'style="border:0;display:block;margin:0 auto;max-width:100%;width:' . $width . 'px;height:' . $height . 'px;"></iframe>';
    }

    /** @return array{width: int, height: int} */
    private static function parseInlineAdDimensions(string $code): array
    {
        $width = 0;
        $height = 0;
        if (preg_match("/['\"]width['\"]\s*:\s*(\d+)/", $code, $m)) {
            $width = (int) $m[1];
        }
        if (preg_match("/['\"]height['\"]\s*:\s*(\d+)/", $code, $m)) {
            $height = (int) $m[1];
        }

        return ['width' => $width, 'height' => $height];
    }

    private function renderVideo(array $content): string
    {
        $url = trim((string) ($content['video_url'] ?? ''));
        if ($url === '') {
            return '';
        }

        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]+)#', $url, $m)) {
            return '<div class="dfz-vid-wrap"><iframe src="https://www.youtube.com/embed/' . Security::escape($m[1]) . '" title="Media" loading="lazy" allowfullscreen></iframe></div>';
        }

        $src = $this->resolveMediaUrl($url);

        return '<video class="dfz-vid" controls preload="none" src="' . Security::escape($src) . '"></video>';
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
    public function getConfig(?string $pageType = 'all', ?string $serviceId = null, ?string $providerId = null): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'gate' => $this->isEnabled(),
            'badge' => $this->promoBadgeUrl(),
            'relay' => rtrim($this->baseUrl, '/') . AdScriptRelay::relayPath() . '?u=',
            'relayScripts' => $this->relayScripts,
            'slotBase' => rtrim($this->baseUrl, '/') . '/assets/c/d',
            'pageType' => $pageType ?? 'all',
            'serviceId' => $serviceId,
            'providerId' => $providerId,
            'download_modal' => [
                'enabled' => $this->getDownloadModalAds($serviceId, $providerId) !== [],
                'countdown' => (int) ($this->data['download_modal_countdown'] ?? 5),
            ],
            'popup' => $this->buildPopupConfig($serviceId, $providerId),
            'owned' => $this->buildOwnedConfig($pageType, $serviceId, $providerId),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function buildPopupConfig(?string $serviceId = null, ?string $providerId = null): array
    {
        $items = [];
        foreach ($this->getPopupAds($serviceId, $providerId) as $ad) {
            $popup = $ad['popup'] ?? [];
            $content = is_array($ad['content'] ?? null) ? $ad['content'] : [];
            $display = (string) ($popup['display'] ?? 'modal');
            $base = [
                'id' => $ad['id'],
                'delay' => (int) ($popup['delay_seconds'] ?? 3),
                'once' => !empty($popup['show_once_per_session']),
            ];

            if ($display === 'window') {
                $url = trim((string) ($content['link_url'] ?? ''));
                if ($url === '' || !preg_match('#^https?://#i', $url)) {
                    continue;
                }
                $items[] = array_merge($base, [
                    'style' => 'window',
                    'url' => $url,
                ]);
                continue;
            }

            $html = $this->renderModalPopupContent($ad);
            if ($html === '') {
                continue;
            }

            $link = trim((string) ($content['link_url'] ?? ''));
            $mode = (string) ($popup['content_mode'] ?? 'html');
            $items[] = array_merge($base, [
                'style' => 'modal',
                'html' => $html,
                'closable' => !isset($popup['closable']) || $popup['closable'],
                'iframe' => $mode === 'iframe' && $link !== '',
            ]);
        }

        return $items;
    }

    /** @param array<string, mixed> $ad */
    private function renderModalPopupContent(array $ad): string
    {
        $popup = is_array($ad['popup'] ?? null) ? $ad['popup'] : [];
        $mode = (string) ($popup['content_mode'] ?? 'html');
        $content = is_array($ad['content'] ?? null) ? $ad['content'] : [];

        if ($mode === 'iframe') {
            $link = trim((string) ($content['link_url'] ?? ''));
            return $link !== '' && preg_match('#^https?://#i', $link) ? $this->renderPopupIframe($link, $content) : '';
        }

        if ($mode === 'image') {
            return $this->renderBanner($content);
        }

        if ($mode === 'video') {
            return $this->renderVideo($content);
        }

        if ($mode === 'text') {
            $html = trim((string) ($content['html'] ?? ''));
            $title = trim((string) ($content['title'] ?? ''));
            if ($html === '' && $title === '') {
                return '';
            }
            $inner = $html !== '' ? $this->renderHtml($content) : '';
            if ($title !== '') {
                $inner = '<strong class="dfz-tx-title">' . Security::escape($title) . '</strong>' . $inner;
            }
            $link = trim((string) ($content['link_url'] ?? ''));
            if ($link !== '' && $html === '' && $title !== '') {
                $inner = '<a href="' . Security::escape($link) . '" class="dfz-tx-lk" target="_blank" rel="noopener noreferrer">' . $inner . '</a>';
            }
            return '<div class="dfz-pop-tx">' . $inner . '</div>';
        }

        $html = trim((string) ($content['html'] ?? ''));
        return $html !== '' ? $this->renderHtml($content) : '';
    }

    /** @param array<string, mixed> $ad */
    private function renderPopupContent(array $ad): string
    {
        $popup = is_array($ad['popup'] ?? null) ? $ad['popup'] : [];
        if (($popup['display'] ?? 'modal') === 'window') {
            return '';
        }

        return $this->renderModalPopupContent($ad);
    }

    /** @param array<string, mixed> $content */
    private function renderPopupIframe(string $url, array $content): string
    {
        $width = max(280, (int) ($content['width'] ?? 600));
        $height = max(200, (int) ($content['height'] ?? 420));

        return '<div class="cz-layer-iframe-wrap">' .
            '<iframe class="cz-layer-iframe" src="' . Security::escape($url) . '" ' .
            'title="Content" width="' . $width . '" height="' . $height . '" ' .
            'loading="lazy" sandbox="allow-scripts allow-same-origin allow-popups allow-forms allow-popups-to-escape-sandbox" ' .
            'referrerpolicy="no-referrer-when-downgrade"></iframe>' .
            '</div>';
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
            'popup' => $this->renderModalPopupContent($ad),
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

    /**
     * @param array<string, array<string, mixed>> $videoPlatforms
     * @param array<string, array<string, mixed>> $audioPlatforms
     * @return array<string, array<string, mixed>>
     */
    public static function placementMapPages(Settings $settings, array $videoPlatforms = [], array $audioPlatforms = []): array
    {
        $services = ServiceConfig::getServices($settings);
        $pages = [
            'global' => [
                'title' => 'All pages (global)',
                'description' => 'Header, footer, and popup zones shared site-wide. More specific maps below override these when set.',
                'layout' => 'global',
            ],
            'home' => [
                'title' => 'Homepage',
                'description' => 'Main landing page with the URL form and platform lists.',
                'layout' => 'home',
            ],
        ];

        foreach ([ServiceConfig::SERVICE_VIDEO => $videoPlatforms, ServiceConfig::SERVICE_AUDIO => $audioPlatforms] as $serviceId => $platforms) {
            if (empty($services[$serviceId]['enabled'])) {
                continue;
            }

            $serviceLabel = (string) ($services[$serviceId]['name'] ?? $services[$serviceId]['nav_label'] ?? $serviceId);
            $type = ServiceConfig::serviceType($serviceId);
            $resultLabel = $type === 'audio' ? 'Audio / MP3 links' : 'Video download links';

            $pages['hub_' . $serviceId] = [
                'title' => $serviceLabel . ' — converter hub',
                'description' => 'Main ' . strtolower($serviceLabel) . ' page that lists all providers (e.g. /' . ServiceConfig::servicePageSlug($serviceId) . ').',
                'layout' => 'platform',
                'service_id' => $serviceId,
            ];

            foreach ($platforms as $providerId => $platform) {
                if (!is_array($platform) || !ServiceConfig::isProviderAssigned($settings, $serviceId, (string) $providerId)) {
                    continue;
                }

                $providerName = (string) ($platform['name'] ?? $platform['h1'] ?? $providerId);
                $slug = (string) ($platform['slug'] ?? $providerId);
                $pageKey = $serviceId . '_' . $providerId;

                $pages['platform_' . $pageKey] = [
                    'title' => $providerName . ' — provider page',
                    'description' => 'Landing page at /' . $slug . ' (' . strtolower($serviceLabel) . '). Assign ads shown only on this provider.',
                    'layout' => 'platform',
                    'service_id' => $serviceId,
                    'provider_id' => (string) $providerId,
                    'provider_name' => $providerName,
                ];
                $pages['result_' . $pageKey] = [
                    'title' => $providerName . ' — result page',
                    'description' => 'Download result page after a ' . $providerName . ' URL is processed (' . $resultLabel . ').',
                    'layout' => 'result',
                    'service_id' => $serviceId,
                    'provider_id' => (string) $providerId,
                    'provider_name' => $providerName,
                    'result_links_label' => $resultLabel,
                ];
            }
        }

        return $pages;
    }

    public static function placementMapKey(string $placement, ?string $serviceId = null, ?string $providerId = null): string
    {
        $key = $placement;
        if ($serviceId !== null && $serviceId !== '' && $serviceId !== ServiceConfig::SERVICE_ALL) {
            $key .= '@' . $serviceId;
            if ($providerId !== null && $providerId !== '') {
                $key .= ':' . $providerId;
            }
        }

        return $key;
    }

    /** @return array{placement: string, service_id: ?string, provider_id: ?string} */
    public static function parsePlacementMapKey(string $key): array
    {
        if (!str_contains($key, '@')) {
            return ['placement' => $key, 'service_id' => null, 'provider_id' => null];
        }

        [$placement, $scope] = explode('@', $key, 2);
        $serviceId = $scope;
        $providerId = null;
        if (str_contains($scope, ':')) {
            [$serviceId, $providerId] = explode(':', $scope, 2);
        }

        return [
            'placement' => $placement,
            'service_id' => $serviceId !== '' ? $serviceId : null,
            'provider_id' => $providerId !== '' ? $providerId : null,
        ];
    }

    public static function isValidPlacementMapKey(string $key): bool
    {
        $parsed = self::parsePlacementMapKey($key);
        if (!isset(self::PLACEMENTS[$parsed['placement']])) {
            return false;
        }

        if ($parsed['service_id'] === null) {
            return $parsed['provider_id'] === null;
        }

        if (!in_array($parsed['service_id'], [ServiceConfig::SERVICE_VIDEO, ServiceConfig::SERVICE_AUDIO], true)) {
            return false;
        }

        if ($parsed['provider_id'] === null) {
            return true;
        }

        return (bool) preg_match('/^[a-z0-9_-]+$/i', $parsed['provider_id']);
    }

    /** @return string[] */
    public static function placementMapLookupKeys(string $placement, ?string $serviceId = null, ?string $providerId = null): array
    {
        $keys = [];
        foreach (self::placementAliases($placement) as $alias) {
            if ($providerId !== null && $providerId !== ''
                && $serviceId !== null && $serviceId !== '' && $serviceId !== ServiceConfig::SERVICE_ALL) {
                $keys[] = self::placementMapKey($alias, $serviceId, $providerId);
            }
            if ($serviceId !== null && $serviceId !== '' && $serviceId !== ServiceConfig::SERVICE_ALL) {
                $keys[] = self::placementMapKey($alias, $serviceId);
            }
            $keys[] = $alias;
        }

        return array_values(array_unique($keys));
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

    /** @return array{width: int, height: int, note: string}|null */
    public static function placementSize(string $placement): ?array
    {
        return self::PLACEMENT_SIZES[$placement] ?? null;
    }

    public static function placementSizeLabel(string $placement): string
    {
        $size = self::placementSize($placement);
        if ($size === null) {
            return '';
        }

        return sprintf('%d×%d px — %s', $size['width'], $size['height'], $size['note']);
    }
}
