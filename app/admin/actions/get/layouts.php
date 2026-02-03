<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl([]));
}

$layouts = LayoutCatalog::listLayouts();
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

function renderVisualFieldsContent(array $visualFields): void
{
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
            echo '<form method="post" action="/admin.php?action=visual_field_delete" data-confirm="Удалить поле?">';
            echo csrf_token_field();
            echo '<input type="hidden" name="id" value="' . (int) $field['id'] . '">';
            echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    }
    echo '</div>';
}

function renderLayoutsSidebarHtml(array $layouts, string $layoutKey, string $tab): void
{
    echo '<div class="card shadow-sm border-0">';
    echo '<div class="card-body p-3">';
    echo '<div class="d-flex align-items-center justify-content-between mb-2">';
    echo '<div class="fw-semibold">Макеты дизайна</div>';
    echo '<a class="btn btn-icon-square btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'layouts', 'layout' => '_new', 'tab' => $tab]), ENT_QUOTES, 'UTF-8') . '" title="Добавить макет" aria-label="Добавить макет">+</a>';
    echo '</div>';

    if (empty($layouts)) {
        echo '<div class="text-muted">Макеты пока не созданы.</div>';
    } elseif ($layoutKey !== '_new') {
        echo '<nav class="nav-deep nav-deep-sm nav-deep-light">';
        echo '<ul class="nav flex-column">';
        foreach ($layouts as $layout) {
            $isActive = $layoutKey === $layout;
            $link = buildAdminUrl(['action' => 'layouts', 'layout' => $layout, 'tab' => $tab]);
            $activeClass = $isActive ? ' fw-bold' : '';
            echo '<li class="nav-item">';
            echo '<a class="nav-link text-decoration-none text-truncate' . $activeClass . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">';
            echo htmlspecialchars($layout, ENT_QUOTES, 'UTF-8');
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</nav>';
    } else {
        echo '<div class="text-muted small">Создание нового макета.</div>';
    }
    echo '</div>';
    echo '</div>';
}

function renderLayoutsContentHtml(array $layouts, string $layoutKey, string $tab, VisualFieldRepo $visualFieldRepo): void
{
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
        echo '<div id="visualFieldsBlock">';
        renderVisualFieldsContent($visualFields);
        echo '</div>';
        echo '</div></div>';
        return;
    }

    if ($layoutKey === '_new') {
        $defaultLayoutTemplate = readDefaultLayoutTemplateFile() ?? '';
        $defaultLayoutNavTemplate = readDefaultLayoutNavTemplateFile() ?? '';
        echo '<form method="post" action="/admin.php?action=layout_create">';
        echo csrf_token_field();
        echo '<div class="mb-3"><label class="form-label">Ключ макета</label><input class="form-control" name="layout_key" required></div>';
        echo '<div class="mb-3 js-code-editor-wrapper">';
        echo '<label class="form-label">Шаблон макета</label>';
        echo '<textarea class="form-control font-monospace code-editor" name="layout_tpl" rows="16">' . renderTextareaValue($defaultLayoutTemplate) . '</textarea>';
        echo '<div class="mt-2 d-flex gap-2">';
        echo '<button class="btn btn-link p-0 link-dotted js-code-editor-expand" type="button">Развернуть</button>';
        echo '<button class="btn btn-link p-0 link-dotted js-code-editor-collapse d-none" type="button">Свернуть</button>';
        echo '</div>';
        echo '</div>';
        echo '<div class="mb-3 js-code-editor-wrapper">';
        echo '<label class="form-label">Шаблоны вывода навигации</label>';
        echo '<textarea class="form-control font-monospace code-editor" name="layout_nav_tpl" rows="10">' . renderTextareaValue($defaultLayoutNavTemplate) . '</textarea>';
        echo '<div class="mt-2 d-flex gap-2">';
        echo '<button class="btn btn-link p-0 link-dotted js-code-editor-expand" type="button">Развернуть</button>';
        echo '<button class="btn btn-link p-0 link-dotted js-code-editor-collapse d-none" type="button">Свернуть</button>';
        echo '</div>';
        echo '</div>';
        echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
        echo '</form>';
    } elseif ($layoutKey !== '') {
        $content = readLayoutTemplate($layoutKey);
        $navContent = readLayoutNavTemplate($layoutKey);
        if ($content === null) {
            echo '<div class="text-muted">Макет не найден.</div>';
        } else {
            echo '<form method="post" action="/admin.php?action=layout_update">';
            echo csrf_token_field();
            echo '<div class="mb-3"><label class="form-label">Ключ макета</label><input class="form-control" name="layout_key" value="' . htmlspecialchars($layoutKey, ENT_QUOTES, 'UTF-8') . '" readonly></div>';
            echo '<div class="mb-3 js-code-editor-wrapper">';
            echo '<label class="form-label">Шаблон макета</label>';
            echo '<textarea class="form-control font-monospace code-editor" name="layout_tpl" rows="16">' . renderTextareaValue($content) . '</textarea>';
            echo '<div class="mt-2 d-flex gap-2">';
            echo '<button class="btn btn-link p-0 link-dotted js-code-editor-expand" type="button">Развернуть</button>';
            echo '<button class="btn btn-link p-0 link-dotted js-code-editor-collapse d-none" type="button">Свернуть</button>';
            echo '</div>';
            echo '</div>';
            echo '<div class="mb-3 js-code-editor-wrapper">';
            echo '<label class="form-label">Шаблоны вывода навигации</label>';
            echo '<textarea class="form-control font-monospace code-editor" name="layout_nav_tpl" rows="10">' . renderTextareaValue($navContent ?? '') . '</textarea>';
            echo '<div class="mt-2 d-flex gap-2">';
            echo '<button class="btn btn-link p-0 link-dotted js-code-editor-expand" type="button">Развернуть</button>';
            echo '<button class="btn btn-link p-0 link-dotted js-code-editor-collapse d-none" type="button">Свернуть</button>';
            echo '</div>';
            echo '</div>';
            echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
            echo '</form>';
            if (!in_array($layoutKey, ['default', 'home'], true)) {
                echo '<form class="mt-2" method="post" action="/admin.php?action=layout_delete" data-confirm="Удалить макет? Это действие необратимо.">';
                echo csrf_token_field();
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
}

AdminLayout::renderHeader('Макеты дизайна');

AdminLayout::openSidebar();
echo '<div id="left-sidebar">';
renderLayoutsSidebarHtml($layouts, $layoutKey, $tab);
echo '</div>';
AdminLayout::closeSidebar();

AdminLayout::openContent();
echo '<div id="content">';
renderLayoutsContentHtml($layouts, $layoutKey, $tab, $visualFieldRepo);
echo '</div>';
AdminLayout::closeContent();

AdminLayout::renderFooter();
