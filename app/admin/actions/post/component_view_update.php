<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$viewId = isset($_POST['view_id']) ? (int) $_POST['view_id'] : 0;
$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$viewName = isset($_POST['view_name']) ? trim((string) $_POST['view_name']) : '';
$listTpl = isset($_POST['list_tpl']) ? (string) $_POST['list_tpl'] : '';
$singleTpl = isset($_POST['single_tpl']) ? (string) $_POST['single_tpl'] : '';

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

if ($viewName === '') {
    $viewName = (string) $viewRow['name'];
}

if (!preg_match('/^[A-Za-z0-9_-]+$/', $viewName)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Ключ шаблона должен быть URL-безопасным']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => $viewName, 'error' => 'Ключ шаблона должен быть URL-безопасным']));
}

$error = null;
if (!writeComponentViewTemplate((string) $component['keyword'], $viewName, $listTpl, $singleTpl, $error)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $error ?? 'Не удалось сохранить шаблон']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => $viewName, 'error' => $error ?? 'Не удалось сохранить шаблон']));
}

$viewRepo->update($viewId, $viewName, $listTpl, $singleTpl);
syncComponentViewsJson($componentId);

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'refresh' => ['#componentsSidebar', '#componentsContent'],
        'focus' => ['component_id' => $componentId],
    ]);
}

redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => $viewName]));
