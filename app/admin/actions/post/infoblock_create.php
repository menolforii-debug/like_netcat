<?php

$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$viewTemplate = isset($_POST['view_template']) ? trim((string) $_POST['view_template']) : '';
$sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
$isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
$settingsJson = isset($_POST['settings_json']) ? (string) $_POST['settings_json'] : '{}';
$beforeHtml = isset($_POST['before_html']) ? (string) $_POST['before_html'] : '';
$afterHtml = isset($_POST['after_html']) ? (string) $_POST['after_html'] : '';
$beforeImage = isset($_POST['before_image']) ? (string) $_POST['before_image'] : '';
$afterImage = isset($_POST['after_image']) ? (string) $_POST['after_image'] : '';

$section = $sectionRepo->findById($sectionId);
if ($section === null || $componentId === 0 || $name === '') {
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

try {
    $settings = parseJsonField($settingsJson, 'settings_json должен быть корректным JSON');
} catch (Throwable $e) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }
    redirectTo(buildAdminUrl(['action' => 'infoblock_new', 'section_id' => $sectionId, 'error' => $e->getMessage()]));
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
    'name' => $name,
    'view_template' => $viewTemplate,
    'settings' => $settings,
    'extra' => $extra,
    'sort' => $sort,
    'is_enabled' => $isEnabled,
]);

if ($user) {
    AdminLog::log($user['id'], 'infoblock_create', 'infoblock', $infoblockId, [
        'section_id' => $sectionId,
        'component_id' => $componentId,
        'name' => $name,
        'view_template' => $viewTemplate,
        'sort' => $sort,
        'is_enabled' => $isEnabled,
        'settings' => $settings,
        'extra' => $extra,
    ]);
}

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'notice' => 'Инфоблок создан',
        'refresh' => ['#sidebarTree', '#contentPane'],
    ]);
}
redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'notice' => 'Инфоблок создан']));
