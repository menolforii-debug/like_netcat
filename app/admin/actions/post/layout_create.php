<?php

$layoutKey = isset($_POST['layout_key']) ? trim((string) $_POST['layout_key']) : '';
$layoutTpl = isset($_POST['layout_tpl']) ? (string) $_POST['layout_tpl'] : '';
$layoutNavTpl = isset($_POST['layout_nav_tpl']) ? (string) $_POST['layout_nav_tpl'] : '';

if ($layoutKey === '') {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Введите ключ макета']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => '_new', 'error' => 'Введите ключ макета']));
}

if (!layoutKeyIsValid($layoutKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Ключ макета должен быть URL-безопасным']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => '_new', 'error' => 'Ключ макета должен быть URL-безопасным']));
}

if (LayoutCatalog::layoutExists($layoutKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Макет с таким ключом уже существует']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => '_new', 'error' => 'Макет с таким ключом уже существует']));
}

if (!writeLayoutTemplate($layoutKey, $layoutTpl, $error)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $error ?? 'Не удалось сохранить макет']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => '_new', 'error' => $error ?? 'Не удалось сохранить макет']));
}

if (!writeLayoutNavTemplate($layoutKey, $layoutNavTpl, $error)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $error ?? 'Не удалось сохранить шаблон навигации']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => '_new', 'error' => $error ?? 'Не удалось сохранить шаблон навигации']));
}

if (isAjaxRequest()) {
    jsonResponse(['ok' => true, 'message' => 'Макет создан']);
}
redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey]));
