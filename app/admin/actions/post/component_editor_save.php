<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    // fallback без сообщений
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl([]));
}

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$view = isset($_POST['view']) ? trim((string) $_POST['view']) : '';
$listContent = isset($_POST['list_content']) ? (string) $_POST['list_content'] : '';
$singleContent = isset($_POST['single_content']) ? (string) $_POST['single_content'] : '';
$systemContent = isset($_POST['system_content']) ? (string) $_POST['system_content'] : '';

if ($componentId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    adminFlashSet('danger', 'Компонент не найден');
    redirectTo(buildAdminUrl(['action' => 'components']));
}

if ($view === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $view)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Некорректное представление']);
    }
    adminFlashSet('danger', 'Некорректное представление');
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'templates']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    adminFlashSet('danger', 'Компонент не найден');
    redirectTo(buildAdminUrl(['action' => 'components']));
}

$componentKey = trim((string) ($component['keyword'] ?? ''));
if ($componentKey === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $componentKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Некорректный ключ компонента']);
    }
    adminFlashSet('danger', 'Некорректный ключ компонента');
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'templates', 'view' => $view]));
}

$root = dirname(__DIR__, 4);
$baseDir = $root . '/templates/component';
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0775, true);
}

$baseReal = realpath($baseDir);
if ($baseReal === false) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Не удалось подготовить папку шаблонов']);
    }
    adminFlashSet('danger', 'Не удалось подготовить папку шаблонов');
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'templates', 'view' => $view]));
}
$baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$componentDir = $baseReal . $componentKey . '/' . $view;
if (!is_dir($componentDir)) {
    mkdir($componentDir, 0775, true);
}

$componentDirReal = realpath($componentDir);
if ($componentDirReal === false || strpos($componentDirReal . DIRECTORY_SEPARATOR, $baseReal) !== 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Некорректный путь к шаблонам']);
    }
    adminFlashSet('danger', 'Некорректный путь к шаблонам');
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'templates', 'view' => $view]));
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

if (isAjaxRequest()) {
    adminOk('Шаблоны сохранены', [], true, [
        'redirect' => buildAdminUrl([
            'action' => 'components',
            'component_id' => $componentId,
            'tab' => 'templates',
            'view' => $view,
        ]),
    ]);
}
adminFlashSet('success', 'Шаблоны сохранены');

redirectTo(buildAdminUrl([
    'action' => 'components',
    'component_id' => $componentId,
    'tab' => 'templates',
    'view' => $view,
]));
