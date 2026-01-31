<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../app/public/bootstrap.php';

$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';

$sectionRepo = new SectionRepo();
$site = $sectionRepo->findSiteByHost($host);
if ($site === null) {
    http_response_code(404);
    echo 'Site not found';
    return;
}

$path = '/';
if (isset($_SERVER['REQUEST_URI'])) {
    $parsedPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_string($parsedPath) && $parsedPath !== '') {
        $path = $parsedPath;
    }
}
$path = '/' . ltrim($path, '/');
if ($path === '/index') {
    header('Location: /', true, 301);
    exit;
}

$renderer = new Renderer();
$renderer->renderSitePath($site, $path);
