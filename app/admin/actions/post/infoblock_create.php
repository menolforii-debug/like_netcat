<?php

$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$key = isset($_POST['key']) ? trim((string) $_POST['key']) : '';
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$viewTemplate = isset($_POST['view_template']) ? trim((string) $_POST['view_template']) : '';
$perPage = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 0;
$sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
$isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
$beforeHtml = isset($_POST['before_html']) ? (string) $_POST['before_html'] : '';
$afterHtml = isset($_POST['after_html']) ? (string) $_POST['after_html'] : '';
$beforeImage = isset($_POST['before_image']) ? (string) $_POST['before_image'] : '';
$afterImage = isset($_POST['after_image']) ? (string) $_POST['after_image'] : '';

$section = $sectionRepo->findById($sectionId);
if ($section === null || $componentId === 0 || $name === '' || $key === '') {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Заполните обязательные поля']);
    }
    redirectTo(buildAdminUrl(['action' => 'infoblock_new', 'section_id' => $sectionId, 'error' => 'Заполните обязательные поля']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'infoblock_new', 'section_id' => $sectionId, 'error' => 'Компонент не найден']));
}

if ($viewTemplate === '') {
    $views = componentViews($component);
    $viewTemplate = $views[0] ?? 'list';
}

if ($perPage < 0) {
    $perPage = 0;
}

if (!Utils::isUrlSafe($key)) {
    $message = 'Ключ может содержать только латинские буквы, цифры, дефис и подчёркивание.';
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $message]);
    }
    redirectTo(buildAdminUrl(['action' => 'infoblock_new', 'section_id' => $sectionId, 'error' => $message]));
}

$existing = DB::fetchOne(
    'SELECT id FROM infoblocks WHERE section_id = :section_id AND `key` = :key LIMIT 1',
    ['section_id' => $sectionId, 'key' => $key]
);
if ($existing !== null) {
    $message = 'Инфоблок с таким ключом уже существует в разделе.';
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $message]);
    }
    redirectTo(buildAdminUrl(['action' => 'infoblock_new', 'section_id' => $sectionId, 'error' => $message]));
}

$extra = [
    'before_html' => $beforeHtml,
    'after_html' => $afterHtml,
    'before_image' => $beforeImage,
    'after_image' => $afterImage,
];

$infoblockId = $infoblockRepo->create([
    'site_id' => $section['site_id'],
    'section_id' => $sectionId,
    'component_id' => $componentId,
    'key' => $key,
    'name' => $name,
    'view_template' => $viewTemplate,
    'per_page' => $perPage,
    'extra' => $extra,
    'sort' => $sort,
    'is_enabled' => $isEnabled,
]);

if ($user) {
    AdminLog::log($user['id'], 'infoblock_create', 'infoblock', $infoblockId, [
        'section_id' => $sectionId,
        'component_id' => $componentId,
        'key' => $key,
        'name' => $name,
        'view_template' => $viewTemplate,
        'per_page' => $perPage,
        'sort' => $sort,
        'is_enabled' => $isEnabled,
        'extra' => $extra,
    ]);
}

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'message' => 'Инфоблок создан',
        'refresh' => ['#sidebarTree', '#contentPane'],
    ]);
}
redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks']));
