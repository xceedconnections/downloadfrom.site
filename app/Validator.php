<?php

declare(strict_types=1);

namespace App;

class Validator
{
    private int $maxUrlLength;

    public function __construct(array $config)
    {
        $this->maxUrlLength = (int) ($config['security']['max_url_length'] ?? 2048);
    }

    public function validateUrl(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            return $this->fail('URL is required.');
        }

        if (strlen($url) > $this->maxUrlLength) {
            return $this->fail('URL exceeds maximum allowed length.');
        }

        if (!preg_match('/^https?:\/\//i', $url)) {
            if (preg_match('/^[a-zA-Z0-9]/', $url)) {
                $url = 'https://' . $url;
            } else {
                return $this->fail('Invalid URL format. Only http and https are allowed.');
            }
        }

        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host'])) {
            return $this->fail('Malformed URL.');
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return $this->fail('Only http and https protocols are permitted.');
        }

        $host = strtolower($parsed['host']);
        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            return $this->fail('Local URLs are not permitted.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (Security::isPrivateIp($host)) {
                return $this->fail('Private IP addresses are not permitted.');
            }
        }

        $dangerous = ['javascript', 'data', 'file', 'ftp', 'gopher', 'mailto', 'tel'];
        if (in_array($scheme, $dangerous, true)) {
            return $this->fail('Dangerous protocol detected.');
        }

        return ['valid' => true, 'url' => $url, 'host' => $host];
    }

    private function fail(string $message): array
    {
        return ['valid' => false, 'error' => $message];
    }
}
