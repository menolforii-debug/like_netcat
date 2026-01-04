<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$fieldsInput = isset($_POST['fields']) && is_array($_POST['fields']) ? $_POST['fields'] : [];

if ($keyword === '' || $name === '') {
    redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Заполните ключ и название']));
}

if (!preg_match('/^[A-Za-z0-9_-]+$/', $keyword)) {
    redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Ключ компонента должен быть URL-безопасным']));
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
        redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Имя поля должно быть URL-безопасным']));
    }
    if (isset($fieldNames[$fieldName])) {
        redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Имя поля должно быть уникальным']));
    }
    $fieldNames[$fieldName] = true;
    $label = isset($row['label']) ? trim((string) $row['label']) : $fieldName;
    $type = isset($row['type']) ? trim((string) $row['type']) : 'text';
    $allowedTypes = ['text', 'textarea', 'number', 'date', 'checkbox', 'select'];
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

$existing = $componentRepo->findByKeyword($keyword);
if ($existing !== null) {
    redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Компонент с таким ключом уже существует']));
}

$componentId = $componentRepo->create($keyword, $name, $fields, $views);

$viewRepo = new ComponentViewRepo();
$defaultListTpl = "<?php foreach (\$objects as \$obj): ?>\n<div><?= htmlspecialchars(\$obj['data']['title'] ?? 'Без заголовка', ENT_QUOTES, 'UTF-8') ?></div>\n<?php endforeach; ?>";
$defaultSingleTpl = "<?php if (!empty(\$object['data']['title'])): ?>\n<h1><?= htmlspecialchars(\$object['data']['title'], ENT_QUOTES, 'UTF-8') ?></h1>\n<?php endif; ?>";
$viewId = $viewRepo->create($componentId, 'list', $defaultListTpl, $defaultSingleTpl);
$error = null;
if (!writeComponentViewTemplate($keyword, 'list', $defaultListTpl, $defaultSingleTpl, $error)) {
    $viewRepo->delete($viewId);
    $componentRepo->delete($componentId);
    redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => $error ?? 'Не удалось создать шаблон']));
}
syncComponentViewsJson($componentId);

if ($user) {
    AdminLog::log($user['id'], 'component_create', 'component', $componentId, [
        'keyword' => $keyword,
        'name' => $name,
    ]);
}

redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'general', 'notice' => 'Компонент создан']));
