<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts']));
}

$layoutKey = isset($_POST['layout_key']) ? trim((string) $_POST['layout_key']) : '';
$layoutTpl = isset($_POST['layout_tpl']) ? (string) $_POST['layout_tpl'] : '';
$layoutNavTpl = isset($_POST['layout_nav_tpl']) ? (string) $_POST['layout_nav_tpl'] : '';

if ($layoutKey === '' || !layoutKeyIsValid($layoutKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Некорректный ключ макета']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts']));
}

if (!LayoutCatalog::layoutExists($layoutKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Макет не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts']));
}

$error = null;
if (!writeLayoutTemplate($layoutKey, $layoutTpl, $error)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => ($error ?: 'Не удалось сохранить макет')]);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey]));
}

$navPath = layoutNavTemplatesDir() . '/' . $layoutKey . '.nav.php';
if (trim($layoutNavTpl) === '') {
    if (is_file($navPath)) {
        @unlink($navPath);
    }
} else {
    $navError = null;
    if (!writeLayoutNavTemplate($layoutKey, $layoutNavTpl, $navError)) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => ($navError ?: 'Не удалось сохранить шаблон навигации')]);
        }
        redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey]));
    }
}

if (isAjaxRequest()) {
    jsonResponse(['ok' => true]);
}
redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey, 'tab' => 'layout']));
