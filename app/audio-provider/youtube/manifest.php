<?php

declare(strict_types=1);

return array (
  'name' => 'YouTube',
  'slug' => 'youtube-mp3',
  'title' => 'YouTube to MP3 Downloader – Convert YouTube Videos to MP3',
  'h1' => 'YouTube to MP3 Downloader',
  'meta_description' => 'Paste a YouTube URL to download MP3 audio. Free YouTube to MP3 converter with multiple quality options.',
  'description' => 'Download MP3 audio from YouTube videos. Paste any public YouTube, youtu.be, or Shorts link.',
  'keywords' => 'youtube mp3, youtube to mp3, youtube audio download',
  'supported_domains' => 
  array (
    0 => 'youtube.com',
    1 => 'youtu.be',
    2 => 'm.youtube.com',
    3 => 'music.youtube.com',
  ),
  'host_patterns' => 
  array (
    0 => '/^youtube\\.com$/',
    1 => '/^youtu\\.be$/',
    2 => '/^music\\.youtube\\.com$/',
  ),
  'allowed_domains' => 
  array (
    0 => 'youtube.com',
    1 => 'youtu.be',
    2 => 'googlevideo.com',
    3 => 'i.ytimg.com',
  ),
  'url_examples' => 
  array (
    0 => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    1 => 'https://youtu.be/dQw4w9WgXcQ',
  ),
  'id' => 'youtube',
  'icon' => 'youtube',
  'service_type' => 'audio',
  'download_supported' => true,
  'downloads_only' => true,
  'how_to' => 
  array (
    0 => 'Copy the track or video URL from YouTube.',
    1 => 'Paste it into the input box on this page.',
    2 => 'Click Generate Links to extract MP3 download options.',
    3 => 'Choose your preferred audio quality and download.',
  ),
  'faq' => 
  array (
    0 => 
    array (
      'q' => 'Is this YouTube MP3 downloader free?',
      'a' => 'Yes, it is free for public URLs.',
    ),
    1 => 
    array (
      'q' => 'What format do I get?',
      'a' => 'MP3, M4A, or other audio formats when available from the source.',
    ),
  ),
);
