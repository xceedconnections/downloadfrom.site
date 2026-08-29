<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/app/bootstrap.php';

use App\AdminAuth;
use App\Storage\StorageFactory;
use App\Storage\StorageBootstrap;
use App\Logger;
use App\RateLimiter;
use App\Security;

Logger::init($config);
Security::initSession($config);

$db = StorageFactory::create($config);
StorageBootstrap::ensureInitialized($db, $config);
AdminAuth::createDefaultAdmin($db);
$rateLimiter = new RateLimiter($db, $config);
$auth = new AdminAuth($db, $rateLimiter);

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST[$config['security']['csrf_token_name']] ?? null, $config)) {
        $error = 'Invalid CSRF token.';
    } else {
        $result = $auth->login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '');
        if ($result['success']) {
            header('Location: dashboard.php');
            exit;
        }
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – VideoLink</title>
    <link rel="stylesheet" href="../public/assets/css/main.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">
<div class="admin-login-box">
    <h1>Admin Login</h1>
    <?php if ($error): ?><p class="admin-error"><?= Security::escape($error) ?></p><?php endif; ?>
    <form method="POST">
        <?= Security::csrfField($config) ?>
        <label>Username<input type="text" name="username" required autocomplete="username"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button type="submit" class="btn btn-primary">Login</button>
    </form>
    <p class="admin-note">Default: admin / changeme123 — change immediately.</p>
</div>
</body>
</html>
