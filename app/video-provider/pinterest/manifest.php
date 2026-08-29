<?php
declare(strict_types=1);

return [
    'name' => 'Pinterest',
    'slug' => 'pinterest-video-downloader',
    'title' => 'Pinterest Video Link Tool – Pin Video Information',
    'h1' => 'Pinterest Video Link Tool',
    'meta_description' => 'Paste a Pinterest pin URL to identify video content and view permitted information.',
    'description' => 'Submit a Pinterest pin URL containing video. We identify the platform and provide appropriate viewing guidance.',
    'icon' => 'pinterest',
    'keywords' => 'pinterest video, pinterest pin',
    'supported_domains' => array (
  0 => 'pinterest.com',
  1 => 'pin.it',
  2 => 'www.pinterest.com',
),
    'how_to' => array (
  0 => 'Open the pin and copy the URL.',
  1 => 'Paste into our tool.',
  2 => 'Click Generate Links.',
),
    'faq' => array (
  0 => 
  array (
    'q' => 'Can I download Pinterest videos?',
    'a' => 'Direct downloading is not available. Use Pinterest\'s official save feature.',
  ),
),
    'url_examples' => array (
  0 => 'https://www.pinterest.com/pin/123456789/',
  1 => 'https://pin.it/abc123',
),
    'download_supported' => false,
    'id' => 'pinterest',
    'host_patterns' => array (
  0 => '/^pinterest\\.com$/',
  1 => '/^pin\\.it$/',
),
    'allowed_domains' => array (
  0 => 'pinterest.com',
  1 => 'pin.it',
  2 => 'www.pinterest.com',
),
    'downloads_only' => false,
];
