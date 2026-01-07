<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$site = $id > 0 ? $sectionRepo->findById($id) : null;
if ($id > 0 && ($site === null || $site['parent_id'] !== null)) {
    echo '<div class="text-danger">Сайт не найден.</div>';
    exit;
}

$extra = $site ? decodeExtra($site) : [];
$mirrorsText = isset($extra['site_mirrors']) && is_array($extra['site_mirrors']) ? implode("\n", $extra['site_mirrors']) : '';
$enabled = $site ? !empty($extra['site_enabled']) : true;
$offlineHtml = isset($extra['site_offline_html']) ? (string) $extra['site_offline_html'] : '';
$currentLayout = isset($extra['layout']) ? (string) $extra['layout'] : '';
$layouts = Layout::listLayouts();
if ($currentLayout !== '' && !in_array($currentLayout, $layouts, true)) {
    $currentLayout = '';
}
$isAjax = isAjaxRequest();

if (!$isAjax) {
    AdminLayout::renderHeader($site ? 'Редактировать сайт' : 'Новый сайт');
    renderAlert($notice, 'success');
    renderAlert($errorMessage, 'error');
    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
    echo '<h1 class="h4 mb-0">' . ($site ? 'Редактировать сайт' : 'Новый сайт') . '</h1>';
    echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(), ENT_QUOTES, 'UTF-8') . '">Назад</a>';
    echo '</div>';
    echo '<div class="card shadow-sm"><div class="card-body">';
}

if ($isAjax) {
    echo '<span data-modal-title="' . ($site ? 'Редактировать сайт' : 'Новый сайт') . '"></span>';
}
echo '<form method="post" action="/admin.php?action=' . ($site ? 'site_update' : 'site_create') . '"' . ($isAjax ? ' data-ajax="true"' : '') . '>';
echo csrfTokenField();
if ($site) {
    echo '<input type="hidden" name="id" value="' . (int) $site['id'] . '">';
}
echo '<div class="mb-3"><label class="form-label">Название сайта</label><input class="form-control" type="text" name="title" value="' . htmlspecialchars((string) ($site['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
echo '<div class="mb-3"><label class="form-label">Основной домен</label><input class="form-control" type="text" name="site_domain" value="' . htmlspecialchars((string) ($extra['site_domain'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<div class="mb-3"><label class="form-label">Зеркала домена (по одному в строке)</label><textarea class="form-control" name="site_mirrors" rows="3">' . htmlspecialchars($mirrorsText, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
$checked = $enabled ? ' checked' : '';
echo '<div class="mb-3 form-check">';
echo '<input class="form-check-input" type="checkbox" name="site_enabled" value="1"' . $checked . '>';
echo '<label class="form-check-label">Сайт включен</label>';
echo '</div>';
echo '<div class="mb-3"><label class="form-label">HTML для отключенного сайта</label><textarea class="form-control" name="site_offline_html" rows="4">' . htmlspecialchars($offlineHtml, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
echo '<div class="mb-3"><label class="form-label">Макет дизайна по умолчанию</label><select class="form-select" name="layout">';
echo '<option value="">По умолчанию</option>';
foreach ($layouts as $layout) {
    $selectedAttr = $currentLayout === $layout ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select><div class="form-text">Наследуется разделами, если у них не задан собственный макет.</div></div>';

$layoutFields = $currentLayout !== '' ? readLayoutFields($currentLayout) : [];
$layoutValues = [];
if (!empty($extra['layout_fields']) && is_array($extra['layout_fields']) && $currentLayout !== '') {
    $valuesForLayout = $extra['layout_fields'][$currentLayout] ?? null;
    if (is_array($valuesForLayout)) {
        $layoutValues = $valuesForLayout;
    }
}

if ($currentLayout !== '' && !empty($layoutFields)) {
    echo '<h2 class="h6 mt-4">Визуальные настройки</h2>';
    echo '<input type="hidden" name="layout_fields_key" value="' . htmlspecialchars($currentLayout, ENT_QUOTES, 'UTF-8') . '">';
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
