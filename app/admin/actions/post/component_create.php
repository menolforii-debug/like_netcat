<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$fieldsJson = isset($_POST['fields_json']) ? trim((string) $_POST['fields_json']) : '';
$viewsJson = isset($_POST['views_json']) ? trim((string) $_POST['views_json']) : '';

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

$existing = $componentRepo->findByKeyword($keyword);
if ($existing !== null) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент с таким ключом уже существует']));
}

$componentId = $componentRepo->create($keyword, $name, $fields, $views);

if ($user) {
    AdminLog::log($user['id'], 'component_create', 'component', $componentId, [
        'keyword' => $keyword,
        'name' => $name,
    ]);
}

redirectTo(buildAdminUrl(['action' => 'components', 'notice' => 'Компонент создан']));
