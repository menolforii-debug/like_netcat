<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$fieldsInput = isset($_POST['fields']) && is_array($_POST['fields']) ? $_POST['fields'] : [];
$fieldsJson = isset($_POST['fields_json']) ? (string) $_POST['fields_json'] : '';

if ($componentId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$keyword = (string) ($component['keyword'] ?? '');
if ($keyword === '' || $name === '') {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Заполните ключ и название']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'error' => 'Заполните ключ и название']));
}

$fieldsInput = normalizeComponentFieldsInput($fieldsInput);
if (empty($fieldsInput) && $fieldsJson !== '') {
    $decoded = json_decode($fieldsJson, true);
    if (!is_array($decoded)) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Поля должны быть корректным JSON']);
        }
        redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'error' => 'Поля должны быть корректным JSON']));
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
        redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'fields', 'error' => 'Имя поля должно быть URL-безопасным']));
    }
    if (isset($fieldNames[$fieldName])) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Имя поля должно быть уникальным']);
        }
        redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'fields', 'error' => 'Имя поля должно быть уникальным']));
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

$views = [];
if (DB::hasTable('component_views')) {
    $viewRepo = new ComponentViewRepo();
    $views = $viewRepo->listNamesForComponent($componentId);
}

$existing = $componentRepo->findByKeyword($keyword);
if ($existing !== null && (int) $existing['id'] !== $componentId) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент с таким ключом уже существует']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'error' => 'Компонент с таким ключом уже существует']));
}

$componentRepo->update($componentId, $keyword, $name, $fields, $views);
syncComponentViewsJson($componentId);

if ($user) {
    AdminLog::log($user['id'], 'component_update', 'component', $componentId, [
        'keyword' => $keyword,
        'name' => $name,
    ]);
}

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'notice' => 'Компонент обновлен',
        'refresh' => ['#componentsSidebar', '#componentsContent'],
        'focus' => ['component_id' => $componentId],
    ]);
}
redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'general', 'notice' => 'Компонент обновлен']));
