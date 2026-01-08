<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$layouts = Layout::listLayouts();
$layoutKey = isset($_GET['layout']) ? trim((string) $_GET['layout']) : '';
$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'layout';
if (!in_array($tab, ['layout', 'visual'], true)) {
    $tab = 'layout';
}

if ($layoutKey !== '' && $layoutKey !== '_new' && !in_array($layoutKey, $layouts, true)) {
    $layoutKey = '';
}

function renderTextareaValue($value): string
{
    $s = (string) $value;
    $s = preg_replace('~</textarea~i', '&lt;/textarea', $s);
    return $s ?? '';
}

function defaultLayoutTemplate(): string
{
    return <<<PHP
<?php
/** @var array \$ctx */
/** @var callable \$body */

\$title = (string) (\$ctx['title'] ?? '');
\$meta = \$ctx['meta'] ?? [];
\$site = \$ctx['site'] ?? [];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php Layout::renderCss(); ?>
    <title><?= htmlspecialchars(\$title, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if (!empty(\$meta['description'])): ?>
        <meta name="description" content="<?= htmlspecialchars((string) \$meta['description'], ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (!empty(\$meta['keywords'])): ?>
        <meta name="keywords" content="<?= htmlspecialchars((string) \$meta['keywords'], ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
</head>
<body class="bg-light">
<div class="page-wrapper d-flex flex-column min-vh-100">
    <div class="content-wrapper flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand fw-semibold" href="/"><?= htmlspecialchars((string) (\$site['title'] ?? 'CMS'), ENT_QUOTES, 'UTF-8') ?></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="/admin.php">Админ</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <main class="container py-4">
            <?php \$body(); ?>
        </main>
    </div>
</div>
<?php Layout::renderJs(); ?>
</body>
</html>
PHP;
}

function defaultLayoutNavTemplate(): string
{
    return <<<PHP
<?php
// Здесь можно описать функции построения меню или другие helper-функции для макета.
PHP;
}

AdminLayout::renderHeader('Макеты дизайна');
renderAlert($notice, 'success');
renderAlert($errorMessage, 'error');

AdminLayout::openSidebar();

echo '<div class="card shadow-sm border-0">';
echo '<div class="card-body p-3">';
echo '<div class="d-flex align-items-center justify-content-between mb-2">';
echo '<div class="fw-semibold">Макеты дизайна</div>';
echo '<a class="btn btn-icon-square btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'layouts', 'layout' => '_new']), ENT_QUOTES, 'UTF-8') . '" title="Добавить макет" aria-label="Добавить макет">+</a>';
echo '</div>';

if (empty($layouts)) {
    echo '<div class="text-muted">Макеты пока не созданы.</div>';
    echo '</div></div>';
    AdminLayout::closeSidebar();

    AdminLayout::openContent();
    echo '<div class="card shadow-sm"><div class="card-body">';
    echo '<div class="text-muted">Создайте первый макет дизайна.</div>';
    echo '</div></div>';
    AdminLayout::closeContent();
    AdminLayout::renderFooter();
    return;
}

echo '<nav class="nav-deep nav-deep-sm nav-deep-light">';
echo '<ul class="nav flex-column">';
foreach ($layouts as $layout) {
    $isActive = $layoutKey === $layout;
    $link = buildAdminUrl(['action' => 'layouts', 'layout' => $layout]);
    $activeClass = $isActive ? ' fw-bold' : '';
    echo '<li class="nav-item">';
    echo '<a class="nav-link text-decoration-none text-truncate' . $activeClass . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">';
    echo htmlspecialchars($layout, ENT_QUOTES, 'UTF-8');
    echo '</a>';
    echo '</li>';
}
echo '</ul>';
echo '</nav>';
echo '</div>';
echo '</div>';

AdminLayout::closeSidebar();

AdminLayout::openContent();
echo '<div class="card shadow-sm">';
echo '<div class="card-body">';

echo '<ul class="nav nav-tabs mb-3">';
$layoutTabLabel = $layoutKey === '_new' ? 'Новый макет' : 'Редактирование макета';
$layoutTabClass = $tab === 'layout' ? ' active' : '';
$visualTabClass = $tab === 'visual' ? ' active' : '';
echo '<li class="nav-item"><a class="nav-link' . $layoutTabClass . '" href="' . htmlspecialchars(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey !== '' ? $layoutKey : null, 'tab' => 'layout']), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($layoutTabLabel, ENT_QUOTES, 'UTF-8') . '</a></li>';
echo '<li class="nav-item"><a class="nav-link' . $visualTabClass . '" href="' . htmlspecialchars(buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey !== '' ? $layoutKey : null, 'tab' => 'visual']), ENT_QUOTES, 'UTF-8') . '">Визуальные настройки</a></li>';
echo '</ul>';

