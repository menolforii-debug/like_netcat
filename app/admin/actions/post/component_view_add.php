<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$view = isset($_POST['view']) ? trim((string) $_POST['view']) : '';

if ($componentId <= 0) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

if ($view === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $view)) {
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'templates', 'error' => 'Некорректное имя шаблона']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
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

redirectTo(buildAdminUrl([
    'action' => 'components',
    'component_id' => $componentId,
    'tab' => 'templates',
    'view' => $view,
]));
