# VideoLink – Multi-Platform Video Downloader



A production-ready, mobile-first PHP video downloader for YouTube, TikTok, Vimeo, Dailymotion, Reddit, and more.



## Provider Plugin Architecture



Each platform lives in its own folder under `app/provider/` (WordPress-style plugins):



```

app/provider/youtube/

  manifest.php    ← SEO, domains, URL detection patterns

  Provider.php    ← metadata + link logic

  Extractor.php   ← optional download extraction (YouTube uses yt-dlp)

app/provider/tiktok/

  manifest.php

  Provider.php

  Extractor.php

```



**Auto-discovery:** `ProviderManager` scans `app/provider/*/` at boot. Delete or rename a folder (e.g. remove `app/provider/tiktok/`) and it disappears from the homepage, sitemap, admin panel, and URL routing — no manual registry edits.



Framework code lives in `app/Provider/` (`ProviderManager`, `ProviderLoader`, `AbstractProvider`).



### Add a new platform



1. Create `app/provider/myplatform/manifest.php` (copy from `youtube/manifest.php`)

2. Create `app/provider/myplatform/Provider.php` extending `App\Provider\AbstractProvider`

3. Optionally add `Extractor.php` with an `extract(string $url): array` method

4. Refresh the site — the platform appears automatically



### Rename a platform folder



Renaming `app/provider/youtube/` to `app/provider/youtuber/` changes the internal `id` and SEO slug (from manifest). Update `slug` in `manifest.php` if you want a specific URL.



## YouTube Downloads



YouTube multi-quality downloads use **yt-dlp** (bundled in `bin/yt-dlp.exe` on Windows).



- Qualities shown: 144p, 240p, 360p, 480p, 720p, 1080p, 1440p, 2160p + audio

- **Node.js** improves extraction (`--js-runtimes node`) — install Node if not present

- On Linux: download yt-dlp to `bin/yt-dlp` and `chmod +x bin/yt-dlp`

- Ensure PHP `shell_exec()` is enabled in `php.ini`



Downloads use **direct CDN links** by default (no server bandwidth). Set `download.proxy_enabled` to `true` in config to stream via `/download/{token}/{index}` instead.



## Quick Start (XAMPP / Shared Hosting)



1. Upload all files to your web folder (e.g. `htdocs/downloadfrom/`)

2. Ensure `storage/` is writable by the web server

3. Open `http://localhost/downloadfrom/` — no `/public` subfolder needed

4. For production, copy `config/config.local.php.example` to `config/config.local.php` and set:



```php

'app' => ['url' => 'https://downloadfrom.site'],

```



## Document Root



Point Apache/Nginx to the **project root** (where `index.php` lives):



```

/downloadfrom/

  index.php      ← entry point

  assets/

  admin/

  app/

    Provider/    ← framework (loader, manager, interfaces)

    provider/    ← platform plugins (youtube, tiktok, …)

  config/

  storage/

  templates/

```



## Admin Panel



- URL: `http://localhost/downloadfrom/admin/`

- Default login: `admin` / `changeme123` — change immediately

- **Platforms** page lists auto-discovered plugins from `app/provider/`



## Features



- Direct video download links (MP4, multiple qualities)

- YouTube, TikTok, Vimeo, Dailymotion, Reddit, Instagram, Facebook, Twitter/X

- JSON storage (no MySQL)

- Rate limiting, CSRF, SSRF protection

- SEO landing pages per platform (from each plugin manifest)

- Mobile-first responsive UI



## Apache



The root `.htaccess` handles clean URLs automatically with XAMPP.



## Nginx



See `nginx.conf.example` — set `root` to the project folder (not a `/public` subfolder).



## Permissions



```bash

chmod -R 750 storage/

chown -R www-data:www-data storage/

```



## Production Checklist



- [ ] Set `config/config.local.php` with your domain

- [ ] Enable HTTPS redirect in `.htaccess`

- [ ] Change admin password

- [ ] Update `robots.txt` sitemap URL

- [ ] Restrict `/admin/` access by IP if possible

