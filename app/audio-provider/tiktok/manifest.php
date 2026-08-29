<?php

declare(strict_types=1);

return array (
  'name' => 'TikTok',
  'slug' => 'tiktok-mp3',
  'title' => 'TikTok to MP3 Downloader',
  'h1' => 'TikTok MP3 Downloader',
  'meta_description' => 'Extract MP3 audio from TikTok videos. Paste a TikTok link to download the audio track.',
  'description' => 'Download MP3 audio from TikTok videos without watermark when available.',
  'keywords' => 'tiktok mp3, tiktok audio download, tiktok to mp3',
  'supported_domains' => 
  array (
    0 => 'tiktok.com',
    1 => 'vm.tiktok.com',
    2 => 'vt.tiktok.com',
  ),
  'host_patterns' => 
  array (
    0 => '/^tiktok\\.com$/',
    1 => '/^vm\\.tiktok\\.com$/',
    2 => '/^vt\\.tiktok\\.com$/',
  ),
  'allowed_domains' => 
  array (
    0 => 'tiktok.com',
    1 => 'vm.tiktok.com',
    2 => 'vt.tiktok.com',
    3 => 'tiktokcdn.com',
    4 => 'tikwm.com',
  ),
  'url_examples' => 
  array (
    0 => 'https://www.tiktok.com/@user/video/1234567890',
  ),
  'id' => 'tiktok',
  'icon' => 'tiktok',
  'service_type' => 'audio',
  'download_supported' => true,
  'downloads_only' => true,
  'how_to' => 
  array (
    0 => 'Copy the track or video URL from TikTok.',
    1 => 'Paste it into the input box on this page.',
    2 => 'Click Generate Links to extract MP3 download options.',
    3 => 'Choose your preferred audio quality and download.',
  ),
  'faq' => 
  array (
    0 => 
    array (
      'q' => 'Is this TikTok MP3 downloader free?',
      'a' => 'Yes, it is free for public URLs.',
    ),
    1 => 
    array (
      'q' => 'What format do I get?',
      'a' => 'MP3, M4A, or other audio formats when available from the source.',
    ),
  ),
);
