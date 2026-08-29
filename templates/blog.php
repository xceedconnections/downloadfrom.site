<?php
$pageTitle = $article['title'];
$pageDescription = $article['description'];
$canonicalPath = 'blog/' . ($blogSlug ?? $slug ?? '');
$adPageType = 'all';
require __DIR__ . '/header.php';
?>

<section class="section">
    <div class="container prose">
        <h1><?= App\Security::escape($article['h1']) ?></h1>
        <p><?= App\Security::escape($article['description']) ?></p>

        <?php $slug = $blogSlug ?? ''; if ($slug === 'how-to-save-tiktok-videos'): ?>
        <p>TikTok allows users to save videos within the app when the creator permits it. Tap the Share button, then look for "Save video" if available. Our tool can retrieve public metadata from TikTok URLs but does not provide direct downloads.</p>
        <p><a href="<?= App\Security::escape($baseUrl) ?>/tiktok-downloader">Try our TikTok video tool →</a></p>
        <?php elseif ($slug === 'how-to-save-youtube-videos'): ?>
        <p>YouTube Premium subscribers can download videos for offline viewing through the official YouTube app. Our tool retrieves public metadata via YouTube's oEmbed API and provides links to watch on YouTube.</p>
        <p><a href="<?= App\Security::escape($baseUrl) ?>/youtube-downloader">Try our YouTube video tool →</a></p>
        <?php elseif ($slug === 'how-to-save-vimeo-videos'): ?>
        <p>Some Vimeo videos offer download buttons when the uploader enables them. Our tool fetches public metadata including title, author, and duration through Vimeo's oEmbed API.</p>
        <p><a href="<?= App\Security::escape($baseUrl) ?>/vimeo-downloader">Try our Vimeo video tool →</a></p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
