<?php

if (!Auth::isAdmin()) {
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl(['action' => 'layouts']));
}

$layoutKey = isset($_POST['layout_key']) ? trim((string) $_POST['layout_key']) : '';
$layoutTpl = isset($_POST['layout_tpl']) ? (string) $_POST['layout_tpl'] : '';
$layoutNavTpl = isset($_POST['layout_nav_tpl']) ? (string) $_POST['layout_nav_tpl'] : '';

if ($layoutKey === '' || !layoutKeyIsValid($layoutKey)) {
    adminFlashSet('danger', 'Некорректный ключ макета. Разрешены A-Za-z0-9_-');
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => '_new']));
}

if (Layout::layoutExists($layoutKey)) {
    adminFlashSet('danger', 'Макет с таким ключом уже существует');
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => '_new']));
}

$error = null;
if (!writeLayoutTemplate($layoutKey, $layoutTpl, $error)) {
    adminFlashSet('danger', $error ?: 'Не удалось сохранить макет');
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
        adminFlashSet('danger', $navError ?: 'Не удалось сохранить шаблон навигации');
        redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey]));
    }
}

adminFlashSet('success', 'Макет создан');
redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey, 'tab' => 'layout']));
