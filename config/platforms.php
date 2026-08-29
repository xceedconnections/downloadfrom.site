<?php

declare(strict_types=1);

/**
 * @deprecated Platform config now lives in app/provider/{platform}/manifest.php
 * This file is kept only as reference for migration. It is not loaded at runtime.
 */
return [
    'youtube' => [
        'name' => 'YouTube',
        'slug' => 'youtube-downloader',
        'title' => 'YouTube Video Link Tool – Get YouTube Video Information',
        'h1' => 'YouTube Video Link Tool',
        'meta_description' => 'Paste a YouTube URL to retrieve public video metadata, thumbnail, and permitted viewing options. Supports youtube.com, youtu.be, and Shorts links.',
        'description' => 'Submit any public YouTube video URL and our tool will fetch available metadata through YouTube\'s public oEmbed service. View title, channel information, thumbnail, and direct links to watch on YouTube.',
        'icon' => 'youtube',
        'keywords' => 'youtube video, youtube link, youtube metadata, youtube shorts',
        'supported_domains' => ['youtube.com', 'youtu.be', 'm.youtube.com', 'music.youtube.com'],
        'how_to' => [
            'Open YouTube and find the video you want.',
            'Tap Share and copy the video link.',
            'Paste the URL into our input box on this page.',
            'Click Generate Links to view available information and options.',
        ],
        'faq' => [
            ['q' => 'Which YouTube URL formats are supported?', 'a' => 'We support standard watch URLs (youtube.com/watch?v=), short links (youtu.be/), Shorts (youtube.com/shorts/), and mobile URLs.'],
            ['q' => 'Can I download YouTube videos here?', 'a' => 'Direct downloading is not available through YouTube\'s public APIs. We provide metadata and a link to watch on YouTube. Use YouTube Premium\'s official offline feature where available.'],
            ['q' => 'Do private or unlisted videos work?', 'a' => 'Only publicly accessible videos can be retrieved. Private, age-restricted, or region-blocked content may not be available.'],
        ],
        'url_examples' => [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/abc123',
        ],
        'download_supported' => false,
    ],

    'tiktok' => [
        'name' => 'TikTok',
        'slug' => 'tiktok-downloader',
        'title' => 'TikTok Video Link Tool – Get TikTok Video Information',
        'h1' => 'TikTok Video Link Tool',
        'meta_description' => 'Paste a TikTok video URL to retrieve public metadata and thumbnail. Supports tiktok.com and vm.tiktok.com links.',
        'description' => 'Enter a public TikTok video URL to fetch title, author, and thumbnail information via TikTok\'s oEmbed endpoint where permitted.',
        'icon' => 'tiktok',
        'keywords' => 'tiktok video, tiktok link, tiktok metadata',
        'supported_domains' => ['tiktok.com', 'vm.tiktok.com', 'vt.tiktok.com', 'www.tiktok.com'],
        'how_to' => [
            'Open the TikTok app or website and find your video.',
            'Tap Share and select Copy Link.',
            'Paste the URL into our tool.',
            'Click Generate Links to see available information.',
        ],
        'faq' => [
            ['q' => 'Does this work with TikTok short links?', 'a' => 'Yes, vm.tiktok.com and vt.tiktok.com short links are supported and normalized automatically.'],
            ['q' => 'Can I download TikTok videos?', 'a' => 'Direct downloading is not available for this platform. Please use TikTok\'s official save option within the app where permitted.'],
        ],
        'url_examples' => [
            'https://www.tiktok.com/@user/video/1234567890',
            'https://vm.tiktok.com/ABC123/',
        ],
        'download_supported' => false,
    ],

    'vimeo' => [
        'name' => 'Vimeo',
        'slug' => 'vimeo-downloader',
        'title' => 'Vimeo Video Link Tool – Get Vimeo Video Information',
        'h1' => 'Vimeo Video Link Tool',
        'meta_description' => 'Paste a Vimeo URL to retrieve video metadata, duration, and thumbnail via Vimeo\'s public oEmbed API.',
        'description' => 'Vimeo provides a rich oEmbed API for public videos. Our tool retrieves title, author, duration, and thumbnail for supported Vimeo links.',
        'icon' => 'vimeo',
        'keywords' => 'vimeo video, vimeo link, vimeo metadata',
        'supported_domains' => ['vimeo.com', 'player.vimeo.com'],
        'how_to' => [
            'Copy the Vimeo video URL from your browser.',
            'Paste it into the input field.',
            'Click Generate Links.',
            'View metadata and permitted viewing options.',
        ],
        'faq' => [
            ['q' => 'Are Vimeo private videos supported?', 'a' => 'No. Only publicly accessible Vimeo videos can be retrieved.'],
            ['q' => 'Can I download Vimeo videos?', 'a' => 'Direct downloading depends on the video owner\'s settings. We show metadata and a link to watch on Vimeo.'],
        ],
        'url_examples' => ['https://vimeo.com/123456789'],
        'download_supported' => false,
    ],

    'dailymotion' => [
        'name' => 'Dailymotion',
        'slug' => 'dailymotion-downloader',
        'title' => 'Dailymotion Video Link Tool – Get Dailymotion Video Information',
        'h1' => 'Dailymotion Video Link Tool',
        'meta_description' => 'Paste a Dailymotion URL to retrieve public video metadata and thumbnail information.',
        'description' => 'Use our tool to fetch Dailymotion video title, author, and thumbnail through permitted public endpoints.',
        'icon' => 'dailymotion',
        'keywords' => 'dailymotion video, dailymotion link',
        'supported_domains' => ['dailymotion.com', 'dai.ly', 'www.dailymotion.com'],
        'how_to' => [
            'Copy the Dailymotion video URL.',
            'Paste it into our input box.',
            'Click Generate Links.',
        ],
        'faq' => [
            ['q' => 'Which Dailymotion URLs work?', 'a' => 'Standard dailymotion.com/video/ and dai.ly short links are supported.'],
        ],
        'url_examples' => ['https://www.dailymotion.com/video/x8abc123', 'https://dai.ly/x8abc123'],
        'download_supported' => false,
    ],

    'instagram' => [
        'name' => 'Instagram',
        'slug' => 'instagram-video-downloader',
        'title' => 'Instagram Video Link Tool – Instagram Reels & Video Info',
        'h1' => 'Instagram Video Link Tool',
        'meta_description' => 'Submit an Instagram video or Reels URL. We provide platform guidance and permitted public information where available.',
        'description' => 'Instagram restricts third-party access to video content. Our tool identifies Instagram URLs and provides appropriate guidance along with any publicly available metadata.',
        'icon' => 'instagram',
        'keywords' => 'instagram video, instagram reels, instagram link',
        'supported_domains' => ['instagram.com', 'www.instagram.com'],
        'how_to' => [
            'Open Instagram and tap Share on the post.',
            'Copy the link.',
            'Paste it here and click Generate Links.',
        ],
        'faq' => [
            ['q' => 'Can I download Instagram Reels?', 'a' => 'Direct downloading is not available through permitted public APIs. Use Instagram\'s official save feature.'],
        ],
        'url_examples' => ['https://www.instagram.com/reel/ABC123/', 'https://www.instagram.com/p/ABC123/'],
        'download_supported' => false,
    ],

    'facebook' => [
        'name' => 'Facebook',
        'slug' => 'facebook-video-downloader',
        'title' => 'Facebook Video Link Tool – Facebook Video Information',
        'h1' => 'Facebook Video Link Tool',
        'meta_description' => 'Paste a Facebook video URL to identify the content and view permitted public information.',
        'description' => 'Facebook videos require authentication for most content. Our tool validates your URL and provides guidance on viewing through Facebook\'s official platform.',
        'icon' => 'facebook',
        'keywords' => 'facebook video, facebook link',
        'supported_domains' => ['facebook.com', 'fb.watch', 'www.facebook.com', 'm.facebook.com'],
        'how_to' => [
            'Copy the Facebook video URL from the share menu.',
            'Paste it into our tool.',
            'Click Generate Links.',
        ],
        'faq' => [
            ['q' => 'Why can\'t I get a download link?', 'a' => 'Facebook does not provide public APIs for third-party video downloading. Please use Facebook\'s official save/share options.'],
        ],
        'url_examples' => ['https://www.facebook.com/watch/?v=123456789', 'https://fb.watch/abc123/'],
        'download_supported' => false,
    ],

    'twitter' => [
        'name' => 'X (Twitter)',
        'slug' => 'twitter-video-downloader',
        'title' => 'X / Twitter Video Link Tool – Video Information',
        'h1' => 'X (Twitter) Video Link Tool',
        'meta_description' => 'Paste an X or Twitter post URL containing video to retrieve permitted public metadata.',
        'description' => 'Submit an X/Twitter status URL with embedded video. We fetch available public metadata through permitted oEmbed endpoints where supported.',
        'icon' => 'twitter',
        'keywords' => 'twitter video, x video, twitter link',
        'supported_domains' => ['twitter.com', 'x.com', 'mobile.twitter.com'],
        'how_to' => [
            'Copy the tweet/post URL from X.',
            'Paste it into our input field.',
            'Click Generate Links.',
        ],
        'faq' => [
            ['q' => 'Does this work with x.com URLs?', 'a' => 'Yes, both twitter.com and x.com URLs are recognized.'],
        ],
        'url_examples' => ['https://twitter.com/user/status/1234567890', 'https://x.com/user/status/1234567890'],
        'download_supported' => false,
    ],

    'reddit' => [
        'name' => 'Reddit',
        'slug' => 'reddit-video-downloader',
        'title' => 'Reddit Video Link Tool – Reddit Video Information',
        'h1' => 'Reddit Video Link Tool',
        'meta_description' => 'Paste a Reddit post URL with video to retrieve public metadata and viewing options.',
        'description' => 'Reddit hosts video content across many communities. Our tool fetches publicly available post metadata through Reddit\'s JSON endpoints for supported links.',
        'icon' => 'reddit',
        'keywords' => 'reddit video, reddit download, reddit link',
        'supported_domains' => ['reddit.com', 'www.reddit.com', 'old.reddit.com', 'v.redd.it'],
        'how_to' => [
            'Copy the Reddit post URL.',
            'Paste it into our tool.',
            'Click Generate Links.',
        ],
        'faq' => [
            ['q' => 'Are v.redd.it direct links supported?', 'a' => 'Direct v.redd.it URLs are recognized. Full post URLs provide richer metadata.'],
        ],
        'url_examples' => ['https://www.reddit.com/r/videos/comments/abc123/title/', 'https://v.redd.it/abc123'],
        'download_supported' => false,
    ],

    'twitch' => [
        'name' => 'Twitch',
        'slug' => 'twitch-video-downloader',
        'title' => 'Twitch Video Link Tool – Clips & VOD Information',
        'h1' => 'Twitch Video Link Tool',
        'meta_description' => 'Paste a Twitch clip or VOD URL to retrieve public metadata and viewing links.',
        'description' => 'Twitch clips and VODs can be identified and metadata retrieved through permitted public methods.',
        'icon' => 'twitch',
        'keywords' => 'twitch clip, twitch vod, twitch video',
        'supported_domains' => ['twitch.tv', 'clips.twitch.tv', 'www.twitch.tv'],
        'how_to' => [
            'Copy the Twitch clip or VOD URL.',
            'Paste it here.',
            'Click Generate Links.',
        ],
        'faq' => [
            ['q' => 'Can I download Twitch streams?', 'a' => 'Live streams cannot be downloaded through third-party tools. Clips and VODs link back to Twitch for official viewing.'],
        ],
        'url_examples' => ['https://clips.twitch.tv/ClipName', 'https://www.twitch.tv/videos/123456789'],
        'download_supported' => false,
    ],

    'pinterest' => [
        'name' => 'Pinterest',
        'slug' => 'pinterest-video-downloader',
        'title' => 'Pinterest Video Link Tool – Pin Video Information',
        'h1' => 'Pinterest Video Link Tool',
        'meta_description' => 'Paste a Pinterest pin URL to identify video content and view permitted information.',
        'description' => 'Submit a Pinterest pin URL containing video. We identify the platform and provide appropriate viewing guidance.',
        'icon' => 'pinterest',
        'keywords' => 'pinterest video, pinterest pin',
        'supported_domains' => ['pinterest.com', 'pin.it', 'www.pinterest.com'],
        'how_to' => [
            'Open the pin and copy the URL.',
            'Paste into our tool.',
            'Click Generate Links.',
        ],
        'faq' => [
            ['q' => 'Can I download Pinterest videos?', 'a' => 'Direct downloading is not available. Use Pinterest\'s official save feature.'],
        ],
        'url_examples' => ['https://www.pinterest.com/pin/123456789/', 'https://pin.it/abc123'],
        'download_supported' => false,
    ],
];
