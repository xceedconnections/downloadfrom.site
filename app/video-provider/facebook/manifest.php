<?php
declare(strict_types=1);

return [
    'name' => 'Facebook',
    'slug' => 'facebook-video-downloader',
    'title' => 'Facebook Video Link Tool – Facebook Video Information',
    'h1' => 'Facebook Video Link Tool',
    'meta_description' => 'Paste a Facebook video URL to identify the content and view permitted public information.',
    'description' => 'Facebook videos require authentication for most content. Our tool validates your URL and provides guidance on viewing through Facebook\'s official platform.',
    'icon' => 'facebook',
    'keywords' => 'facebook video, facebook link',
    'supported_domains' => array (
  0 => 'facebook.com',
  1 => 'fb.watch',
  2 => 'www.facebook.com',
  3 => 'm.facebook.com',
),
    'how_to' => array (
  0 => 'Copy the Facebook video URL from the share menu.',
  1 => 'Paste it into our tool.',
  2 => 'Click Generate Links.',
),
    'faq' => array (
  0 => 
  array (
    'q' => 'Why can\'t I get a download link?',
    'a' => 'Facebook does not provide public APIs for third-party video downloading. Please use Facebook\'s official save/share options.',
  ),
),
    'url_examples' => array (
  0 => 'https://www.facebook.com/watch/?v=123456789',
  1 => 'https://fb.watch/abc123/',
),
    'download_supported' => false,
    'id' => 'facebook',
    'host_patterns' => array (
  0 => '/^facebook\\.com$/',
  1 => '/^fb\\.watch$/',
),
    'allowed_domains' => array (
  0 => 'facebook.com',
  1 => 'fb.watch',
  2 => 'www.facebook.com',
  3 => 'm.facebook.com',
  4 => 'fbcdn.net',
),
    'downloads_only' => false,
];
