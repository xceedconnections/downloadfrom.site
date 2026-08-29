<?php
declare(strict_types=1);

return [
    'name' => 'TikTok',
    'slug' => 'tiktok-downloader',
    'title' => 'TikTok Video Link Tool – Get TikTok Video Information',
    'h1' => 'TikTok Video Link Tool',
    'meta_description' => 'Paste a TikTok video URL to retrieve public metadata and thumbnail. Supports tiktok.com and vm.tiktok.com links.',
    'description' => 'Enter a public TikTok video URL to fetch title, author, and thumbnail information via TikTok\'s oEmbed endpoint where permitted.',
    'icon' => 'tiktok',
    'keywords' => 'tiktok video, tiktok link, tiktok metadata',
    'supported_domains' => array (
  0 => 'tiktok.com',
  1 => 'vm.tiktok.com',
  2 => 'vt.tiktok.com',
  3 => 'www.tiktok.com',
),
    'how_to' => array (
  0 => 'Open the TikTok app or website and find your video.',
  1 => 'Tap Share and select Copy Link.',
  2 => 'Paste the URL into our tool.',
  3 => 'Click Generate Links to see available information.',
),
    'faq' => array (
  0 => 
  array (
    'q' => 'Does this work with TikTok short links?',
    'a' => 'Yes, vm.tiktok.com and vt.tiktok.com short links are supported and normalized automatically.',
  ),
  1 => 
  array (
    'q' => 'Can I download TikTok videos?',
    'a' => 'Direct downloading is not available for this platform. Please use TikTok\'s official save option within the app where permitted.',
  ),
),
    'url_examples' => array (
  0 => 'https://www.tiktok.com/@user/video/1234567890',
  1 => 'https://vm.tiktok.com/ABC123/',
),
    'download_supported' => true,
    'id' => 'tiktok',
    'host_patterns' => array (
  0 => '/^tiktok\\.com$/',
  1 => '/^vm\\.tiktok\\.com$/',
  2 => '/^vt\\.tiktok\\.com$/',
),
    'allowed_domains' => array (
  0 => 'tiktok.com',
  1 => 'vm.tiktok.com',
  2 => 'vt.tiktok.com',
  3 => 'www.tiktok.com',
),
    'downloads_only' => false,
];
