<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();
$auth->logout();
header('Location: login.php');
exit;
