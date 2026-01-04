<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$viewId = isset($_POST['view_id']) ? (int) $_POST['view_id'] : 0;
$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;

if ($viewId <= 0 || $componentId <= 0) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Вид не найден']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$viewRepo = new ComponentViewRepo();
$viewRow = $viewRepo->findById($viewId);
if ($viewRow === null || (int) $viewRow['component_id'] !== $componentId) {
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'error' => 'Вид не найден']));
}

if ($viewRepo->countForComponent($componentId) <= 1) {
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => (string) $viewRow['name'], 'error' => 'Нельзя удалить последний вид']));
}

$viewName = (string) $viewRow['name'];
$viewRepo->delete($viewId);
syncComponentViewsJson($componentId);

$templatePath = dirname(__DIR__, 3) . '/templates/' . (string) $component['keyword'] . '/' . $viewName . '.php';
if (is_file($templatePath)) {
    @unlink($templatePath);
}

redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'notice' => 'Вид удален']));
