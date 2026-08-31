<?php

declare(strict_types=1);

namespace App;

/**
 * Default SEO metadata for core, static, and blog pages (seeded into page_seo).
 */
final class PageSeoDefaults
{
    /** @return list<array<string, string>> */
    public static function all(): array
    {
        return [
            self::row(
                'home',
                'Homepage',
                'core',
                'YouTube Video Downloader – Free MP4 & MP3 Online',
                'Free YouTube Video Downloader & MP3 Converter',
                'Download YouTube videos in HD MP4 or convert to MP3 free. Paste any link — no signup, fast quality options on mobile and desktop.',
                'Download YouTube videos in HD MP4 or convert to MP3 free. Paste any YouTube, TikTok, or Shorts link and get download options in seconds.',
                'youtube video downloader, youtube to mp4, youtube to mp3, free youtube downloader, download youtube video, youtube mp3 converter, online video downloader',
                self::homeSeoContent()
            ),
            self::row(
                ServiceConfig::PAGE_VIDEO,
                'Video Converter',
                'core',
                'Free Video Converter – Download YouTube, TikTok & More',
                'Online Video Converter',
                'Convert and download videos from YouTube, TikTok, Vimeo and more. Paste a link, pick quality, save MP4 free — no software needed.',
                'Convert and download videos from YouTube, TikTok, Vimeo and more. Paste a link, choose your quality, and save MP4 files instantly — free, no account required.',
                'video converter, online video downloader, youtube video download, tiktok video download, mp4 downloader, free video converter',
                ''
            ),
            self::row(
                ServiceConfig::PAGE_AUDIO,
                'Audio Converter',
                'core',
                'YouTube to MP3 Converter – Free Audio Download Online',
                'YouTube to MP3 Converter',
                'Convert YouTube to MP3 free. Paste any YouTube URL and download high-quality MP3 audio in seconds — no account, works on phone and PC.',
                'Convert YouTube to MP3 free. Paste any YouTube, Shorts, or music link and download MP3 audio in your preferred quality — fast and free.',
                'youtube to mp3, youtube mp3 converter, youtube to mp3 downloader, convert youtube to mp3, youtube mp3 download free, mp3 converter online',
                ''
            ),
            self::row(
                ServiceConfig::PAGE_FAQ,
                'FAQ',
                'core',
                'FAQ – YouTube Downloader & MP3 Converter Help',
                'Frequently Asked Questions',
                'Answers about our YouTube video downloader, MP3 converter, supported platforms, downloads, privacy, and how the service works.',
                'Find answers about supported platforms, video and audio downloads, privacy, and how to use our free online converter tools.',
                'youtube downloader faq, youtube to mp3 help, video downloader questions, mp3 converter support',
                ''
            ),
            self::row(
                'privacy',
                'Privacy Policy',
                'static',
                'Privacy Policy – DownloadFrom.Site',
                'Privacy Policy',
                'How DownloadFrom.Site handles your data when you use our free YouTube video downloader and MP3 converter tools.',
                'Read our privacy policy to learn what information we collect and how we protect your data when using our video and audio tools.',
                'privacy policy, data protection, video downloader privacy',
                ''
            ),
            self::row(
                'terms',
                'Terms of Service',
                'static',
                'Terms of Service – DownloadFrom.Site',
                'Terms of Service',
                'Terms and conditions for using DownloadFrom.Site YouTube video downloader, MP3 converter, and related online tools.',
                'Review the terms and conditions for using our free online video and audio converter services.',
                'terms of service, user agreement, downloader terms',
                ''
            ),
            self::row(
                'dmca',
                'DMCA Policy',
                'static',
                'DMCA / Copyright Policy – DownloadFrom.Site',
                'DMCA / Copyright Policy',
                'Copyright and DMCA policy for DownloadFrom.Site. How to submit takedown notices for our video downloader service.',
                'Information about copyright compliance and how to submit DMCA takedown requests.',
                'dmca policy, copyright notice, takedown request',
                ''
            ),
            self::row(
                'contact',
                'Contact',
                'static',
                'Contact Us – DownloadFrom.Site Support',
                'Contact',
                'Contact DownloadFrom.Site for support, DMCA notices, or questions about our YouTube downloader and MP3 converter.',
                'Get in touch for support, copyright notices, or general questions about our free online video and audio tools.',
                'contact support, downloader help, dmca contact',
                ''
            ),
            self::row(
                'blog/how-to-save-youtube-videos',
                'Blog: Save YouTube Videos',
                'blog',
                'How to Download YouTube Videos – Free MP4 Guide 2026',
                'How to Download YouTube Videos',
                'Learn how to download YouTube videos in MP4 free. Step-by-step guide to saving YouTube, Shorts, and music videos online.',
                'Discover the best ways to save YouTube videos as MP4 files. Learn how our free YouTube video downloader works and what formats are available.',
                'how to download youtube videos, save youtube video, youtube mp4 download guide, youtube downloader tutorial',
                ''
            ),
            self::row(
                'blog/how-to-save-tiktok-videos',
                'Blog: Save TikTok Videos',
                'blog',
                'How to Download TikTok Videos – Free Online Guide',
                'How to Save TikTok Videos',
                'Learn how to download TikTok videos without watermark. Free guide to saving TikTok clips as MP4 on phone and desktop.',
                'Step-by-step guide to saving TikTok videos. Learn official methods and how our free TikTok video downloader can help.',
                'how to download tiktok videos, save tiktok video, tiktok mp4 download, tiktok downloader guide',
                ''
            ),
            self::row(
                'blog/how-to-save-vimeo-videos',
                'Blog: Save Vimeo Videos',
                'blog',
                'How to Download Vimeo Videos – Free Online Guide',
                'How to Save Vimeo Videos',
                'Learn how to download Vimeo videos when permitted. Free guide to saving Vimeo clips as MP4 online.',
                'Understand Vimeo download options and how our free tool retrieves public video information and download links.',
                'how to download vimeo videos, save vimeo video, vimeo mp4 download, vimeo downloader guide',
                ''
            ),
        ];
    }

