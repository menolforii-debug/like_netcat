<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts']));
}

$layoutKey = isset($_POST['layout_key']) ? trim((string) $_POST['layout_key']) : '';

if ($layoutKey === '' || !layoutKeyIsValid($layoutKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Некорректный ключ макета']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts']));
}

if (in_array($layoutKey, ['default', 'home'], true)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Системный макет нельзя удалить']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey]));
}

if (!LayoutCatalog::layoutExists($layoutKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Макет не найден']);
    }
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
        adminFlashSet('error', 'Запрещено удалять файлы вне директории макетов');
        redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey]));
    }
    @unlink($real);
}

redirectTo(buildAdminUrl(['action' => 'layouts']));
