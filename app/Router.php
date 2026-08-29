<?php

declare(strict_types=1);

namespace App;

use App\Repositories\FaqRepository;
use App\Storage\DatabaseConnection;

class Router
{
    private array $config;
    private Seo $seo;
    private VideoService $videoService;
    private Validator $validator;
    private RateLimiter $rateLimiter;
    private Settings $settings;
    private array $videoPlatforms;
    private array $audioPlatforms;
    /** @var array<int, array<string, mixed>> */
    private array $servicesNav;
    private AdManager $adManager;

    public function __construct(
        array $config,
        Seo $seo,
        VideoService $videoService,
        Validator $validator,
        RateLimiter $rateLimiter,
        Settings $settings,
        array $videoPlatforms,
        array $audioPlatforms,
        array $servicesNav,
        AdManager $adManager
    ) {
        $this->config = $config;
        $this->seo = $seo;
        $this->videoService = $videoService;
        $this->validator = $validator;
        $this->rateLimiter = $rateLimiter;
        $this->settings = $settings;
        $this->videoPlatforms = $videoPlatforms;
        $this->audioPlatforms = $audioPlatforms;
        $this->servicesNav = $servicesNav;
        $this->adManager = $adManager;
    }

    private function templateVars(): array
    {
        return [
            'config' => $this->config,
            'seo' => $this->seo,
            'settings' => $this->settings,
            'platforms' => $this->videoPlatforms,
            'videoPlatforms' => $this->videoPlatforms,
            'audioPlatforms' => $this->audioPlatforms,
            'servicesNav' => $this->servicesNav,
            'adManager' => $this->adManager,
            'baseUrl' => $this->seo->baseUrl(),
        ];
    }

    public function dispatch(string $uri, string $method): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $path = '/' . trim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        $basePath = AppUrl::basePath($this->config);
        if ($basePath && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }

        if ($method === 'POST' && $path === '/process') {
            $this->handleProcess();
            return;
        }

