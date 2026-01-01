<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$path = '/' . ltrim($path, '/');

$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';

$renderer = new Renderer();
$renderer->renderPath($path, $editMode);
