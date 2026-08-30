<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$url = 'https://n6wxm.com/vignette.min.js';
$host = parse_url($url, PHP_URL_HOST);
$http = new App\HttpClient([(string) $host]);
$r = $http->get($url);
echo ($r['success'] ? 'OK' : 'FAIL') . ' status=' . ($r['status'] ?? 0) . ' len=' . strlen((string) ($r['body'] ?? '')) . PHP_EOL;

$encoded = App\AdScriptRelay::encode($url);
$decoded = App\AdScriptRelay::decode($encoded);
echo 'decode=' . ($decoded === $url ? 'match' : 'fail') . PHP_EOL;
$body = App\AdScriptRelay::fetch($url);
echo 'fetch=' . ($body !== null ? strlen($body) : 'null') . PHP_EOL;
