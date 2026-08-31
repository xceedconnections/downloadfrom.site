<?php require __DIR__ . '/../header.php'; ?>
<section class="section"><div class="container prose">
<h1><?= App\Security::escape($staticH1 ?? 'Privacy Policy') ?></h1>
<p>Last updated: <?= date('F j, Y') ?></p>
<h2>Information We Collect</h2>
<p>We collect minimal anonymous usage data including request counts and platform types. We do not store complete IP addresses unless configured for rate limiting, in which case IPs are hashed.</p>
<h2>URLs You Submit</h2>
<p>Video URLs are processed to retrieve public metadata. Results may be temporarily cached and expire automatically.</p>
<h2>Cookies</h2>
<p>We use session cookies for CSRF protection and admin authentication. These are essential for site security.</p>
<h2>Third Parties</h2>
<p>We make outbound requests to video platform APIs to retrieve public metadata. No personal data is shared with third parties.</p>
<h2>Contact</h2>
<p>For privacy inquiries, visit our <a href="<?= App\Security::escape($baseUrl) ?>/contact">contact page</a>.</p>
</div></section>
<?php require __DIR__ . '/../footer.php'; ?>
