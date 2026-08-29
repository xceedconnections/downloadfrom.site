<?php
declare(strict_types=1);

return [
    'name' => 'X (Twitter)',
    'slug' => 'twitter-video-downloader',
    'title' => 'X / Twitter Video Link Tool – Video Information',
    'h1' => 'X (Twitter) Video Link Tool',
    'meta_description' => 'Paste an X or Twitter post URL containing video to retrieve permitted public metadata.',
    'description' => 'Submit an X/Twitter status URL with embedded video. We fetch available public metadata through permitted oEmbed endpoints where supported.',
    'icon' => 'twitter',
    'keywords' => 'twitter video, x video, twitter link',
    'supported_domains' => array (
  0 => 'twitter.com',
  1 => 'x.com',
  2 => 'mobile.twitter.com',
),
    'how_to' => array (
  0 => 'Copy the tweet/post URL from X.',
  1 => 'Paste it into our input field.',
  2 => 'Click Generate Links.',
),
    'faq' => array (
  0 => 
  array (
    'q' => 'Does this work with x.com URLs?',
    'a' => 'Yes, both twitter.com and x.com URLs are recognized.',
  ),
),
    'url_examples' => array (
  0 => 'https://twitter.com/user/status/1234567890',
  1 => 'https://x.com/user/status/1234567890',
),
    'download_supported' => false,
    'id' => 'twitter',
    'host_patterns' => array (
  0 => '/^twitter\\.com$/',
  1 => '/^x\\.com$/',
),
    'allowed_domains' => array (
  0 => 'twitter.com',
  1 => 'x.com',
  2 => 'mobile.twitter.com',
  3 => 'publish.twitter.com',
  4 => 'video.twimg.com',
  5 => 'twimg.com',
),
    'downloads_only' => false,
];
