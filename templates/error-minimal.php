<?php

declare(strict_types=1);

/** @var array $config */
/** @var string $errorMessage */

$siteName = (string) ($config['app']['name'] ?? 'Site');
$message = $errorMessage ?? 'An unexpected error occurred.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Error – <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; padding: 2rem; }
        .card { max-width: 32rem; margin: 4rem auto; background: #fff; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 24px rgba(15,23,42,.08); }
        h1 { margin: 0 0 .75rem; font-size: 1.5rem; }
        p { margin: 0 0 1.25rem; line-height: 1.5; color: #475569; }
        a { color: #2563eb; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Something went wrong</h1>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <p><a href="/">Back to home</a></p>
    </div>
</body>
</html>
