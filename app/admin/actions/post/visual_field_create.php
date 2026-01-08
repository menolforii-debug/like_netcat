<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$label = isset($_POST['label']) ? trim((string) $_POST['label']) : '';
$type = isset($_POST['type']) ? trim((string) $_POST['type']) : 'text';
$sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;

if ($name === '' || $label === '') {
    redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual', 'error' => 'Заполните ключ и название поля']));
}

if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
    redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual', 'error' => 'Ключ поля должен быть URL-безопасным']));
}

$allowedTypes = ['text', 'textarea', 'number', 'checkbox', 'select', 'color'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'text';
}

if ($visualFieldRepo->findByName($name) !== null) {
    redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual', 'error' => 'Поле с таким ключом уже существует']));
}

$options = [];
$visualFieldRepo->create($name, $label, $type, $options, $sort);

redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual', 'notice' => 'Поле создано']));
