<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$field = $visualFieldRepo->findById($id);
if ($field === null) {
    redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual', 'error' => 'Поле не найдено']));
}

$label = isset($_POST['label']) ? trim((string) $_POST['label']) : '';
$type = isset($_POST['type']) ? trim((string) $_POST['type']) : 'text';
$sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;

if ($label === '') {
    redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual', 'error' => 'Название поля обязательно']));
}

$allowedTypes = ['text', 'textarea', 'number', 'checkbox', 'select', 'color', 'file'];
if (!in_array($type, $allowedTypes, true)) {
    // Если тип неизвестен, сохраняем безопасный дефолт.
    $type = 'text';
}

$options = [];
if ($type === 'select') {
    $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
}
$visualFieldRepo->update($id, $label, $type, $options, $sort);

redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual']));
