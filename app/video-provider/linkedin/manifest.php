<?php
declare(strict_types=1);

return [
    'name' => 'LinkedIn',
    'slug' => 'linkedin-video-downloader',
    'title' => 'LinkedIn Video Link Tool',
    'h1' => 'LinkedIn Video Link Tool',
    'meta_description' => 'Paste a LinkedIn video URL to identify content and view permitted information.',
    'description' => 'Submit a LinkedIn video link for platform identification and guidance.',
    'icon' => 'linkedin',
    'keywords' => 'linkedin video, linkedin link',
    'supported_domains' => array (
  0 => 'linkedin.com',
  1 => 'www.linkedin.com',
),
    'how_to' => array (
  0 => 'Copy the LinkedIn post URL.',
  1 => 'Paste it here.',
  2 => 'Click Generate Links.',
),
    'faq' => array (
),
    'url_examples' => array (
  0 => 'https://www.linkedin.com/posts/example',
),
    'download_supported' => false,
    'id' => 'linkedin',
    'host_patterns' => array (
  0 => '/^linkedin\\.com$/',
),
    'allowed_domains' => array (
  0 => 'linkedin.com',
  1 => 'www.linkedin.com',
),
    'downloads_only' => false,
];
