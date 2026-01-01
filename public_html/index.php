<?php

require __DIR__ . '/../app/bootstrap.php';

$path = '/';
if (isset($_SERVER['REQUEST_URI'])) {
    $parsedPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_string($parsedPath)) {
        $path = $parsedPath;
    }
}
$path = '/' . ltrim($path, '/');

$renderer = new Renderer();
$renderer->renderPath($path);
