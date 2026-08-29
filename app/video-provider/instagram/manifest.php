<?php
declare(strict_types=1);

return [
    'name' => 'Instagram',
    'slug' => 'instagram-video-downloader',
    'title' => 'Instagram Video Link Tool – Instagram Reels & Video Info',
    'h1' => 'Instagram Video Link Tool',
    'meta_description' => 'Submit an Instagram video or Reels URL. We provide platform guidance and permitted public information where available.',
    'description' => 'Instagram restricts third-party access to video content. Our tool identifies Instagram URLs and provides appropriate guidance along with any publicly available metadata.',
    'icon' => 'instagram',
    'keywords' => 'instagram video, instagram reels, instagram link',
    'supported_domains' => array (
  0 => 'instagram.com',
  1 => 'www.instagram.com',
),
    'how_to' => array (
  0 => 'Open Instagram and tap Share on the post.',
  1 => 'Copy the link.',
  2 => 'Paste it here and click Generate Links.',
),
    'faq' => array (
  0 => 
  array (
    'q' => 'Can I download Instagram Reels?',
    'a' => 'Direct downloading is not available through permitted public APIs. Use Instagram\'s official save feature.',
  ),
),
    'url_examples' => array (
  0 => 'https://www.instagram.com/reel/ABC123/',
  1 => 'https://www.instagram.com/p/ABC123/',
),
    'download_supported' => false,
    'id' => 'instagram',
    'host_patterns' => array (
  0 => '/^instagram\\.com$/',
),
    'allowed_domains' => array (
  0 => 'instagram.com',
  1 => 'www.instagram.com',
  2 => 'cdninstagram.com',
),
    'downloads_only' => false,
];
