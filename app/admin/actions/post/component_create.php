<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    // fallback без сообщений
    adminFlashSet('danger', 'Недостаточно прав');
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
    adminFlashSet('danger', 'Заполните ключ и название');
    redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Заполните ключ и название']));
}

if (!preg_match('/^[A-Za-z0-9_-]+$/', $keyword)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Ключ компонента должен быть URL-безопасным']);
    }
    adminFlashSet('danger', 'Ключ компонента должен быть URL-безопасным');
    redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Ключ компонента должен быть URL-безопасным']));
}

$fieldsInput = normalizeComponentFieldsInput($fieldsInput);
if (empty($fieldsInput) && $fieldsJson !== '') {
    $decoded = json_decode($fieldsJson, true);
    if (!is_array($decoded)) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Поля должны быть корректным JSON']);
        }
        adminFlashSet('danger', 'Поля должны быть корректным JSON');
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
        adminFlashSet('danger', 'Имя поля должно быть URL-безопасным');
        redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Имя поля должно быть URL-безопасным']));
    }
    if (isset($fieldNames[$fieldName])) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Имя поля должно быть уникальным']);
        }
        adminFlashSet('danger', 'Имя поля должно быть уникальным');
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
    adminFlashSet('danger', 'Компонент с таким ключом уже существует');
    redirectTo(buildAdminUrl(['action' => 'component_new', 'error' => 'Компонент с таким ключом уже существует']));
}

$componentId = $componentRepo->create($keyword, $name, $fields, $views);

$root = dirname(__DIR__, 4);
$baseDir = $root . '/templates/component';
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0775, true);
}
$baseReal = realpath($baseDir);
if ($baseReal !== false) {
    $baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $componentDir = $baseReal . $keyword . '/list';
    if (!is_dir($componentDir)) {
        mkdir($componentDir, 0775, true);
    }
    $componentDirReal = realpath($componentDir);
    if ($componentDirReal !== false && strpos($componentDirReal . DIRECTORY_SEPARATOR, $baseReal) === 0) {
        $defaultListContent = <<<'PHP'
<?php
/** @var array $items */
?>
<div class="component-list">
    <?php foreach ($items as $item): ?>
        <?php $title = (string) ($item['data']['title'] ?? ''); ?>
        <?php $text = (string) ($item['data']['text'] ?? ''); ?>
        <article class="component-item">
            <h3><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
            <?php if ($text !== ''): ?>
                <p><?php echo nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')); ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
PHP;

        $defaultSingleContent = <<<'PHP'
<?php
/** @var array|null $object */
if ($object === null) {
    return;
}
$title = (string) ($object['data']['title'] ?? '');
$text = (string) ($object['data']['text'] ?? '');
?>
<article class="component-single">
    <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if ($text !== ''): ?>
        <div class="content"><?php echo nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')); ?></div>
    <?php endif; ?>
</article>
PHP;

        $defaultSystemContent = <<<'PHP'
<?php
// Доступны переменные: $section, $site, $infoblock, $component, $isSingle
// Пример системных настроек:
// $query_order = 'a.created_at DESC';
// $query_limit = '10';
// $distinct = 'DISTINCT';
PHP;

        $defaultFiles = [
            'list.php' => $defaultListContent,
            'single.php' => $defaultSingleContent,
            'system.php' => $defaultSystemContent,
        ];

        foreach ($defaultFiles as $fileName => $content) {
            $filePath = $componentDirReal . '/' . $fileName;
            if (!is_file($filePath)) {
                file_put_contents($filePath, $content, LOCK_EX);
            }
        }
    }
}

if ($user) {
    AdminLog::log($user['id'], 'component_create', 'component', $componentId, [
        'keyword' => $keyword,
        'name' => $name,
    ]);
}

if (isAjaxRequest()) {
    adminOk('Компонент создан', ['component_id' => $componentId, 'tab' => 'general'], true, [
        'refresh' => ['#components_block'],
    ]);
}
adminFlashSet('success', 'Компонент создан');
redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'general']));
