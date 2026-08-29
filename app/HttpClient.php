<?php

declare(strict_types=1);

namespace App;

class HttpClient
{
    private array $allowedDomains;
    private int $timeout;
    private int $maxRedirects;

    public function __construct(array $allowedDomains, int $timeout = 10, int $maxRedirects = 3)
    {
        $this->allowedDomains = $allowedDomains;
        $this->timeout = $timeout;
        $this->maxRedirects = $maxRedirects;
    }

    public function get(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, null, $headers);
    }

    public function post(string $url, string $body, array $headers = []): array
    {
        return $this->request('POST', $url, $body, $headers);
    }

    private function request(string $method, string $url, ?string $body, array $headers): array
    {
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host'])) {
            return $this->error('Invalid URL for outbound request.');
        }

        $host = strtolower($parsed['host']);
        if (!Security::validateOutboundHost($host, $this->allowedDomains)) {
            Logger::error('SSRF blocked: host not in allowlist - ' . $host);
            return $this->error('Outbound request blocked for security reasons.');
        }

        if (Security::resolveAndValidateHost($host, $this->allowedDomains) === null) {
            Logger::error('SSRF blocked: private/reserved IP for host - ' . $host);
            return $this->error('Outbound request blocked for security reasons.');
        }

        return $this->execute($method, $url, $body, $headers, 0);
    }

    private function execute(string $method, string $url, ?string $body, array $headers, int $redirectCount): array
    {
        if ($redirectCount > $this->maxRedirects) {
            return $this->error('Too many redirects.');
        }

        $ch = curl_init();
        if ($ch === false) {
            return $this->error('Failed to initialize request.');
        }

        $defaultHeaders = [
            'User-Agent: VideoLink/1.0 (+https://example.com)',
            'Accept: application/json, text/html, */*',
        ];
        $allHeaders = array_merge($defaultHeaders, $headers);

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_MAXFILESIZE => 15728640,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body ?? '';
        }

        curl_setopt_array($ch, $options);

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return $this->error('Request failed: ' . $error);
        }

        if ($httpCode >= 300 && $httpCode < 400 && !empty($redirectUrl)) {
            $redirectParsed = parse_url($redirectUrl);
            if ($redirectParsed === false || empty($redirectParsed['host'])) {
                return $this->error('Invalid redirect URL.');
            }
            $redirectHost = strtolower($redirectParsed['host']);
            if (!Security::validateOutboundHost($redirectHost, $this->allowedDomains)) {
                Logger::error('SSRF blocked on redirect: ' . $redirectHost);
                return $this->error('Redirect blocked for security reasons.');
            }
            return $this->execute($method, $redirectUrl, $body, $headers, $redirectCount + 1);
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'status' => $httpCode,
            'body' => $responseBody,
            'effective_url' => (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
            'error' => $httpCode >= 400 ? "HTTP $httpCode" : null,
        ];
    }

    private function error(string $message): array
    {
        return ['success' => false, 'status' => 0, 'body' => null, 'error' => $message];
    }
}
