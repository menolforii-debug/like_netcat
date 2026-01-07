<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$layouts = Layout::listLayouts();
$layoutKey = isset($_GET['layout']) ? trim((string) $_GET['layout']) : '';
$tab = isset($_GET['tab']) ? trim((string) $_GET['tab']) : 'edit';
if (!in_array($tab, ['edit', 'visual'], true)) {
    $tab = 'edit';
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

AdminLayout::renderHeader('Шаблоны дизайна (паблик)');
renderAlert($notice, 'success');
renderAlert($errorMessage, 'error');

AdminLayout::openSidebar();

echo '<div class="card shadow-sm border-0">';
echo '<div class="card-body p-3">';
echo '<div class="d-flex align-items-center justify-content-between mb-2">';
echo '<div class="fw-semibold">Шаблоны дизайна</div>';
echo '<a class="btn btn-icon-square btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'layouts', 'layout' => '_new']), ENT_QUOTES, 'UTF-8') . '" title="Добавить шаблон" aria-label="Добавить шаблон">+</a>';
echo '</div>';

if (empty($layouts)) {
    echo '<div class="text-muted">Шаблоны пока не созданы.</div>';
    echo '</div></div>';
    AdminLayout::closeSidebar();

    AdminLayout::openContent();
    echo '<div class="card shadow-sm"><div class="card-body">';
    echo '<div class="text-muted">Создайте первый шаблон дизайна.</div>';
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
    echo '<li class="nav-item"><span class="nav-link active">Новый шаблон</span></li>';
    echo '</ul>';
    echo '<form method="post" action="/admin.php?action=layout_create">';
    echo csrfTokenField();
    echo '<div class="mb-3"><label class="form-label">Ключ шаблона</label><input class="form-control" name="layout_key" required></div>';
    echo '<div class="mb-3"><label class="form-label">Шаблон дизайна</label><textarea class="form-control font-monospace code-editor" name="layout_tpl" rows="14">' . renderTextareaValue(defaultLayoutTemplate()) . '</textarea></div>';
    echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
    echo '</form>';
} elseif ($layoutKey !== '') {
    $content = readLayoutTemplate($layoutKey);
    $editLink = buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey, 'tab' => 'edit']);
    $visualLink = buildAdminUrl(['action' => 'layouts', 'layout' => $layoutKey, 'tab' => 'visual']);
    echo '<ul class="nav nav-tabs mb-3">';
    echo '<li class="nav-item"><a class="nav-link' . ($tab === 'edit' ? ' active' : '') . '" href="' . htmlspecialchars($editLink, ENT_QUOTES, 'UTF-8') . '">Редактирование макета</a></li>';
    echo '<li class="nav-item"><a class="nav-link' . ($tab === 'visual' ? ' active' : '') . '" href="' . htmlspecialchars($visualLink, ENT_QUOTES, 'UTF-8') . '">Визуальные настройки</a></li>';
    echo '</ul>';

    if ($content === null) {
        echo '<div class="text-muted">Шаблон не найден.</div>';
    } elseif ($tab === 'visual') {
        $fields = readLayoutFields($layoutKey);
        echo '<form method="post" action="/admin.php?action=layout_fields_update">';
        echo csrfTokenField();
        echo '<input type="hidden" name="layout_key" value="' . htmlspecialchars($layoutKey, ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm align-middle">';
        echo '<thead><tr><th>Имя</th><th>Подпись</th><th>Тип</th><th>Обязательное</th><th>Опции</th><th>Удалить</th></tr></thead><tbody>';

        foreach ($fields as $index => $field) {
            echo '<tr>';
            echo '<td><input class="form-control form-control-sm" name="fields[' . $index . '][name]" value="' . htmlspecialchars((string) $field['name'], ENT_QUOTES, 'UTF-8') . '"></td>';
            echo '<td><input class="form-control form-control-sm" name="fields[' . $index . '][label]" value="' . htmlspecialchars((string) $field['label'], ENT_QUOTES, 'UTF-8') . '"></td>';
            echo '<td><select class="form-select form-select-sm" name="fields[' . $index . '][type]">';
            foreach (['text', 'textarea', 'number', 'date', 'checkbox', 'select'] as $type) {
                $selected = $type === (string) $field['type'] ? ' selected' : '';
                echo '<option value="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            echo '</select></td>';
            $checked = !empty($field['required']) ? ' checked' : '';
            echo '<td><input class="form-check-input" type="checkbox" name="fields[' . $index . '][required]" value="1"' . $checked . '></td>';
            echo '<td>';
            if ((string) $field['type'] === 'select') {
                echo '<div class="d-flex flex-column gap-1">';
                $optIndex = 0;
                if (!empty($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $optKey => $optLabel) {
                        echo '<div class="input-group input-group-sm">';
                        echo '<input class="form-control" name="fields[' . $index . '][options][' . $optIndex . '][key]" value="' . htmlspecialchars((string) $optKey, ENT_QUOTES, 'UTF-8') . '" placeholder="Ключ">';
                        echo '<input class="form-control" name="fields[' . $index . '][options][' . $optIndex . '][label]" value="' . htmlspecialchars((string) $optLabel, ENT_QUOTES, 'UTF-8') . '" placeholder="Название">';
                        echo '<span class="input-group-text"><input type="checkbox" name="fields[' . $index . '][options][' . $optIndex . '][delete]" value="1"></span>';
                        echo '</div>';
                        $optIndex++;
                    }
                }
                echo '<div class="input-group input-group-sm">';
                echo '<input class="form-control" name="fields[' . $index . '][options][' . $optIndex . '][key]" placeholder="Ключ">';
                echo '<input class="form-control" name="fields[' . $index . '][options][' . $optIndex . '][label]" placeholder="Название">';
                echo '</div>';
                echo '</div>';
            } else {
                echo '<span class="text-muted small">—</span>';
            }
            echo '</td>';
            echo '<td><input class="form-check-input" type="checkbox" name="fields[' . $index . '][delete]" value="1"></td>';
            echo '</tr>';
        }

        $newIndex = count($fields);
        echo '<tr>';
        echo '<td><input class="form-control form-control-sm" name="fields[' . $newIndex . '][name]" placeholder="Новое поле"></td>';
        echo '<td><input class="form-control form-control-sm" name="fields[' . $newIndex . '][label]" placeholder="Подпись"></td>';
        echo '<td><select class="form-select form-select-sm" name="fields[' . $newIndex . '][type]">';
        foreach (['text', 'textarea', 'number', 'date', 'checkbox', 'select'] as $type) {
            echo '<option value="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        echo '</select></td>';
        echo '<td><input class="form-check-input" type="checkbox" name="fields[' . $newIndex . '][required]" value="1"></td>';
        echo '<td><span class="text-muted small">—</span></td>';
        echo '<td></td>';
        echo '</tr>';
        echo '</tbody></table></div>';
        echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
        echo '</form>';
    } else {
        echo '<form method="post" action="/admin.php?action=layout_update">';
        echo csrfTokenField();
        echo '<div class="mb-3"><label class="form-label">Ключ шаблона</label><input class="form-control" name="layout_key" value="' . htmlspecialchars($layoutKey, ENT_QUOTES, 'UTF-8') . '" readonly></div>';
        echo '<div class="mb-3"><label class="form-label">Шаблон дизайна</label><textarea class="form-control font-monospace code-editor" name="layout_tpl" rows="14">' . renderTextareaValue($content) . '</textarea></div>';
        echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
        echo '</form>';
        if (!in_array($layoutKey, ['default', 'home'], true)) {
            echo '<form class="mt-2" method="post" action="/admin.php?action=layout_delete" onsubmit="return confirm(\'Удалить шаблон?\')">';
            echo csrfTokenField();
            echo '<input type="hidden" name="layout_key" value="' . htmlspecialchars($layoutKey, ENT_QUOTES, 'UTF-8') . '">';
            echo '<button class="btn btn-outline-danger" type="submit">Удалить</button>';
            echo '</form>';
        } else {
            echo '<div class="text-muted small mt-2">Системные шаблоны нельзя удалить.</div>';
        }
    }
} else {
    echo '<div class="text-muted">Выберите шаблон для редактирования.</div>';
}

echo '</div></div>';
AdminLayout::closeContent();

AdminLayout::renderFooter();
