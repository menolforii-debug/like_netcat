<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$layoutKey = isset($_POST['layout_key']) ? trim((string) $_POST['layout_key']) : '';

if ($layoutKey === '' || !layoutKeyIsValid($layoutKey)) {
    redirectTo(buildAdminUrl([
        'action' => 'layouts',
        'error' => 'Некорректный ключ макета',
    ]));
}

// системные макеты защищаем (как и UI)
if (in_array($layoutKey, ['default', 'home'], true)) {
    redirectTo(buildAdminUrl([
        'action' => 'layouts',
        'layout' => $layoutKey,
        'error' => 'Системный макет нельзя удалить',
    ]));
}

if (!LayoutCatalog::layoutExists($layoutKey)) {
    redirectTo(buildAdminUrl([
        'action' => 'layouts',
        'error' => 'Макет не найден',
    ]));
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
    // безопасность: удаляем только внутри templates/layouts/
    if (!str_starts_with(rtrim($real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $templatesDir)) {
        redirectTo(buildAdminUrl([
            'action' => 'layouts',
            'layout' => $layoutKey,
            'error' => 'Запрещено удалять файлы вне директории макетов',
        ]));
    }
    @unlink($real);
}

redirectTo(buildAdminUrl([
    'action' => 'layouts',
]));
