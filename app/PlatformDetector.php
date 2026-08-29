<?php



declare(strict_types=1);



namespace App;



class PlatformDetector

{

    /** @var array<string, array<string>> */

    private array $patterns = [];



    /** @var array<string, callable> */

    private array $normalizers = [];



    public function setPatterns(array $patterns): void

    {

        $this->patterns = $patterns;

    }



    public function setNormalizers(array $normalizers): void

    {

        $this->normalizers = $normalizers;

    }



    public function normalize(string $url): string

    {

        $url = trim($url);

        $url = preg_replace('/\s+/', '', $url);



        if (!preg_match('/^https?:\/\//i', $url)) {

            $url = 'https://' . ltrim($url, '/');

        }



        $platform = $this->detect($url);

        if ($platform !== 'unknown' && isset($this->normalizers[$platform])) {

            return ($this->normalizers[$platform])($url);

        }



        return $url;

    }



    public function detect(string $url): string

    {

        $parsed = parse_url(trim($url));

        if ($parsed === false || empty($parsed['host'])) {

            return 'unknown';

        }



        $host = strtolower($parsed['host']);

        $host = preg_replace('/^www\./', '', $host);

        $host = preg_replace('/^m\./', '', $host);



        foreach ($this->patterns as $platform => $regexes) {

            foreach ($regexes as $pattern) {

                if (preg_match($pattern, $host)) {

                    return $platform;

                }

            }

        }



        return 'unknown';

    }



    public function getSupportedPlatforms(): array

    {

        return array_keys($this->patterns);

    }

}


