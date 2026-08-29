<?php
$pageTitle = 'Error – ' . $config['app']['name'];
$pageDescription = 'An error occurred';
$robotsMeta = 'noindex, nofollow';
$adPageType = 'all';
$showServiceSelect = true;
$selectedService = App\ServiceConfig::SERVICE_ALL;
require __DIR__ . '/header.php';
?>

<section class="section error-section">
    <div class="container">
        <div class="error-card">
            <h1>Something went wrong</h1>
            <p class="error-message"><?= App\Security::escape($errorMessage ?? 'An unexpected error occurred.') ?></p>

            <?php if (!empty($prefillUrl)): ?>
            <?php require __DIR__ . '/partials/url-form.php'; ?>
            <?php else: ?>
            <a href="<?= App\Security::escape($baseUrl) ?>/" class="btn btn-primary">Back to Home</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
