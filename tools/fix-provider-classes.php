<?php
$root = dirname(__DIR__) . '/app';
$dirs = glob($root . '/provider/*/Provider.php') ?: glob($root . '/Provider/*/Provider.php') ?: [];
foreach ($dirs as $file) {
    $c = file_get_contents($file);
    $c = preg_replace('/class \w+Provider extends/', 'class Provider extends', $c);
    file_put_contents($file, $c);
    echo basename(dirname($file)) . "\n";
}
