<?php
declare(strict_types=1);

return [
    'name' => 'YouTube',
    'slug' => 'youtube-downloader',
    'title' => 'YouTube Video Downloader – Free MP4 HD 1080p Online',
    'h1' => 'YouTube Video Downloader',
    'meta_description' => 'Download YouTube videos free in 1080p, 720p, 480p MP4. Paste any YouTube or Shorts URL — fast, no signup, works on all devices.',
    'description' => 'Download YouTube videos in HD MP4 quality. Paste any public YouTube, youtu.be, or Shorts link and choose your preferred quality.',
    'icon' => 'youtube',
    'keywords' => 'youtube video downloader, download youtube video, youtube to mp4, youtube mp4 downloader, youtube hd download, youtube shorts downloader',
    'supported_domains' => array (
  0 => 'youtube.com',
  1 => 'youtu.be',
  2 => 'm.youtube.com',
  3 => 'music.youtube.com',
),
    'how_to' => array (
  0 => 'Open YouTube and find the video you want.',
  1 => 'Tap Share and copy the video link.',
  2 => 'Paste the URL into our input box on this page.',
  3 => 'Click Generate Links to view available information and options.',
),
    'faq' => array (
  0 => 
  array (
    'q' => 'Which YouTube URL formats are supported?',
    'a' => 'We support standard watch URLs (youtube.com/watch?v=), short links (youtu.be/), Shorts (youtube.com/shorts/), and mobile URLs.',
  ),
  1 => 
  array (
    'q' => 'Can I download YouTube videos here?',
    'a' => 'Direct downloading is not available through YouTube\'s public APIs. We provide metadata and a link to watch on YouTube. Use YouTube Premium\'s official offline feature where available.',
  ),
  2 => 
  array (
    'q' => 'Do private or unlisted videos work?',
    'a' => 'Only publicly accessible videos can be retrieved. Private, age-restricted, or region-blocked content may not be available.',
  ),
),
    'url_examples' => array (
  0 => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
  1 => 'https://youtu.be/dQw4w9WgXcQ',
  2 => 'https://www.youtube.com/shorts/abc123',
),
    'download_supported' => true,
    'id' => 'youtube',
    'host_patterns' => array (
  0 => '/^youtube\\.com$/',
  1 => '/^youtu\\.be$/',
  2 => '/^music\\.youtube\\.com$/',
),
    'allowed_domains' => array (
  0 => 'youtube.com',
  1 => 'youtu.be',
  2 => 'm.youtube.com',
  3 => 'music.youtube.com',
  4 => 'googlevideo.com',
  5 => 'i.ytimg.com',
  6 => 's.ytimg.com',
),
    'downloads_only' => true,
];
