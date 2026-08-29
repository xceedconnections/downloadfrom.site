<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();

$cache = new App\Cache($config);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!App\Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {
        $message = 'Invalid CSRF token.';
    } else {
        $cleared = $cache->clear();
        $message = "Cleared {$cleared} cache entries.";
    }
}

$pageTitle = 'Cache Management';
require __DIR__ . '/layout/header.php';
?>

<h1>Cache Management</h1>
<p>Current cached items: <strong><?= $cache->count() ?></strong></p>
<?php if ($message): ?><p class="admin-success"><?= App\Security::escape($message) ?></p><?php endif; ?>

<form method="POST">
    <?= App\Security::csrfField($config) ?>
    <button type="submit" class="btn btn-primary" onclick="return confirm('Clear all cache?')">Clear All Cache</button>
</form>

<?php require __DIR__ . '/layout/footer.php'; ?>