if ($tab === 'visual') {
    $visualFields = $visualFieldRepo->listAll();
    echo '<div class="mb-4">';
    echo '<div class="d-flex align-items-center justify-content-between mb-3">';
    echo '<h2 class="h6 mb-0">Поля визуальных настроек</h2>';
    echo '<button class="btn btn-sm btn-outline-primary" data-modal-url="' . htmlspecialchars(buildAdminUrl(['action' => 'visual_field_form']), ENT_QUOTES, 'UTF-8') . '">Добавить поле</button>';
    echo '</div>';
    if (empty($visualFields)) {
        echo '<div class="alert alert-light border">Поля пока не созданы.</div>';
    } else {
        foreach ($visualFields as $field) {
            echo '<div class="border rounded p-3 mb-3">';
            echo '<div class="d-flex align-items-start justify-content-between gap-3">';
            echo '<div>';
            echo '<div class="fw-semibold">' . htmlspecialchars((string) $field['label'], ENT_QUOTES, 'UTF-8') . '</div>';
            echo '<div class="text-muted small">Ключ: ' . htmlspecialchars((string) $field['name'], ENT_QUOTES, 'UTF-8') . '</div>';
            echo '<div class="text-muted small">Тип: ' . htmlspecialchars((string) $field['type'], ENT_QUOTES, 'UTF-8') . '</div>';
            echo '<div class="text-muted small">Сортировка: ' . (int) ($field['sort'] ?? 0) . '</div>';
            echo '</div>';
            echo '<div class="d-flex gap-2">';
            echo '<button class="btn btn-sm btn-outline-primary" data-modal-url="' . htmlspecialchars(buildAdminUrl(['action' => 'visual_field_form', 'id' => (int) $field['id']]), ENT_QUOTES, 'UTF-8') . '">Редактировать</button>';
            echo '<form method="post" action="/admin.php?action=visual_field_delete" onsubmit="return confirm(\'Удалить поле?\')">';
            echo csrfTokenField();
            echo '<input type="hidden" name="id" value="' . (int) $field['id'] . '">';
            echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    }
    echo '</div>';
} else {
    if ($layoutKey === '_new') {
        echo '<form method="post" action="/admin.php?action=layout_create">';
        echo csrfTokenField();
        echo '<div class="mb-3"><label class="form-label">Ключ макета</label><input class="form-control" name="layout_key" required></div>';
        echo '<div class="mb-3"><label class="form-label">Шаблон макета</label><textarea class="form-control font-monospace code-editor" name="layout_tpl" rows="16">' . renderTextareaValue(defaultLayoutTemplate()) . '</textarea></div>';
        echo '<div class="mb-3"><label class="form-label">Шаблоны вывода навигации</label><textarea class="form-control font-monospace code-editor" name="layout_nav_tpl" rows="10">' . renderTextareaValue(defaultLayoutNavTemplate()) . '</textarea></div>';
        echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
        echo '</form>';
    } elseif ($layoutKey !== '') {
        $content = readLayoutTemplate($layoutKey);
        $navContent = readLayoutNavTemplate($layoutKey);
        if ($content === null) {
            echo '<div class="text-muted">Макет не найден.</div>';
        } else {
            echo '<form method="post" action="/admin.php?action=layout_update">';
            echo csrfTokenField();
            echo '<div class="mb-3"><label class="form-label">Ключ макета</label><input class="form-control" name="layout_key" value="' . htmlspecialchars($layoutKey, ENT_QUOTES, 'UTF-8') . '" readonly></div>';
            echo '<div class="mb-3"><label class="form-label">Шаблон макета</label><textarea class="form-control font-monospace code-editor" name="layout_tpl" rows="16">' . renderTextareaValue($content) . '</textarea></div>';
            echo '<div class="mb-3"><label class="form-label">Шаблоны вывода навигации</label><textarea class="form-control font-monospace code-editor" name="layout_nav_tpl" rows="10">' . renderTextareaValue($navContent ?? '') . '</textarea></div>';
            echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
            echo '</form>';
            if (!in_array($layoutKey, ['default', 'home'], true)) {
                echo '<form class="mt-2" method="post" action="/admin.php?action=layout_delete" onsubmit="return confirm(\'Удалить макет?\')">';
                echo csrfTokenField();
                echo '<input type="hidden" name="layout_key" value="' . htmlspecialchars($layoutKey, ENT_QUOTES, 'UTF-8') . '">';
                echo '<button class="btn btn-outline-danger" type="submit">Удалить</button>';
                echo '</form>';
            } else {
                echo '<div class="text-muted small mt-2">Системные макеты нельзя удалить.</div>';
            }
        }
    } else {
        echo '<div class="text-muted">Выберите макет для редактирования.</div>';
    }
}

echo '</div></div>';
AdminLayout::closeContent();

AdminLayout::renderFooter();
