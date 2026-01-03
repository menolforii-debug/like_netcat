<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$fieldsJson = isset($_POST['fields_json']) ? trim((string) $_POST['fields_json']) : '';
$viewsJson = isset($_POST['views_json']) ? trim((string) $_POST['views_json']) : '';

if ($componentId <= 0) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

if ($keyword === '' || $name === '') {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Заполните ключ и название']));
}

if ($fieldsJson === '') {
    $fieldsJson = '[]';
}

if ($viewsJson === '') {
    $viewsJson = '[]';
}

try {
    $fields = parseJsonField($fieldsJson, 'Некорректный JSON полей');
    $views = parseJsonField($viewsJson, 'Некорректный JSON видов отображения');
} catch (InvalidArgumentException $e) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => $e->getMessage()]));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$existing = $componentRepo->findByKeyword($keyword);
if ($existing !== null && (int) $existing['id'] !== $componentId) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент с таким ключом уже существует']));
}

$componentRepo->update($componentId, $keyword, $name, $fields, $views);

if ($user) {
    AdminLog::log($user['id'], 'component_update', 'component', $componentId, [
        'keyword' => $keyword,
        'name' => $name,
    ]);
}

redirectTo(buildAdminUrl(['action' => 'components', 'notice' => 'Компонент обновлен']));
