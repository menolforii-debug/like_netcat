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

if (!LayoutCatalog::layoutExists($layoutKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Макет не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'error' => 'Макет не найден']));
}

$path = layoutTemplatesDir() . '/' . $layoutKey . '.php';
$navPath = layoutNavTemplatesDir() . '/' . $layoutKey . '.nav.php';
if (!is_file($path) || !@unlink($path)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Не удалось удалить макет']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey, 'error' => 'Не удалось удалить макет']));
}
if (is_file($navPath)) {
    @unlink($navPath);
}

if (isAjaxRequest()) {
    jsonResponse(['ok' => true, 'message' => 'Макет удален']);
}
redirectTo(buildAdminUrl(['action' => 'layouts']));
