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
        'error' => 'Некорректный ключ макета',
    ]));
}

if (!LayoutCatalog::layoutExists($layoutKey)) {
    redirectTo(buildAdminUrl([
        'action' => 'layouts',
        'error' => 'Макет не найден',
    ]));
}

if (trim($layoutTpl) === '') {
    redirectTo(buildAdminUrl([
        'action' => 'layouts',
        'layout' => $layoutKey,
        'error' => 'Шаблон макета не может быть пустым',
    ]));
}

$error = null;
if (!writeLayoutTemplate($layoutKey, $layoutTpl, $error)) {
    redirectTo(buildAdminUrl([
        'action' => 'layouts',
        'layout' => $layoutKey,
        'error' => $error ?: 'Не удалось сохранить макет',
    ]));
}

// nav.php — опционален: пустой => удалить, непустой => сохранить
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
