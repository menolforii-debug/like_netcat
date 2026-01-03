<?php

$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$viewTemplate = isset($_POST['view_template']) ? trim((string) $_POST['view_template']) : '';
$sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
$isEnabled = isset($_POST['is_enabled']) ? 1 : 0;

$section = $sectionRepo->findById($sectionId);
if ($section === null || $componentId === 0 || $name === '') {
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'Заполните обязательные поля']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'Компонент не найден']));
}

if ($viewTemplate === '') {
    $views = componentViews($component);
    $viewTemplate = $views[0] ?? 'list';
}

$infoblockId = $infoblockRepo->create([
    'site_id' => $section['site_id'],
    'section_id' => $sectionId,
    'component_id' => $componentId,
    'name' => $name,
    'view_template' => $viewTemplate,
    'settings' => [],
    'extra' => [],
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
    ]);
}

redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'notice' => 'Инфоблок создан']));
