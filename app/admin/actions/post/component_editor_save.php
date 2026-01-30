<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$view = isset($_POST['view']) ? trim((string) $_POST['view']) : '';
$fileKey = isset($_POST['file']) ? trim((string) $_POST['file']) : 'list';
$content = isset($_POST['content']) ? (string) $_POST['content'] : '';

$allowedFiles = [
    'list' => 'list.php',
    'single' => 'single.php',
    'system' => 'system.php',
];

if ($componentId <= 0) {
    redirectTo(buildAdminUrl(['action' => 'component_editor', 'error' => 'Компонент не найден']));
}

if ($view === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $view)) {
    redirectTo(buildAdminUrl(['action' => 'component_editor', 'component_id' => $componentId, 'error' => 'Некорректное представление']));
}

if (!isset($allowedFiles[$fileKey])) {
    redirectTo(buildAdminUrl(['action' => 'component_editor', 'component_id' => $componentId, 'view' => $view, 'error' => 'Некорректный файл шаблона']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    redirectTo(buildAdminUrl(['action' => 'component_editor', 'error' => 'Компонент не найден']));
}

$componentKey = trim((string) ($component['keyword'] ?? ''));
if ($componentKey === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $componentKey)) {
    redirectTo(buildAdminUrl(['action' => 'component_editor', 'component_id' => $componentId, 'view' => $view, 'file' => $fileKey, 'error' => 'Некорректный ключ компонента']));
}

$root = dirname(__DIR__, 4);
$baseDir = $root . '/templates/component';
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0775, true);
}

$baseReal = realpath($baseDir);
if ($baseReal === false) {
    redirectTo(buildAdminUrl(['action' => 'component_editor', 'component_id' => $componentId, 'view' => $view, 'file' => $fileKey, 'error' => 'Не удалось подготовить папку шаблонов']));
}
$baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$componentDir = $baseReal . $componentKey . '/' . $view;
if (!is_dir($componentDir)) {
    mkdir($componentDir, 0775, true);
}

$componentDirReal = realpath($componentDir);
if ($componentDirReal === false || strpos($componentDirReal . DIRECTORY_SEPARATOR, $baseReal) !== 0) {
    redirectTo(buildAdminUrl(['action' => 'component_editor', 'component_id' => $componentId, 'view' => $view, 'file' => $fileKey, 'error' => 'Некорректный путь к шаблонам']));
}

$fileName = $allowedFiles[$fileKey];
$filePath = $componentDirReal . '/' . $fileName;

file_put_contents($filePath, $content, LOCK_EX);

redirectTo(buildAdminUrl([
    'action' => 'component_editor',
    'component_id' => $componentId,
    'view' => $view,
    'file' => $fileKey,
    'saved' => 1,
]));
