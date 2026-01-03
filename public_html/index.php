<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../app/bootstrap.php';

$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
$host = strtolower(trim($host));
if (str_contains($host, ':')) {
    $host = explode(':', $host, 2)[0];
}

$sectionRepo = new SectionRepo();
$site = $sectionRepo->findSiteByHost($host);
if ($site === null) {
    http_response_code(404);
    echo 'Site not found';
    return;
}

$settings = $sectionRepo->getSiteSettings($site);
if (!$settings['site_enabled']) {
    http_response_code(503);
    echo $settings['site_offline_html'];
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