        match (true) {
            $path === '/' => $this->renderHome(),
            $path === '/privacy' => $this->renderStatic('Privacy Policy', 'privacy'),
            $path === '/terms' => $this->renderStatic('Terms of Service', 'terms'),
            $path === '/dmca' => $this->renderStatic('DMCA / Copyright Policy', 'dmca'),
            $path === '/contact' => $this->renderStatic('Contact', 'contact'),
            $path === '/' . ServiceConfig::PAGE_FAQ => $this->renderFaq(),
            $path === '/' . ServiceConfig::PAGE_VIDEO => $this->renderService(ServiceConfig::SERVICE_VIDEO),
            $path === '/' . ServiceConfig::PAGE_AUDIO => $this->renderService(ServiceConfig::SERVICE_AUDIO),
            str_starts_with($path, '/download/') => $this->handleDownload(substr($path, 10)),
            str_starts_with($path, '/api/cleanup/') => $this->handleCleanup(substr($path, 13)),
            str_starts_with($path, '/api/w/') => $this->handleWidgetApi(substr($path, 7)),
            str_starts_with($path, '/result/') => $this->renderResult(substr($path, 8)),
            str_starts_with($path, '/blog/') => $this->renderBlog(substr($path, 6)),
            default => $this->renderPlatformOr404($path),
        };
    }

    private function handleProcess(): void
    {
        if (!Security::validateCsrfToken($_POST[$this->config['security']['csrf_token_name']] ?? null, $this->config)) {
            http_response_code(403);
            $this->renderError('Invalid request. Please refresh and try again.');
            return;
        }

        $ipHash = Security::hashIp($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $limit = $this->rateLimiter->check($ipHash, 'process');
        if (!$limit['allowed']) {
            http_response_code(429);
            header('Retry-After: ' . ($limit['retry_after'] ?? 60));
            $this->renderError($limit['reason']);
            return;
        }

        $url = $_POST['url'] ?? '';
        $validation = $this->validator->validateUrl($url);

        if (!$validation['valid']) {
            $this->renderError($validation['error'], $url);
            return;
        }

        $serviceId = trim((string) ($_POST['service'] ?? ServiceConfig::SERVICE_ALL));
        if (!ServiceConfig::isValidServiceChoice($serviceId)) {
            $serviceId = ServiceConfig::SERVICE_ALL;
        }

        $result = $this->videoService->process($validation['url'], $serviceId);

        if (!$result['success']) {
            $this->renderError($result['message'], $url);
            return;
        }

        $token = $this->videoService->storeResult($result['data']);
        header('Location: ' . $this->seo->canonical('result/' . $token), true, 302);
        exit;
    }

    private function renderFaq(): void
    {
        extract($this->templateVars());
        $faq = PlatformConfig::applyDynamicFaqAnswers($this->loadFaq('home'), array_merge($this->videoPlatforms, $this->audioPlatforms));
        require dirname(__DIR__) . '/templates/faq.php';
    }

    private function renderService(string $serviceId): void
    {
        if (!ServiceConfig::isServiceEnabled($this->settings, $serviceId)) {
            http_response_code(404);
            $this->renderError('Page not found.');
            return;
        }

        $serviceNav = null;
        foreach ($this->servicesNav as $entry) {
            if (($entry['id'] ?? '') === $serviceId) {
                $serviceNav = $entry;
                break;
            }
        }

        if ($serviceNav === null || ($serviceNav['platforms'] ?? []) === []) {
            http_response_code(404);
            $this->renderError('Page not found.');
            return;
        }

        extract($this->templateVars());
        $serviceMeta = $this->seo->serviceMeta($serviceId, $serviceNav);
        $servicePlatforms = $serviceNav['platforms'];
        $prefillUrl = Security::escape($_GET['url'] ?? '');
        require dirname(__DIR__) . '/templates/service.php';
    }

    private function renderHome(): void
    {
        extract($this->templateVars());
        $meta = $this->seo->homepageMeta();
        $prefillUrl = Security::escape($_GET['url'] ?? '');
        $showServiceSelect = true;
        $selectedService = ServiceConfig::SERVICE_ALL;
        require dirname(__DIR__) . '/templates/home.php';
    }

    private function renderPlatformOr404(string $path): void
    {
        extract($this->templateVars());
        $slug = ltrim($path, '/');
        $platform = ServiceConfig::findPlatformBySlug($slug, $this->videoPlatforms, $this->audioPlatforms);

        if ($platform === null) {
            http_response_code(404);
            $this->renderError('Page not found.');
            return;
        }

        $meta = $platform;
        $currentService = $platform['service'] ?? ServiceConfig::SERVICE_VIDEO;
        $prefillUrl = Security::escape($_GET['url'] ?? '');
        require dirname(__DIR__) . '/templates/platform.php';
    }

    private function handleDownload(string $path): void
    {
        $parts = explode('/', trim($path, '/'));
        $token = $parts[0] ?? '';
        $index = (int) ($parts[1] ?? 0);

        $data = $this->videoService->getResult($token);
        if ($data === null) {
            http_response_code(404);
            echo 'Download link expired or not found.';
            return;
        }

        $platform = (string) ($data['platform'] ?? '');
        $link = $data['links'][$index] ?? [];
        $serviceType = (string) ($link['service_type'] ?? (
            ($data['service'] ?? '') === ServiceConfig::SERVICE_AUDIO ? 'audio' : 'video'
        ));
        if (!PlatformConfig::isProxyEnabled($this->settings, $platform, $this->config, $serviceType)) {
            http_response_code(410);
            echo 'Direct downloads are enabled for this platform. Use the download buttons on the result page.';
            return;
        }

        $proxy = new DownloadProxy($this->videoService);
        $proxy->stream($token, $index);
        exit;
    }

    private function handleCleanup(string $token): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $bodyToken = trim((string) ($_POST['token'] ?? ''));
            if ($bodyToken !== '') {
                $token = $bodyToken;
            }
        }

        $token = trim($token, '/');
        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            http_response_code(400);
            echo 'Invalid token.';
            return;
        }

        $deleted = $this->videoService->cleanupResult($token);
        header('Content-Type: text/plain');
        header('Cache-Control: no-store');
        http_response_code($deleted ? 200 : 500);
        echo $deleted ? 'ok' : 'fail';
        exit;
    }

    private function handleWidgetApi(string $placement): void
    {
        $placement = trim($placement, '/');
        if ($placement === '' || !preg_match('/^[a-z0-9_]+$/', $placement)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['html' => '']);
            return;
        }

        if (!$this->adManager->isEnabled()) {
            header('Content-Type: application/json');
            header('Cache-Control: no-store');
            echo json_encode(['html' => '']);
            return;
        }

        $pageType = trim((string) ($_GET['p'] ?? 'all'));
        $serviceId = trim((string) ($_GET['s'] ?? ''));
        $serviceId = $serviceId !== '' ? $serviceId : null;
        $providerId = trim((string) ($_GET['provider'] ?? ''));
        $providerId = $providerId !== '' ? $providerId : null;

        $html = $this->adManager->renderZone($placement, $pageType, $serviceId, $providerId);

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=300');
        header('X-Robots-Tag: noindex');
        echo json_encode(['html' => $html], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        exit;
    }

    private function renderResult(string $token): void
    {
        extract($this->templateVars());
        header('X-Robots-Tag: noindex, nofollow');
        $data = $this->videoService->getResult($token);

        if ($data === null) {
            http_response_code(404);
            $this->renderError('Result expired or not found.');
            return;
        }

        $resultToken = $token;
        require dirname(__DIR__) . '/templates/result.php';
    }

    private function renderBlog(string $slug): void
    {
        extract($this->templateVars());
        $articles = [
            'how-to-save-tiktok-videos' => [
                'title' => 'How to Save TikTok Videos – Official Methods',
                'h1' => 'How to Save TikTok Videos',
                'description' => 'Learn about official ways to save TikTok videos and how our tool helps you retrieve public video information.',
            ],
            'how-to-save-youtube-videos' => [
                'title' => 'How to Save YouTube Videos – Official Options',
                'h1' => 'How to Save YouTube Videos',
                'description' => 'Discover YouTube Premium offline viewing and how to use our tool for public video metadata.',
            ],
            'how-to-save-vimeo-videos' => [
                'title' => 'How to Save Vimeo Videos – What You Need to Know',
                'h1' => 'How to Save Vimeo Videos',
                'description' => 'Understand Vimeo download options and how our tool retrieves public video information.',
            ],
        ];

        if (!isset($articles[$slug])) {
            http_response_code(404);
            $this->renderError('Article not found.');
            return;
        }

        $article = $articles[$slug];
        $blogSlug = $slug;
        require dirname(__DIR__) . '/templates/blog.php';
    }

    private function renderStatic(string $title, string $page): void
    {
        extract($this->templateVars());
        $pageTitle = $title;
        $pageDescription = $title;
        require dirname(__DIR__) . '/templates/static/' . $page . '.php';
    }

    private function renderError(string $message, string $url = ''): void
    {
        extract($this->templateVars());
        $errorMessage = $message;
        $prefillUrl = Security::escape($url);
        require dirname(__DIR__) . '/templates/error.php';
    }

    private function loadFaq(string $section): array
    {
        $repo = new FaqRepository(DatabaseConnection::get());
        $items = $repo->loadSection($section);
        if ($items === []) {
            return $this->defaultHomeFaq();
        }

        return $items;
    }

    private function defaultHomeFaq(): array
    {
        return [
            ['q' => 'Is this service free?', 'a' => 'Yes, our video URL tool is free to use for public video links.'],
            ['q' => 'Which platforms are supported?', 'a' => PlatformConfig::supportedPlatformsSentence(array_merge($this->videoPlatforms, $this->audioPlatforms))],
            ['q' => 'Can I download any video?', 'a' => 'No. We only provide information and links permitted by each platform. Direct downloading is not available where platforms prohibit it.'],
            ['q' => 'Do you store my URLs?', 'a' => 'Results are temporarily cached to improve performance and expire automatically. We do not store personal information.'],
        ];
    }
}
