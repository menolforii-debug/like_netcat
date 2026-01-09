<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$viewName = isset($_POST['view_name']) ? trim((string) $_POST['view_name']) : '';
$listTpl = isset($_POST['list_tpl']) ? (string) $_POST['list_tpl'] : '';
$singleTpl = isset($_POST['single_tpl']) ? (string) $_POST['single_tpl'] : '';
$systemTpl = isset($_POST['system_tpl']) ? (string) $_POST['system_tpl'] : '';

if ($componentId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

if ($viewName === '') {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Введите название шаблона']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => '_new', 'error' => 'Введите название шаблона']));
}

if (!preg_match('/^[A-Za-z0-9_-]+$/', $viewName)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Ключ шаблона должен быть URL-безопасным']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => '_new', 'error' => 'Ключ шаблона должен быть URL-безопасным']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$viewRepo = new ComponentViewRepo();
$existingView = $viewRepo->findByName($componentId, $viewName);
if ($existingView !== null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Шаблон с таким ключом уже существует']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => '_new', 'error' => 'Шаблон с таким ключом уже существует']));
}

$viewId = $viewRepo->create($componentId, $viewName, $listTpl, $singleTpl, $systemTpl);
$error = null;
if (!writeComponentViewTemplate((string) $component['keyword'], $viewName, $listTpl, $singleTpl, $systemTpl, $error)) {
    $viewRepo->delete($viewId);
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $error ?? 'Не удалось сохранить шаблон']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => '_new', 'error' => $error ?? 'Не удалось сохранить шаблон']));
}

syncComponentViewsJson($componentId);

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'message' => 'Шаблон создан',
        'refresh' => ['#componentsSidebar', '#componentsContent'],
        'focus' => ['component_id' => $componentId],
    ]);
}

redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'view' => $viewName]));
