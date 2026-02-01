<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    // fallback без сообщений
    redirectTo(buildAdminUrl([]));
}

$keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$fieldsInput = isset($_POST['fields']) && is_array($_POST['fields']) ? $_POST['fields'] : [];
$fieldsJson = isset($_POST['fields_json']) ? (string) $_POST['fields_json'] : '';

if ($keyword === '' || $name === '') {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Заполните ключ и название']);
    }
    redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Заполните ключ и название']));
}

if (!preg_match('/^[A-Za-z0-9_-]+$/', $keyword)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Ключ компонента должен быть URL-безопасным']);
    }
    redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Ключ компонента должен быть URL-безопасным']));
}

$fieldsInput = normalizeComponentFieldsInput($fieldsInput);
if (empty($fieldsInput) && $fieldsJson !== '') {
    $decoded = json_decode($fieldsJson, true);
    if (!is_array($decoded)) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Поля должны быть корректным JSON']);
        }
        redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Поля должны быть корректным JSON']));
    }
    $fieldsInput = normalizeComponentFieldsInput($decoded['fields'] ?? $decoded);
}

$fields = [];
$fieldNames = [];
foreach ($fieldsInput as $row) {
    if (!is_array($row)) {
        continue;
    }
    if (!empty($row['delete'])) {
        continue;
    }
    $fieldName = isset($row['name']) ? trim((string) $row['name']) : '';
    if ($fieldName === '') {
        continue;
    }
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $fieldName)) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Имя поля должно быть URL-безопасным']);
        }
        redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Имя поля должно быть URL-безопасным']));
    }
    if (isset($fieldNames[$fieldName])) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Имя поля должно быть уникальным']);
        }
        redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Имя поля должно быть уникальным']));
    }
    $fieldNames[$fieldName] = true;
    $label = isset($row['label']) ? trim((string) $row['label']) : $fieldName;
    $type = isset($row['type']) ? trim((string) $row['type']) : 'text';
    $allowedTypes = ['text', 'textarea', 'number', 'date', 'checkbox', 'select', 'file'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'text';
    }
    $required = !empty($row['required']);
    $options = [];
    if ($type === 'select' && isset($row['options']) && is_array($row['options'])) {
        foreach ($row['options'] as $option) {
            if (!is_array($option)) {
                continue;
            }
            if (!empty($option['delete'])) {
                continue;
            }
            $optKey = isset($option['key']) ? trim((string) $option['key']) : '';
            $optLabel = isset($option['label']) ? trim((string) $option['label']) : '';
            if ($optKey === '' || $optLabel === '') {
                continue;
            }
            $options[$optKey] = $optLabel;
        }
    }
    $fields[] = [
        'name' => $fieldName,
        'label' => $label,
        'type' => $type,
        'required' => $required,
        'options' => $options,
    ];
}

$views = ['list'];

$existing = $componentRepo->findByKeyword($keyword);
if ($existing !== null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент с таким ключом уже существует']);
    }
    redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Компонент с таким ключом уже существует']));
}

$componentId = $componentRepo->create($keyword, $name, $fields, $views);

if ($user) {
    AdminLog::log($user['id'], 'component_create', 'component', $componentId, [
        'keyword' => $keyword,
        'name' => $name,
    ]);
}

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'message' => 'Компонент создан',
        'refresh' => ['#componentsSidebar', '#componentsContent'],
        'focus' => ['component_id' => $componentId],
    ]);
}
redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'general']));
