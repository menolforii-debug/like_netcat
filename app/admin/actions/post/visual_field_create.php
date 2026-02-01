<?php

$isAjax = isAjaxRequest();
if (!Auth::isAdmin()) {
    if ($isAjax) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    // fallback без сообщений
    redirectTo(buildAdminUrl([]));
}

$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$label = isset($_POST['label']) ? trim((string) $_POST['label']) : '';
$type = isset($_POST['type']) ? trim((string) $_POST['type']) : 'text';
$sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;

if ($name === '' || $label === '') {
    if ($isAjax) {
        jsonResponse(['ok' => false, 'error' => 'Заполните ключ и название поля']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual', 'error' => 'Заполните ключ и название поля']));
}

if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
    if ($isAjax) {
        jsonResponse(['ok' => false, 'error' => 'Ключ поля должен быть URL-безопасным']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual', 'error' => 'Ключ поля должен быть URL-безопасным']));
}

$allowedTypes = ['text', 'textarea', 'number', 'checkbox', 'select', 'color', 'file'];
if (!in_array($type, $allowedTypes, true)) {
    // Если тип неизвестен, сохраняем безопасный дефолт.
    $type = 'text';
}

if ($visualFieldRepo->findByName($name) !== null) {
    if ($isAjax) {
        jsonResponse(['ok' => false, 'error' => 'Поле с таким ключом уже существует']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual', 'error' => 'Поле с таким ключом уже существует']));
}

$options = [];
$visualFieldRepo->create($name, $label, $type, $options, $sort);

if ($isAjax) {
    jsonResponse([
        'ok' => true,
        'message' => 'Поле создано',
        'refresh' => ['#visualFieldsBlock'],
    ]);
}
redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual']));
