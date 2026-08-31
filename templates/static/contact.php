<?php require __DIR__ . '/../header.php'; ?>
<section class="section"><div class="container prose">
<h1><?= App\Security::escape($staticH1 ?? 'Contact') ?></h1>
<p>For support, DMCA notices, or general inquiries, please email:</p>
<p><strong><?= App\Security::escape($config['app']['name']) ?></strong><br>
Email: admin@example.com</p>
<p>We aim to respond within 48 hours on business days.</p>
</div></section>
<?php require __DIR__ . '/../footer.php'; ?>
