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

if ($componentId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    adminFlashSet('danger', 'Компонент не найден');
    redirectTo(buildAdminUrl(['action' => 'components']));
}

if ($view === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $view)) {
    $message = 'Некорректное имя шаблона';
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $message]);
    }
    adminFlashSet('danger', $message);
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

$views = [];
$decodedViews = json_decode((string) ($component['views_json'] ?? ''), true);
if (is_array($decodedViews)) {
    foreach ($decodedViews as $existingView) {
        if (is_string($existingView) && $existingView !== '') {
            $views[] = $existingView;
        }
    }
}

$views = array_values(array_filter($views, static function ($item) use ($view): bool {
    return $item !== $view;
}));

$fields = [];
$decodedFields = json_decode((string) ($component['fields_json'] ?? '[]'), true);
if (is_array($decodedFields)) {
    $fields = $decodedFields['fields'] ?? $decodedFields;
    if (!is_array($fields)) {
        $fields = [];
    }
}

$componentRepo->update($componentId, (string) ($component['keyword'] ?? ''), (string) ($component['name'] ?? ''), $fields, $views);

$root = dirname(__DIR__, 4);
$baseDir = $root . '/templates/component';
$baseReal = realpath($baseDir);
if ($baseReal !== false) {
    $baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $componentKey = (string) ($component['keyword'] ?? '');
    if ($componentKey !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $componentKey)) {
        $viewDir = $baseReal . $componentKey . '/' . $view;
        $realViewDir = realpath($viewDir);
        if ($realViewDir !== false && strpos($realViewDir . DIRECTORY_SEPARATOR, $baseReal) === 0) {
            foreach (['list.php', 'single.php', 'system.php'] as $fileName) {
                $filePath = $realViewDir . '/' . $fileName;
                if (is_file($filePath)) {
                    unlink($filePath);
                }
            }
            @rmdir($realViewDir);
        }
    }
}

if (isAjaxRequest()) {
    adminOk('Шаблон удален', [], true, [
        'refresh' => ['#components_block'],
    ]);
}
adminFlashSet('success', 'Шаблон удален');
redirectTo(buildAdminUrl([
    'action' => 'components',
    'component_id' => $componentId,
    'tab' => 'templates',
]));
