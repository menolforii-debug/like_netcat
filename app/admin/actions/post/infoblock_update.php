<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
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

if ($id === 0 || $name === '') {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Заполните обязательные поля']);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'Заполните обязательные поля']));
}

if ($perPage < 0) {
    $perPage = 0;
}

if ($key !== '' && !Utils::isUrlSafe($key)) {
    $message = 'Ключ может содержать только латинские буквы, цифры, дефис и подчёркивание.';
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $message]);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => $message]));
}

if ($key !== '') {
    $existing = DB::fetchOne(
        'SELECT id FROM infoblocks WHERE section_id = :section_id AND `key` = :key LIMIT 1',
        ['section_id' => $sectionId, 'key' => $key]
    );
    if ($existing !== null && (int) $existing['id'] !== $id) {
        $message = 'Инфоблок с таким ключом уже существует в разделе.';
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => $message]);
        }
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => $message]));
    }
}

$extra = [
    'before_html' => $beforeHtml,
    'after_html' => $afterHtml,
    'before_image' => $beforeImage,
    'after_image' => $afterImage,
];

$infoblockRepo->update($id, [
    'key' => $key,
    'name' => $name,
    'view_template' => $viewTemplate !== '' ? $viewTemplate : 'list',
    'per_page' => $perPage,
    'extra' => $extra,
    'sort' => $sort,
    'is_enabled' => $isEnabled,
]);

if ($user) {
    AdminLog::log($user['id'], 'infoblock_update', 'infoblock', $id, [
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
        'message' => 'Инфоблок обновлен',
        'refresh' => ['#sidebarTree', '#contentPane'],
    ]);
}
redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks']));
