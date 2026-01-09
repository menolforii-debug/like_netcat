<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$viewId = isset($_POST['view_id']) ? (int) $_POST['view_id'] : 0;
$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;

if ($viewId <= 0 || $componentId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Шаблон не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Шаблон не найден']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$viewRepo = new ComponentViewRepo();
$viewRow = $viewRepo->findById($viewId);
if ($viewRow === null || (int) $viewRow['component_id'] !== $componentId) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Шаблон не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'error' => 'Шаблон не найден']));
}

if ($viewRepo->countForComponent($componentId) <= 1) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Нельзя удалить последний шаблон']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => (string) $viewRow['name'], 'error' => 'Нельзя удалить последний шаблон']));
}

$viewName = (string) $viewRow['name'];
$viewRepo->delete($viewId);
syncComponentViewsJson($componentId);

$templatePath = dirname(__DIR__, 3) . '/templates/component/' . (string) $component['keyword'] . '/' . $viewName . '.php';
if (is_file($templatePath)) {
    @unlink($templatePath);
}

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'refresh' => ['#componentsSidebar', '#componentsContent'],
        'focus' => ['component_id' => $componentId],
    ]);
}

redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId]));
