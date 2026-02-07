<?php

if (!Auth::isAdmin()) {
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl(['action' => 'layouts']));
}

$layoutKey = isset($_POST['layout_key']) ? trim((string) $_POST['layout_key']) : '';

if ($layoutKey === '' || !layoutKeyIsValid($layoutKey)) {
    adminFlashSet('danger', 'Некорректный ключ макета');
    redirectTo(buildAdminUrl(['action' => 'layouts']));
}

if (in_array($layoutKey, ['default', 'home'], true)) {
    adminFlashSet('danger', 'Системный макет нельзя удалить');
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey]));
}

if (!Layout::layoutExists($layoutKey)) {
    adminFlashSet('danger', 'Макет не найден');
    redirectTo(buildAdminUrl(['action' => 'layouts']));
}

$templatesDir = realpath(layoutTemplatesDir());
if ($templatesDir === false) {
    $templatesDir = layoutTemplatesDir();
}
$templatesDir = rtrim($templatesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

$layoutPath = layoutTemplatesDir() . '/' . $layoutKey . '.php';
$navPath = layoutNavTemplatesDir() . '/' . $layoutKey . '.nav.php';

foreach ([$layoutPath, $navPath] as $path) {
    if (!is_file($path)) {
        continue;
    }
    $real = realpath($path);
    if ($real === false) {
        continue;
    }
    if (!str_starts_with(rtrim($real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $templatesDir)) {
        adminFlashSet('danger', 'Запрещено удалять файлы вне директории макетов');
        redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey]));
    }
    @unlink($real);
}

adminFlashSet('success', 'Макет удален');
redirectTo(buildAdminUrl(['action' => 'layouts']));
