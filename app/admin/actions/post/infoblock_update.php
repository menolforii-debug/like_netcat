<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$viewTemplate = isset($_POST['view_template']) ? trim((string) $_POST['view_template']) : '';
$sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
$isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
$settingsJson = isset($_POST['settings_json']) ? (string) $_POST['settings_json'] : '{}';
$beforeHtml = isset($_POST['before_html']) ? (string) $_POST['before_html'] : '';
$afterHtml = isset($_POST['after_html']) ? (string) $_POST['after_html'] : '';
$beforeImage = isset($_POST['before_image']) ? (string) $_POST['before_image'] : '';
$afterImage = isset($_POST['after_image']) ? (string) $_POST['after_image'] : '';

if ($id === 0 || $name === '') {
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'Заполните обязательные поля']));
}

try {
    $settings = parseJsonField($settingsJson, 'settings_json должен быть корректным JSON');
} catch (Throwable $e) {
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => $e->getMessage()]));
}

$extra = [
    'before_html' => $beforeHtml,
    'after_html' => $afterHtml,
    'before_image' => $beforeImage,
    'after_image' => $afterImage,
];

$infoblockRepo->update($id, [
    'name' => $name,
    'view_template' => $viewTemplate !== '' ? $viewTemplate : 'list',
    'settings' => $settings,
    'extra' => $extra,
    'sort' => $sort,
    'is_enabled' => $isEnabled,
]);

if ($user) {
    AdminLog::log($user['id'], 'infoblock_update', 'infoblock', $id, [
        'name' => $name,
        'view_template' => $viewTemplate,
        'sort' => $sort,
        'is_enabled' => $isEnabled,
        'settings' => $settings,
        'extra' => $extra,
    ]);
}

redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'notice' => 'Инфоблок обновлен']));
