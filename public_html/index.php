<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$path = '/' . ltrim($path, '/');

$repo = new SectionRepo();
$section = $repo->findByPath($path);

if ($section === null) {
    http_response_code(404);
    echo 'Section not found';
    exit;
}

$children = $repo->findChildren((int) $section['id']);
$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';

$renderer = new Renderer();
$renderer->renderSection($section, $children, $editMode);
