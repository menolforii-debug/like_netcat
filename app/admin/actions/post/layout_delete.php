<?php

$layoutKey = isset($_POST['layout_key']) ? trim((string) $_POST['layout_key']) : '';

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

if (in_array($layoutKey, ['default', 'home'], true)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Системные макеты нельзя удалить']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey, 'error' => 'Системные макеты нельзя удалить']));
}

if (!Layout::layoutExists($layoutKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Макет не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'error' => 'Макет не найден']));
}

$path = layoutTemplatesDir() . '/' . $layoutKey . '.php';
if (!is_file($path) || !@unlink($path)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Не удалось удалить макет']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey, 'error' => 'Не удалось удалить макет']));
}

$fieldsPath = layoutFieldsPath($layoutKey);
if (is_file($fieldsPath)) {
    @unlink($fieldsPath);
}

if (isAjaxRequest()) {
    jsonResponse(['ok' => true, 'notice' => 'Макет удален']);
}
redirectTo(buildAdminUrl(['action' => 'layouts', 'notice' => 'Макет удален']));
