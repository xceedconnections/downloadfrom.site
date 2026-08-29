<?php
declare(strict_types=1);

return [
    'name' => 'Vimeo',
    'slug' => 'vimeo-downloader',
    'title' => 'Vimeo Video Link Tool – Get Vimeo Video Information',
    'h1' => 'Vimeo Video Link Tool',
    'meta_description' => 'Paste a Vimeo URL to retrieve video metadata, duration, and thumbnail via Vimeo\'s public oEmbed API.',
    'description' => 'Vimeo provides a rich oEmbed API for public videos. Our tool retrieves title, author, duration, and thumbnail for supported Vimeo links.',
    'icon' => 'vimeo',
    'keywords' => 'vimeo video, vimeo link, vimeo metadata',
    'supported_domains' => array (
  0 => 'vimeo.com',
  1 => 'player.vimeo.com',
),
    'how_to' => array (
  0 => 'Copy the Vimeo video URL from your browser.',
  1 => 'Paste it into the input field.',
  2 => 'Click Generate Links.',
  3 => 'View metadata and permitted viewing options.',
),
    'faq' => array (
  0 => 
  array (
    'q' => 'Are Vimeo private videos supported?',
    'a' => 'No. Only publicly accessible Vimeo videos can be retrieved.',
  ),
  1 => 
  array (
    'q' => 'Can I download Vimeo videos?',
    'a' => 'Direct downloading depends on the video owner\'s settings. We show metadata and a link to watch on Vimeo.',
  ),
),
    'url_examples' => array (
  0 => 'https://vimeo.com/123456789',
),
    'download_supported' => false,
    'id' => 'vimeo',
    'host_patterns' => array (
  0 => '/^vimeo\\.com$/',
  1 => '/^player\\.vimeo\\.com$/',
),
    'allowed_domains' => array (
  0 => 'vimeo.com',
  1 => 'player.vimeo.com',
),
    'downloads_only' => false,
];
