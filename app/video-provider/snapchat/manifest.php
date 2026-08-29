<?php
declare(strict_types=1);

return [
    'name' => 'Snapchat',
    'slug' => 'snapchat-video-downloader',
    'title' => 'Snapchat Video Link Tool',
    'h1' => 'Snapchat Video Link Tool',
    'meta_description' => 'Paste a Snapchat URL to identify content and view permitted information.',
    'description' => 'Submit a Snapchat link. We identify the platform and provide viewing guidance.',
    'icon' => 'snapchat',
    'keywords' => 'snapchat video, snapchat link',
    'supported_domains' => array (
  0 => 'snapchat.com',
  1 => 'www.snapchat.com',
),
    'how_to' => array (
  0 => 'Copy the Snapchat link.',
  1 => 'Paste it here.',
  2 => 'Click Generate Links.',
),
    'faq' => array (
),
    'url_examples' => array (
  0 => 'https://www.snapchat.com/add/example',
),
    'download_supported' => false,
    'id' => 'snapchat',
    'host_patterns' => array (
  0 => '/^snapchat\\.com$/',
),
    'allowed_domains' => array (
  0 => 'snapchat.com',
  1 => 'www.snapchat.com',
),
    'downloads_only' => false,
];
