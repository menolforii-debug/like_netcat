<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$layouts = Layout::listLayouts();
$layoutKey = isset($_GET['layout']) ? trim((string) $_GET['layout']) : '';

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
\$section = \$ctx['section'] ?? null;

Layout::renderDocumentStart(\$title, \$meta);
Layout::renderNavbar((string) (\$site['title'] ?? 'CMS'), [
    ['label' => 'Админ', 'href' => '/admin.php'],
]);

echo '<main class="container py-4">';
\$body();
echo '</main>';

Layout::renderDocumentEnd();
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

if ($layoutKey === '_new') {
    echo '<ul class="nav nav-tabs mb-3">';
    echo '<li class="nav-item"><span class="nav-link active">Новый макет</span></li>';
    echo '</ul>';
    echo '<form method="post" action="/admin.php?action=layout_create">';
    echo csrfTokenField();
    echo '<div class="mb-3"><label class="form-label">Ключ макета</label><input class="form-control" name="layout_key" required></div>';
    echo '<div class="mb-3"><label class="form-label">Шаблон макета</label><textarea class="form-control font-monospace code-editor" name="layout_tpl" rows="14">' . renderTextareaValue(defaultLayoutTemplate()) . '</textarea></div>';
    echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
    echo '</form>';
} elseif ($layoutKey !== '') {
    $content = readLayoutTemplate($layoutKey);
    echo '<ul class="nav nav-tabs mb-3">';
    echo '<li class="nav-item"><span class="nav-link active">Редактирование макета</span></li>';
    echo '</ul>';
    if ($content === null) {
        echo '<div class="text-muted">Макет не найден.</div>';
    } else {
        echo '<form method="post" action="/admin.php?action=layout_update">';
        echo csrfTokenField();
        echo '<div class="mb-3"><label class="form-label">Ключ макета</label><input class="form-control" name="layout_key" value="' . htmlspecialchars($layoutKey, ENT_QUOTES, 'UTF-8') . '" readonly></div>';
        echo '<div class="mb-3"><label class="form-label">Шаблон макета</label><textarea class="form-control font-monospace code-editor" name="layout_tpl" rows="14">' . renderTextareaValue($content) . '</textarea></div>';
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

echo '</div></div>';
AdminLayout::closeContent();

AdminLayout::renderFooter();
