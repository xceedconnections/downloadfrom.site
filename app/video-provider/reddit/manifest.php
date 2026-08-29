<?php
declare(strict_types=1);

return [
    'name' => 'Reddit',
    'slug' => 'reddit-video-downloader',
    'title' => 'Reddit Video Link Tool – Reddit Video Information',
    'h1' => 'Reddit Video Link Tool',
    'meta_description' => 'Paste a Reddit post URL with video to retrieve public metadata and viewing options.',
    'description' => 'Reddit hosts video content across many communities. Our tool fetches publicly available post metadata through Reddit\'s JSON endpoints for supported links.',
    'icon' => 'reddit',
    'keywords' => 'reddit video, reddit download, reddit link',
    'supported_domains' => array (
  0 => 'reddit.com',
  1 => 'www.reddit.com',
  2 => 'old.reddit.com',
  3 => 'v.redd.it',
),
    'how_to' => array (
  0 => 'Copy the Reddit post URL.',
  1 => 'Paste it into our tool.',
  2 => 'Click Generate Links.',
),
    'faq' => array (
  0 => 
  array (
    'q' => 'Are v.redd.it direct links supported?',
    'a' => 'Direct v.redd.it URLs are recognized. Full post URLs provide richer metadata.',
  ),
),
    'url_examples' => array (
  0 => 'https://www.reddit.com/r/videos/comments/abc123/title/',
  1 => 'https://v.redd.it/abc123',
),
    'download_supported' => false,
    'id' => 'reddit',
    'host_patterns' => array (
  0 => '/^reddit\\.com$/',
  1 => '/^v\\.redd\\.it$/',
),
    'allowed_domains' => array (
  0 => 'reddit.com',
  1 => 'www.reddit.com',
  2 => 'old.reddit.com',
  3 => 'v.redd.it',
  4 => 'i.redd.it',
),
    'downloads_only' => false,
];
