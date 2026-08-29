<?php

declare(strict_types=1);

require __DIR__ . '/init.php';
$auth->requireAuth();
header('Location: dashboard.php');
exit;
