<?php

declare(strict_types=1);

return array (
  'name' => 'SoundCloud',
  'slug' => 'soundcloud-mp3',
  'title' => 'SoundCloud to MP3 Downloader',
  'h1' => 'SoundCloud MP3 Downloader',
  'meta_description' => 'Download MP3 from SoundCloud tracks. Paste a SoundCloud URL to get direct audio download links.',
  'description' => 'Download MP3 audio from public SoundCloud tracks and playlists.',
  'keywords' => 'soundcloud mp3, soundcloud downloader, soundcloud to mp3',
  'supported_domains' => 
  array (
    0 => 'soundcloud.com',
    1 => 'on.soundcloud.com',
  ),
  'host_patterns' => 
  array (
    0 => '/^soundcloud\\.com$/',
    1 => '/^on\\.soundcloud\\.com$/',
  ),
  'allowed_domains' => 
  array (
    0 => 'soundcloud.com',
    1 => 'sndcdn.com',
    2 => 'scdn.co',
  ),
  'url_examples' => 
  array (
    0 => 'https://soundcloud.com/artist/track-name',
  ),
  'id' => 'soundcloud',
  'icon' => 'soundcloud',
  'service_type' => 'audio',
  'download_supported' => true,
  'downloads_only' => true,
  'how_to' => 
  array (
    0 => 'Copy the track or video URL from SoundCloud.',
    1 => 'Paste it into the input box on this page.',
    2 => 'Click Generate Links to extract MP3 download options.',
    3 => 'Choose your preferred audio quality and download.',
  ),
  'faq' => 
  array (
    0 => 
    array (
      'q' => 'Is this SoundCloud MP3 downloader free?',
      'a' => 'Yes, it is free for public URLs.',
    ),
    1 => 
    array (
      'q' => 'What format do I get?',
      'a' => 'MP3, M4A, or other audio formats when available from the source.',
    ),
  ),
);
