<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$layoutKey = isset($_POST['layout_key']) ? trim((string) $_POST['layout_key']) : '';
$layoutTpl = isset($_POST['layout_tpl']) ? (string) $_POST['layout_tpl'] : '';
$layoutNavTpl = isset($_POST['layout_nav_tpl']) ? (string) $_POST['layout_nav_tpl'] : '';

if ($layoutKey === '' || !layoutKeyIsValid($layoutKey)) {
    redirectTo(buildAdminUrl([
        'action' => 'layouts',
        'layout' => '_new',
        'error' => 'Некорректный ключ макета. Разрешены A-Za-z0-9_-',
    ]));
}

if (LayoutCatalog::layoutExists($layoutKey)) {
    redirectTo(buildAdminUrl([
        'action' => 'layouts',
        'layout' => '_new',
        'error' => 'Макет с таким ключом уже существует',
    ]));
}

if (trim($layoutTpl) === '') {
    redirectTo(buildAdminUrl([
        'action' => 'layouts',
        'layout' => '_new',
        'error' => 'Шаблон макета не может быть пустым',
    ]));
}

$error = null;
if (!writeLayoutTemplate($layoutKey, $layoutTpl, $error)) {
    redirectTo(buildAdminUrl([
        'action' => 'layouts',
        'layout' => '_new',
        'error' => $error ?: 'Не удалось сохранить макет',
    ]));
}

// nav.php — опционален: пустой => не создаём/удаляем, непустой => пишем
$navPath = layoutNavTemplatesDir() . '/' . $layoutKey . '.nav.php';
if (trim($layoutNavTpl) === '') {
    if (is_file($navPath)) {
        @unlink($navPath);
    }
} else {
    $navError = null;
    if (!writeLayoutNavTemplate($layoutKey, $layoutNavTpl, $navError)) {
        redirectTo(buildAdminUrl([
            'action' => 'layouts',
            'layout' => $layoutKey,
            'error' => $navError ?: 'Не удалось сохранить шаблон навигации',
        ]));
    }
}

redirectTo(buildAdminUrl([
    'action' => 'layouts',
    'layout' => $layoutKey,
]));
