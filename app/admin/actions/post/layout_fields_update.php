<?php

$layoutKey = isset($_POST['layout_key']) ? trim((string) $_POST['layout_key']) : '';
$fieldsInput = isset($_POST['fields']) && is_array($_POST['fields']) ? $_POST['fields'] : [];

if ($layoutKey === '') {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Макет не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'error' => 'Макет не найден']));
}

if (!layoutKeyIsValid($layoutKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Некорректный ключ макета']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'error' => 'Некорректный ключ макета']));
}

if (!Layout::layoutExists($layoutKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Макет не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'error' => 'Макет не найден']));
}

$fields = normalizeLayoutFieldsInput($fieldsInput);
if (!writeLayoutFields($layoutKey, $fields, $error)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $error ?? 'Не удалось сохранить поля макета']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey, 'tab' => 'visual', 'error' => $error ?? 'Не удалось сохранить поля макета']));
}

if (isAjaxRequest()) {
    jsonResponse(['ok' => true, 'notice' => 'Поля макета сохранены']);
}
redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey, 'tab' => 'visual', 'notice' => 'Поля макета сохранены']));
