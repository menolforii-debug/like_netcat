<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$viewName = isset($_POST['view_name']) ? trim((string) $_POST['view_name']) : '';
$listTpl = isset($_POST['list_tpl']) ? (string) $_POST['list_tpl'] : '';
$singleTpl = isset($_POST['single_tpl']) ? (string) $_POST['single_tpl'] : '';

if ($componentId <= 0) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

if ($viewName === '') {
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => '_new', 'error' => 'Введите название вида']));
}

if (!preg_match('/^[A-Za-z0-9_-]+$/', $viewName)) {
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => '_new', 'error' => 'Ключ вида должен быть URL-безопасным']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$viewRepo = new ComponentViewRepo();
$existingView = $viewRepo->findByName($componentId, $viewName);
if ($existingView !== null) {
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => '_new', 'error' => 'Вид с таким ключом уже существует']));
}

$viewId = $viewRepo->create($componentId, $viewName, $listTpl, $singleTpl);
$error = null;
if (!writeComponentViewTemplate((string) $component['keyword'], $viewName, $listTpl, $singleTpl, $error)) {
    $viewRepo->delete($viewId);
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => '_new', 'error' => $error ?? 'Не удалось сохранить шаблон']));
}

syncComponentViewsJson($componentId);

redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => $viewName, 'notice' => 'Вид создан']));