    /** @return array<string, string> */
    private static function row(
        string $pageKey,
        string $pageLabel,
        string $pageType,
        string $title,
        string $h1,
        string $metaDescription,
        string $description,
        string $keywords,
        string $seoContent
    ): array {
        return [
            'page_key' => $pageKey,
            'page_label' => $pageLabel,
            'page_type' => $pageType,
            'title' => $title,
            'h1' => $h1,
            'meta_description' => $metaDescription,
            'description' => $description,
            'keywords' => $keywords,
            'og_image' => '',
            'robots' => 'index, follow',
            'seo_content' => $seoContent,
        ];
    }

    private static function homeSeoContent(): string
    {
        return <<<'HTML'
<h2>Free YouTube Video Downloader &amp; MP3 Converter Online</h2>
<p>DownloadFrom.Site is a free online YouTube video downloader and YouTube to MP3 converter. Paste any public YouTube, YouTube Shorts, TikTok, or supported platform link to get MP4 video download options or convert audio to MP3 — no software, no registration, and no limits on public URLs.</p>
<p>Our tool supports multiple quality options including 1080p, 720p, and 480p MP4 when available from the source. Whether you need a YouTube to MP4 downloader for saving clips offline or a YouTube to MP3 converter for extracting music and podcasts, DownloadFrom.Site works on desktop, tablet, and mobile browsers.</p>
<h3>Why Choose Our YouTube Downloader?</h3>
<ul>
<li><strong>Fast &amp; free</strong> — generate download links in seconds with no account</li>
<li><strong>HD quality</strong> — MP4 in 1080p, 720p, and lower when the source allows</li>
<li><strong>MP3 converter</strong> — extract audio from YouTube videos in multiple bitrates</li>
<li><strong>Multi-platform</strong> — YouTube, TikTok, Vimeo, and more supported services</li>
<li><strong>Mobile friendly</strong> — works in any browser on phone, tablet, or PC</li>
</ul>
HTML;
    }
}
