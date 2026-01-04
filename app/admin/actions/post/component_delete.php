<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
if ($componentId <= 0) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$infoblock = DB::fetchOne(
    'SELECT 1 FROM infoblocks WHERE component_id = :component_id LIMIT 1',
    ['component_id' => $componentId]
);
if ($infoblock !== null) {
    redirectTo(buildAdminUrl([
        'action' => 'components',
        'component_id' => $componentId,
        'error' => 'Нельзя удалить компонент: он используется в инфоблоках.',
    ]));
}

$viewNames = [];
if (DB::hasTable('component_views')) {
    $viewRepo = new ComponentViewRepo();
    $viewNames = $viewRepo->listNamesForComponent($componentId);
}

if (empty($viewNames)) {
    $viewsJson = $component['views_json'] ?? '[]';
    $decoded = json_decode((string) $viewsJson, true);
    if (is_array($decoded)) {
        $viewNames = $decoded;
    }
}

$componentRepo->deleteWithViews($componentId);

$componentKey = (string) ($component['keyword'] ?? '');
if ($componentKey !== '' && !empty($viewNames)) {
    $root = dirname(__DIR__, 3);
    $templatesDir = $root . '/templates/' . $componentKey;
    foreach ($viewNames as $viewName) {
        $viewName = trim((string) $viewName);
        if ($viewName === '') {
            continue;
        }
        $templatePath = $templatesDir . '/' . $viewName . '.php';
        if (is_file($templatePath)) {
            @unlink($templatePath);
        }
    }
}

if ($user) {
    AdminLog::log($user['id'], 'component_delete', 'component', $componentId, [
        'keyword' => $component['keyword'] ?? null,
        'name' => $component['name'] ?? null,
    ]);
}

redirectTo(buildAdminUrl(['action' => 'components', 'notice' => 'Компонент удален']));
