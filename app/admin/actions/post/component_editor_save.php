<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$view = isset($_POST['view']) ? trim((string) $_POST['view']) : '';
$listContent = isset($_POST['list_content']) ? (string) $_POST['list_content'] : '';
$singleContent = isset($_POST['single_content']) ? (string) $_POST['single_content'] : '';
$systemContent = isset($_POST['system_content']) ? (string) $_POST['system_content'] : '';

if ($componentId <= 0) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

if ($view === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $view)) {
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'templates', 'error' => 'Некорректное представление']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$componentKey = trim((string) ($component['keyword'] ?? ''));
if ($componentKey === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $componentKey)) {
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'templates', 'view' => $view, 'error' => 'Некорректный ключ компонента']));
}

$root = dirname(__DIR__, 4);
$baseDir = $root . '/templates/component';
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0775, true);
}

$baseReal = realpath($baseDir);
if ($baseReal === false) {
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'templates', 'view' => $view, 'error' => 'Не удалось подготовить папку шаблонов']));
}
$baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$componentDir = $baseReal . $componentKey . '/' . $view;
if (!is_dir($componentDir)) {
    mkdir($componentDir, 0775, true);
}

$componentDirReal = realpath($componentDir);
if ($componentDirReal === false || strpos($componentDirReal . DIRECTORY_SEPARATOR, $baseReal) !== 0) {
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'templates', 'view' => $view, 'error' => 'Некорректный путь к шаблонам']));
}

$files = [
    'list.php' => $listContent,
    'single.php' => $singleContent,
    'system.php' => $systemContent,
];

foreach ($files as $fileName => $content) {
    $filePath = $componentDirReal . '/' . $fileName;
    file_put_contents($filePath, $content, LOCK_EX);
}

redirectTo(buildAdminUrl([
    'action' => 'components',
    'component_id' => $componentId,
    'tab' => 'templates',
    'view' => $view,
    'saved' => 1,
]));
