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
        jsonResponse(['ok' => false, 'error' => 'Некорректный ключ макета. Разрешены A-Za-z0-9_-']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => '_new']));
}

if (LayoutCatalog::layoutExists($layoutKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Макет с таким ключом уже существует']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => '_new']));
}

$error = null;
if (!writeLayoutTemplate($layoutKey, $layoutTpl, $error)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => ($error ?: 'Не удалось сохранить макет')]);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => '_new']));
}

// nav.php — опционален
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
    jsonResponse([
        'ok' => true,
        'message' => 'Макет создан',
        'focus' => ['layout' => $layoutKey, 'tab' => 'layout'],
        'refresh' => ['#layoutsSidebarBlock', '#layoutsContentBlock'],
    ]);
}
redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey, 'tab' => 'layout']));
