<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$path = '/' . ltrim($path, '/');

$sectionRepo = new SectionRepo(Database::connection());
$section = $sectionRepo->findByPath($path);

if ($section === null) {
    http_response_code(404);
    echo 'Section not found';
    exit;
}

$component = null;
$infoblock = null;
$items = [];
$core = [];
$editMode = Auth::check('editor');

$templatePath = __DIR__ . '/../templates/section/default.php';
if (!is_file($templatePath)) {
    http_response_code(500);
    echo 'Template not found';
    exit;
}

require $templatePath;
