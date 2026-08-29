<?php
declare(strict_types=1);

return [
    'name' => 'Dailymotion',
    'slug' => 'dailymotion-downloader',
    'title' => 'Dailymotion Video Link Tool – Get Dailymotion Video Information',
    'h1' => 'Dailymotion Video Link Tool',
    'meta_description' => 'Paste a Dailymotion URL to retrieve public video metadata and thumbnail information.',
    'description' => 'Use our tool to fetch Dailymotion video title, author, and thumbnail through permitted public endpoints.',
    'icon' => 'dailymotion',
    'keywords' => 'dailymotion video, dailymotion link',
    'supported_domains' => array (
  0 => 'dailymotion.com',
  1 => 'dai.ly',
  2 => 'www.dailymotion.com',
),
    'how_to' => array (
  0 => 'Copy the Dailymotion video URL.',
  1 => 'Paste it into our input box.',
  2 => 'Click Generate Links.',
),
    'faq' => array (
  0 => 
  array (
    'q' => 'Which Dailymotion URLs work?',
    'a' => 'Standard dailymotion.com/video/ and dai.ly short links are supported.',
  ),
),
    'url_examples' => array (
  0 => 'https://www.dailymotion.com/video/x8abc123',
  1 => 'https://dai.ly/x8abc123',
),
    'download_supported' => false,
    'id' => 'dailymotion',
    'host_patterns' => array (
  0 => '/^dailymotion\\.com$/',
  1 => '/^dai\\.ly$/',
),
    'allowed_domains' => array (
  0 => 'dailymotion.com',
  1 => 'dai.ly',
  2 => 'www.dailymotion.com',
),
    'downloads_only' => false,
];
