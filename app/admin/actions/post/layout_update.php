<?php

$layoutKey = isset($_POST['layout_key']) ? trim((string) $_POST['layout_key']) : '';
$layoutTpl = isset($_POST['layout_tpl']) ? (string) $_POST['layout_tpl'] : '';
$layoutNavTpl = isset($_POST['layout_nav_tpl']) ? (string) $_POST['layout_nav_tpl'] : '';

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

if (!writeLayoutTemplate($layoutKey, $layoutTpl, $error)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $error ?? 'Не удалось сохранить макет']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey, 'error' => $error ?? 'Не удалось сохранить макет']));
}

if (!writeLayoutNavTemplate($layoutKey, $layoutNavTpl, $error)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $error ?? 'Не удалось сохранить шаблон навигации']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey, 'error' => $error ?? 'Не удалось сохранить шаблон навигации']));
}

if (isAjaxRequest()) {
    jsonResponse(['ok' => true]);
}
redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey]));
