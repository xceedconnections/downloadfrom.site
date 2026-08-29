<?php



declare(strict_types=1);



namespace App;



/**

 * Proxies video downloads through the server with correct headers.

 */

class DownloadProxy

{

    private VideoService $videoService;



    public function __construct(VideoService $videoService)

    {

        $this->videoService = $videoService;

    }



    public function stream(string $token, int $index): void

    {

        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {

            http_response_code(404);

            echo 'Invalid token.';

            return;

        }



        $data = $this->videoService->getResult($token);

        if ($data === null || empty($data['links'][$index])) {

            http_response_code(404);

            echo 'Download link expired or not found.';

            return;

        }



        $link = $data['links'][$index];

        if (empty($link['download']) || empty($link['url'])) {

            http_response_code(400);

            echo 'Not a downloadable link.';

            return;

        }



        $mediaUrl = $link['url'];



        // Block HLS manifest URLs – these are tiny playlist files, not the video

        if (str_contains($mediaUrl, '.m3u8') || str_contains($mediaUrl, 'manifest.googlevideo.com')) {

            http_response_code(400);

            echo 'Invalid download link. Please re-submit the video URL to get fresh download links.';

            return;

        }



        $host = parse_url($mediaUrl, PHP_URL_HOST) ?? '';

        if (!$this->isAllowedHost($host)) {

            http_response_code(403);

            echo 'Download host not permitted.';

            return;

        }



        $title = preg_replace('/[^\w\s\-().]/u', '', $data['title'] ?? 'video');

        $title = trim(preg_replace('/\s+/', '_', $title)) ?: 'video';

        $isAudio = ($link['service_type'] ?? '') === 'audio'
            || (($data['service'] ?? '') === ServiceConfig::SERVICE_AUDIO && empty($link['service_type']));
        $sourceExt = strtolower((string) ($link['source_ext'] ?? ''));
        $ext = strtolower((string) ($link['ext'] ?? ($isAudio ? 'mp3' : 'mp4')));
        if ($isAudio && $ext === '' && $sourceExt !== '') {
            $ext = $sourceExt;
        }

        $filename = substr($title, 0, 80) . '.' . $ext;



        $referer = match ($data['platform'] ?? '') {

            'youtube' => 'https://www.youtube.com/',

            'tiktok' => 'https://www.tiktok.com/',

            'vimeo' => 'https://vimeo.com/',

            default => '',

        };



        // Disable output buffering for large file streaming

        while (ob_get_level() > 0) {

            ob_end_clean();

        }



        header('X-Robots-Tag: noindex, nofollow');

        header('Content-Disposition: attachment; filename="' . $filename . '"');

        header('Content-Type: ' . $this->mimeType($ext));
        header('Cache-Control: no-store');

        header('Accept-Ranges: bytes');



        if (!empty($link['filesize']) && (int) $link['filesize'] > 0) {

            header('Content-Length: ' . (int) $link['filesize']);

        }



        $ch = curl_init($mediaUrl);

        curl_setopt_array($ch, [

            CURLOPT_FOLLOWLOCATION => true,

            CURLOPT_TIMEOUT => 0,

            CURLOPT_CONNECTTIMEOUT => 30,

            CURLOPT_BUFFERSIZE => 256 * 1024,

            CURLOPT_HTTPHEADER => array_filter([

                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',

                $referer ? "Referer: {$referer}" : null,

            ]),

            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk): int {

                echo $chunk;

                if (ob_get_level() > 0) {

                    ob_flush();

                }

                flush();

                return strlen($chunk);

            },

        ]);



        $success = curl_exec($ch);

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $error = curl_error($ch);

        curl_close($ch);



        if ($success === false || $httpCode >= 400) {

            Logger::error("Download proxy failed [HTTP {$httpCode}]: {$error} URL: " . substr($mediaUrl, 0, 120));

        }

    }



    private function isAllowedHost(string $host): bool

    {

        if (str_contains($host, 'googlevideo.com')) {

            return true;

        }



        return Security::validateOutboundHost($host, [
            'googlevideo.com', 'fbcdn.net', 'tiktok.com', 'tiktokcdn.com',
            'tiktokcdn-us.com', 'tiktokv.com', 'tiktokv.eu', 'muscdn.com',
            'vimeocdn.com', 'dailymotion.com', 'dmcdn.net',
            'v.redd.it', 'twimg.com', 'cdninstagram.com',
        ]);

    }



    private function mimeType(string $ext): string
    {
        return match (strtolower($ext)) {
            'm4a' => 'audio/mp4',
            'mp3' => 'audio/mpeg',
            'webm' => 'audio/webm',
            'opus' => 'audio/opus',
            'ogg' => 'audio/ogg',
            'mp4' => 'video/mp4',
            default => 'application/octet-stream',
        };
    }
}


