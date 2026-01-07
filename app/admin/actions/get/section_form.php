<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$parentId = isset($_GET['parent_id']) ? (int) $_GET['parent_id'] : 0;

$section = $id > 0 ? $sectionRepo->findById($id) : null;
if ($id > 0 && $section === null) {
    echo '<div class="text-danger">Раздел не найден.</div>';
    exit;
}

$siteId = 0;
if ($section !== null) {
    $siteId = (int) $section['site_id'];
} elseif ($parentId > 0) {
    $parent = $sectionRepo->findById($parentId);
    if ($parent === null) {
        echo '<div class="text-danger">Родительский раздел не найден.</div>';
        exit;
    }
    $siteId = (int) $parent['site_id'];
    $section = [
        'parent_id' => $parentId,
        'title' => '',
        'english_name' => '',
        'sort' => 0,
        'extra_json' => '{}',
    ];
}

if ($siteId <= 0) {
    echo '<div class="text-danger">Не удалось определить сайт.</div>';
    exit;
}

$site = $sectionRepo->findById($siteId);
$options = [];
if ($site !== null) {
    $options[] = $site;
    $options = array_merge($options, collectSections($sectionRepo, $siteId));
}

$extra = $section ? decodeExtra($section) : [];
$layouts = Layout::listLayouts();
$siteExtra = $site ? decodeExtra($site) : [];
$siteLayout = isset($siteExtra['layout']) ? (string) $siteExtra['layout'] : '';
$currentLayout = isset($extra['layout']) ? (string) $extra['layout'] : '';
if ($currentLayout !== '' && !in_array($currentLayout, $layouts, true)) {
    $currentLayout = '';
}
$layoutForFields = $currentLayout !== '' ? $currentLayout : $siteLayout;
$isAjax = isAjaxRequest();

$isSystemRoot = $section && $section['parent_id'] === null && in_array($section['english_name'] ?? '', ['index', '404'], true);

if (!$isAjax) {
    AdminLayout::renderHeader($id > 0 ? 'Редактировать раздел' : 'Новый раздел');
    renderAlert($notice, 'success');
    renderAlert($errorMessage, 'error');
    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
    echo '<h1 class="h4 mb-0">' . ($id > 0 ? 'Редактировать раздел' : 'Новый раздел') . '</h1>';
    echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $siteId]), ENT_QUOTES, 'UTF-8') . '">Назад</a>';
    echo '</div>';
    echo '<div class="card shadow-sm"><div class="card-body">';
}

if ($isAjax) {
    echo '<span data-modal-title="' . ($id > 0 ? 'Редактировать раздел' : 'Новый раздел') . '"></span>';
}
echo '<form method="post" action="/admin.php?action=' . ($id > 0 ? 'section_update' : 'section_create') . '"' . ($isAjax ? ' data-ajax="true"' : '') . '>';
echo csrfTokenField();
if ($id > 0) {
    echo '<input type="hidden" name="id" value="' . (int) $section['id'] . '">';
}
echo '<div class="mb-3"><label class="form-label">Название</label><input class="form-control" type="text" name="title" value="' . htmlspecialchars((string) ($section['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
$englishNameAttributes = $isSystemRoot ? ' disabled' : ' required';
$englishNameHint = $isSystemRoot ? '<div class="form-text">Системный раздел: English name фиксирован.</div>' : '';
echo '<div class="mb-3"><label class="form-label">English name (латиница)</label><input class="form-control" type="text" name="english_name" value="' . htmlspecialchars((string) ($section['english_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '"' . $englishNameAttributes . '>' . $englishNameHint . '</div>';
echo '<div class="mb-3"><label class="form-label">Родительский раздел</label><select class="form-select" name="parent_id" required>';
echo '<option value="">Выберите родителя</option>';
foreach ($options as $option) {
    if ($id > 0 && (int) $option['id'] === (int) $section['id']) {
        continue;
    }
    if ((int) $option['site_id'] !== $siteId) {
        continue;
    }
    $selectedAttr = (int) ($section['parent_id'] ?? 0) === (int) $option['id'] ? ' selected' : '';
    echo '<option value="' . (int) $option['id'] . '"' . $selectedAttr . '>' . htmlspecialchars((string) $option['title'], ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select></div>';
echo '<div class="mb-3"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="' . htmlspecialchars((string) ($section['sort'] ?? 0), ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<div class="mb-3"><label class="form-label">Макет дизайна</label><select class="form-select" name="layout">';
echo '<option value="">Наследовать макет сайта</option>';
foreach ($layouts as $layout) {
    $selectedAttr = $currentLayout === $layout ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select></div>';

$layoutFields = $layoutForFields !== '' ? readLayoutFields($layoutForFields) : [];
$layoutValues = [];
if (!empty($extra['layout_fields']) && is_array($extra['layout_fields']) && $layoutForFields !== '') {
    $valuesForLayout = $extra['layout_fields'][$layoutForFields] ?? null;
    if (is_array($valuesForLayout)) {
        $layoutValues = $valuesForLayout;
    }
}

if ($layoutForFields !== '' && !empty($layoutFields)) {
    echo '<h2 class="h6 mt-4">Визуальные настройки</h2>';
    echo '<input type="hidden" name="layout_fields_key" value="' . htmlspecialchars($layoutForFields, ENT_QUOTES, 'UTF-8') . '">';
    if ($currentLayout === '' && $siteLayout !== '') {
        echo '<div class="form-text mb-2">Используется макет сайта: ' . htmlspecialchars($siteLayout, ENT_QUOTES, 'UTF-8') . '.</div>';
    }
    foreach ($layoutFields as $field) {
        $name = (string) $field['name'];
        $label = (string) ($field['label'] ?? $name);
        $type = (string) ($field['type'] ?? 'text');
        $value = isset($layoutValues[$name]) ? (string) $layoutValues[$name] : '';
        echo '<div class="mb-3">';
        echo '<label class="form-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
        if ($type === 'textarea') {
            echo '<textarea class="form-control" name="layout_fields[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" rows="3">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</textarea>';
        } elseif ($type === 'select' && !empty($field['options']) && is_array($field['options'])) {
            echo '<select class="form-select" name="layout_fields[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']">';
            echo '<option value="">—</option>';
            foreach ($field['options'] as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $optKey = (string) ($option['key'] ?? '');
                $optLabel = (string) ($option['label'] ?? $optKey);
                if ($optKey === '') {
                    continue;
                }
                $selectedAttr = $optKey === $value ? ' selected' : '';
                echo '<option value="' . htmlspecialchars($optKey, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'checkbox') {
            echo '<select class="form-select" name="layout_fields[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']">';
            echo '<option value="">—</option>';
            $yesSelected = $value === '1' ? ' selected' : '';
            $noSelected = $value === '0' ? ' selected' : '';
            echo '<option value="1"' . $yesSelected . '>Да</option>';
            echo '<option value="0"' . $noSelected . '>Нет</option>';
            echo '</select>';
        } else {
            $inputType = $type === 'number' ? 'number' : ($type === 'date' ? 'date' : 'text');
            echo '<input class="form-control" type="' . htmlspecialchars($inputType, ENT_QUOTES, 'UTF-8') . '" name="layout_fields[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
        }
        echo '</div>';
    }
}
echo '<div class="d-flex justify-content-end gap-2">';
echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
echo '</div>';
echo '</form>';

if (!$isAjax) {
    echo '</div></div>';
    AdminLayout::renderFooter();
}
