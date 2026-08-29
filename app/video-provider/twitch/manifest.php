<?php
declare(strict_types=1);

return [
    'name' => 'Twitch',
    'slug' => 'twitch-video-downloader',
    'title' => 'Twitch Video Link Tool – Clips & VOD Information',
    'h1' => 'Twitch Video Link Tool',
    'meta_description' => 'Paste a Twitch clip or VOD URL to retrieve public metadata and viewing links.',
    'description' => 'Twitch clips and VODs can be identified and metadata retrieved through permitted public methods.',
    'icon' => 'twitch',
    'keywords' => 'twitch clip, twitch vod, twitch video',
    'supported_domains' => array (
  0 => 'twitch.tv',
  1 => 'clips.twitch.tv',
  2 => 'www.twitch.tv',
),
    'how_to' => array (
  0 => 'Copy the Twitch clip or VOD URL.',
  1 => 'Paste it here.',
  2 => 'Click Generate Links.',
),
    'faq' => array (
  0 => 
  array (
    'q' => 'Can I download Twitch streams?',
    'a' => 'Live streams cannot be downloaded through third-party tools. Clips and VODs link back to Twitch for official viewing.',
  ),
),
    'url_examples' => array (
  0 => 'https://clips.twitch.tv/ClipName',
  1 => 'https://www.twitch.tv/videos/123456789',
),
    'download_supported' => false,
    'id' => 'twitch',
    'host_patterns' => array (
  0 => '/^twitch\\.tv$/',
  1 => '/^clips\\.twitch\\.tv$/',
),
    'allowed_domains' => array (
  0 => 'twitch.tv',
  1 => 'clips.twitch.tv',
  2 => 'www.twitch.tv',
),
    'downloads_only' => false,
];
