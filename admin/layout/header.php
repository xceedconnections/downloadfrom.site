<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= App\Security::escape($pageTitle ?? 'Admin') ?> – VideoLink Admin</title>

    <link rel="stylesheet" href="../assets/css/main.css">

    <link rel="stylesheet" href="assets/admin.css">

</head>

<body class="admin-body">

<div class="admin-layout">

    <aside class="admin-sidebar">

        <div class="admin-brand">VideoLink Admin</div>

        <nav>

            <a href="dashboard.php">Dashboard</a>

            <a href="services.php">Services</a>

            <a href="providers.php">Video Providers</a>

            <a href="audio-providers.php">Audio Providers</a>

            <a href="ads.php">Ad Management</a>

            <a href="platforms.php">Platforms</a>

            <a href="pages.php">SEO Pages</a>

            <a href="faq.php">FAQ</a>

            <a href="settings.php">Site Settings</a>

            <a href="cache.php">Cache</a>

            <a href="logs.php">Logs</a>

            <a href="logout.php">Logout</a>

        </nav>

    </aside>

    <main class="admin-content">

