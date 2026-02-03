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
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Некорректное имя шаблона']);
    }
    adminFlashSet('danger', 'Некорректное имя шаблона');
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

if (!in_array($view, $views, true)) {
    $views[] = $view;
}

$fields = [];
$decodedFields = json_decode((string) ($component['fields_json'] ?? '[]'), true);
if (is_array($decodedFields)) {
    $fields = $decodedFields['fields'] ?? $decodedFields;
    if (!is_array($fields)) {
        $fields = [];
    }
}

$componentRepo->update($componentId, (string) ($component['keyword'] ?? ''), (string) ($component['name'] ?? ''), $fields, $views);

if (isAjaxRequest()) {
    adminOk('Шаблон добавлен', [], true, [
        'redirect' => buildAdminUrl([
            'action' => 'components',
            'component_id' => $componentId,
            'tab' => 'templates',
            'view' => $view,
        ]),
    ]);
}
adminFlashSet('success', 'Шаблон добавлен');

redirectTo(buildAdminUrl([
    'action' => 'components',
    'component_id' => $componentId,
    'tab' => 'templates',
    'view' => $view,
]));
