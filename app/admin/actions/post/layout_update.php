<?php

if (!Auth::isAdmin()) {
    adminFlashSet('error', 'Недостаточно прав');
    redirectTo(buildAdminUrl(['action' => 'layouts']));
}

$layoutKey = isset($_POST['layout_key']) ? trim((string) $_POST['layout_key']) : '';
$layoutTpl = isset($_POST['layout_tpl']) ? (string) $_POST['layout_tpl'] : '';
$layoutNavTpl = isset($_POST['layout_nav_tpl']) ? (string) $_POST['layout_nav_tpl'] : '';

if ($layoutKey === '' || !layoutKeyIsValid($layoutKey)) {
    adminFlashSet('error', 'Некорректный ключ макета');
    redirectTo(buildAdminUrl(['action' => 'layouts']));
}

if (!LayoutCatalog::layoutExists($layoutKey)) {
    adminFlashSet('error', 'Макет не найден');
    redirectTo(buildAdminUrl(['action' => 'layouts']));
}

$error = null;
if (!writeLayoutTemplate($layoutKey, $layoutTpl, $error)) {
    adminFlashSet('error', $error ?: 'Не удалось сохранить макет');
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
        adminFlashSet('error', $navError ?: 'Не удалось сохранить шаблон навигации');
        redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey]));
    }
}

adminFlashSet('success', 'Изменения сохранены');
redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey]));
