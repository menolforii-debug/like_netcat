<?php

if (!Auth::isAdmin()) {
    adminFlashSet('error', 'Недостаточно прав');
    redirectTo(buildAdminUrl(['action' => 'layouts']));
}

$layoutKey = isset($_POST['layout_key']) ? trim((string) $_POST['layout_key']) : '';
$layoutTpl = isset($_POST['layout_tpl']) ? (string) $_POST['layout_tpl'] : '';
$layoutNavTpl = isset($_POST['layout_nav_tpl']) ? (string) $_POST['layout_nav_tpl'] : '';

if ($layoutKey === '' || !layoutKeyIsValid($layoutKey)) {
    adminFlashSet('error', 'Некорректный ключ макета. Разрешены A-Za-z0-9_-');
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => '_new']));
}

if (LayoutCatalog::layoutExists($layoutKey)) {
    adminFlashSet('error', 'Макет с таким ключом уже существует');
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => '_new']));
}

if (trim($layoutTpl) === '') {
    adminFlashSet('error', 'Шаблон макета не может быть пустым');
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => '_new']));
}

$error = null;
if (!writeLayoutTemplate($layoutKey, $layoutTpl, $error)) {
    adminFlashSet('error', $error ?: 'Не удалось сохранить макет');
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
        adminFlashSet('error', $navError ?: 'Не удалось сохранить шаблон навигации');
        redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey]));
    }
}

adminFlashSet('success', 'Макет создан');
redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey]));
