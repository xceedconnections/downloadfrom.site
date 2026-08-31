<?php

declare(strict_types=1);

$config = require __DIR__ . '/app/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$baseUrl = rtrim($config['app']['url'], '/');

echo "User-agent: *\n";
echo "Allow: /\n";
echo "\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n";
echo "Disallow: /download/\n";
echo "Disallow: /result/\n";
echo "Disallow: /storage/\n";
echo "Disallow: /app/\n";
echo "Disallow: /config/\n";
echo "Disallow: /templates/\n";
echo "\n";
echo 'Sitemap: ' . $baseUrl . "/sitemap.php\n";
