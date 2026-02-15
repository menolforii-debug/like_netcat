<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl([]));
}

$components = $componentRepo->listAll();
$componentIdRaw = isset($_GET['component_id']) ? (string) $_GET['component_id'] : '';
$isNewComponent = $componentIdRaw === '_new';
$componentId = $isNewComponent ? 0 : (int) $componentIdRaw;
$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'general';
if (!in_array($tab, ['general', 'fields', 'templates'], true)) {
    $tab = 'general';
}
$viewParam = isset($_GET['view']) ? trim((string) $_GET['view']) : '';
$addView = isset($_GET['add_view']) ? (string) $_GET['add_view'] : '';
$saved = isset($_GET['saved']) ? (string) $_GET['saved'] : '';
$errorMessage = isset($_GET['error']) ? trim((string) $_GET['error']) : '';

$allowedFiles = [
    'list' => 'list.php',
    'single' => 'single.php',
    'system' => 'system.php',
];

$selectedComponent = null;
foreach ($components as $component) {
    if ((int) $component['id'] === $componentId) {
        $selectedComponent = $component;
        break;
    }
}

if ($selectedComponent === null && !$isNewComponent && $components !== []) {
    $selectedComponent = $components[0];
    $componentId = (int) $selectedComponent['id'];
}

$viewsByComponent = [];
foreach ($components as $component) {
    $views = [];
    $decoded = json_decode((string) ($component['views_json'] ?? ''), true);
    if (is_array($decoded)) {
        foreach ($decoded as $view) {
            $view = trim((string) $view);
            if ($view !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $view)) {
                $views[] = $view;
            }
        }
    }
    if ($views === []) {
        $views = ['default'];
    }
    $viewsByComponent[(int) $component['id']] = $views;
}

$fields = [];
if ($selectedComponent !== null) {
    $fieldsJson = $selectedComponent['fields_json'] ?? '[]';
    $decoded = json_decode((string) $fieldsJson, true);
    if (is_array($decoded)) {
        $rawFields = $decoded['fields'] ?? $decoded;
        if (is_array($rawFields)) {
            foreach ($rawFields as $field) {
                if (is_string($field)) {
                    $fields[] = [
                        'name' => $field,
                        'label' => $field,
                        'type' => 'text',
                        'required' => false,
                        'options' => [],
                    ];
                    continue;
                }
                if (!is_array($field) || empty($field['name'])) {
                    continue;
                }
                $options = [];
                if (!empty($field['options']) && is_array($field['options'])) {
                    $options = $field['options'];
                }
                $fields[] = [
                    'name' => (string) $field['name'],
                    'label' => isset($field['label']) ? (string) $field['label'] : (string) $field['name'],
                    'type' => isset($field['type']) ? (string) $field['type'] : 'text',
                    'required' => !empty($field['required']),
                    'options' => $options,
                ];
            }
        }
    }
}

$selectedView = '';
$templateContents = [
    'list' => '',
    'single' => '',
    'system' => '',
];
$templateExists = [
    'list' => false,
    'single' => false,
    'system' => false,
];
$componentKey = '';
$templateError = '';

if ($selectedComponent !== null) {
    $componentKey = trim((string) ($selectedComponent['keyword'] ?? ''));
    $views = $viewsByComponent[(int) $selectedComponent['id']] ?? ['default'];
    if ($viewParam === '' || !in_array($viewParam, $views, true)) {
        $viewParam = $views[0];
    }
    $selectedView = $viewParam;

    if ($componentKey !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $componentKey)) {
        $root = dirname(__DIR__, 4);
        $baseDir = $root . '/templates/component';
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }
        $baseReal = realpath($baseDir);
        if ($baseReal === false) {
            $templateError = 'Не удалось подготовить папку шаблонов.';
        } else {
            $baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            foreach ($allowedFiles as $key => $fileName) {
                $filePath = $baseReal . $componentKey . '/' . $selectedView . '/' . $fileName;
                if (!is_file($filePath)) {
                    continue;
                }
                $templateExists[$key] = true;
                $realFile = realpath($filePath);
                if ($realFile !== false && strpos($realFile, $baseReal) === 0) {
                    $templateContents[$key] = file_get_contents($realFile) ?: '';
                } else {
                    $templateError = 'Некорректный путь к файлу шаблона.';
                    break;
                }
            }
        }
    }

    $defaultTemplates = componentDefaultTemplatesForEditor();

    foreach ($defaultTemplates as $key => $content) {
        if (!$templateExists[$key] && $templateContents[$key] === '') {
            $templateContents[$key] = $content;
        }
    }
}

function renderComponentsBlock(array $ctx): void
{
    $components = $ctx['components'];
    $componentId = $ctx['componentId'];
    $tab = $ctx['tab'];
    $selectedComponent = $ctx['selectedComponent'];
    $fields = $ctx['fields'];
    $isNewComponent = $ctx['isNewComponent'];
    $errorMessage = $ctx['errorMessage'];
    $saved = $ctx['saved'];
    $viewsByComponent = $ctx['viewsByComponent'];
    $selectedView = $ctx['selectedView'];
    $templateContents = $ctx['templateContents'];
    $componentKey = $ctx['componentKey'];
    $templateError = $ctx['templateError'];
    $addView = $ctx['addView'];

    AdminLayout::openSidebar();

    echo '<div id="left-sidebar" class="card shadow-sm border-0">';
    echo '<div class="card-body p-3">';

    echo '<div class="d-flex align-items-center justify-content-between mb-2">';
    echo '<div class="fw-semibold">Компоненты</div>';
    echo '<a class="btn btn-icon-square btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'components', 'component_id' => '_new']), ENT_QUOTES, 'UTF-8') . '" title="Добавить компонент" aria-label="Добавить компонент">+</a>';
    echo '</div>';

    if (empty($components)) {
        echo '<div class="text-muted">Компоненты пока не созданы.</div>';
        echo '</div></div>';
        AdminLayout::closeSidebar();

        AdminLayout::openContent();
        echo '<div id="content" class="card shadow-sm"><div class="card-body">';
        if ($isNewComponent) {
            if ($errorMessage !== '') {
                echo '<div class="mb-3 text-danger">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>';
            }
            echo '<form method="post" action="/admin.php?action=component_create">';
            echo csrf_token_field();
            echo '<div class="mb-3"><label class="form-label">Ключ</label><input class="form-control" name="keyword" required></div>';
            echo '<div class="mb-3"><label class="form-label">Название</label><input class="form-control" name="name" required></div>';
            echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
            echo '</form>';
        } else {
            echo '<div class="text-muted">Создайте первый компонент.</div>';
        }
        echo '</div></div>';
        AdminLayout::closeContent();

        return;
    }

    echo '<nav class="nav-deep nav-deep-sm nav-deep-light component-tree">';
    echo '<ul class="nav flex-column component-tree-root">';

    foreach ($components as $component) {
        $id = (int) $component['id'];
        $name = (string) $component['name'];
        $keyword = (string) ($component['keyword'] ?? '');
        $isActive = $selectedComponent !== null && $id === (int) $selectedComponent['id'];
        $views = $viewsByComponent[$id] ?? ['default'];
        $liClass = 'nav-item component-tree-item';
        if ($isActive) {
            $liClass .= ' is-active is-open';
        }

        $addViewLink = buildAdminUrl([
            'action' => 'components',
            'component_id' => $id,
            'tab' => 'templates',
            'add_view' => 1,
        ]);
        $settingsLink = buildAdminUrl([
            'action' => 'components',
            'component_id' => $id,
            'tab' => 'general',
        ]);

        echo '<li class="' . $liClass . '" data-component-id="' . $id . '">';
        echo '<div class="component-tree-row">';
        echo '<a class="component-tree-link js-component-toggle text-decoration-none" href="' . htmlspecialchars($settingsLink, ENT_QUOTES, 'UTF-8') . '" data-component-id="' . $id . '">';
        echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        echo '<span class="ms-2 text-muted small">' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</a>';
        echo '<div class="component-tree-right">';
        echo '<a class="btn btn-icon-square btn-outline-primary" href="' . htmlspecialchars($addViewLink, ENT_QUOTES, 'UTF-8') . '" title="Добавить шаблон" aria-label="Добавить шаблон">+</a>';
        echo '<button class="btn btn-icon-square btn-outline-secondary component-tree-toggle js-component-toggle" type="button" data-component-id="' . $id . '" aria-label="Показать шаблоны">';
        echo '<span class="component-tree-chevron"></span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';

        echo '<div class="component-tree-views">';
        echo '<div class="fw-semibold small mb-1">Шаблоны</div>';
        echo '<ul class="nav flex-column">';
        foreach ($views as $view) {
            $link = buildAdminUrl([
                'action' => 'components',
                'component_id' => $id,
                'tab' => 'templates',
                'view' => $view,
            ]);
            $isSelected = $isActive && $selectedView === $view && $tab === 'templates';
            $viewItemClass = 'component-tree-viewitem';
            if ($isSelected) {
                $viewItemClass .= ' is-active';
            }
            echo '<li class="nav-item ' . $viewItemClass . '">';
            echo '<div class="component-tree-viewrow">';
            echo '<a class="nav-link component-tree-viewlink text-decoration-none text-truncate" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">';
            echo htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            echo '</a>';
            echo '<span class="component-tree-viewarrow">›</span>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';

        echo '</li>';
    }

    echo '</ul>';
    echo '</nav>';

    echo '</div>';
    echo '</div>';

    AdminLayout::closeSidebar();

    AdminLayout::openContent();

    echo '<div id="content" class="card shadow-sm">';
    echo '<div class="card-body">';

    if ($errorMessage !== '') {
        echo '<div class="mb-3 text-danger">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    // Сообщения create/update/delete теперь показываются только через AJAX → toast.

    if ($selectedComponent === null && !$isNewComponent) {
        echo '<div class="text-muted">Выберите компонент слева.</div>';
        echo '</div></div>';
        AdminLayout::closeContent();
        if ($wrap) {
            echo '</div>';
        }
        return;
    }

    if ($isNewComponent) {
        echo '<form method="post" action="/admin.php?action=component_create">';
        echo csrf_token_field();
        echo '<div class="mb-3"><label class="form-label">Ключ</label><input class="form-control" name="keyword" required></div>';
        echo '<div class="mb-3"><label class="form-label">Название</label><input class="form-control" name="name" required></div>';
        echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
        echo '</form>';
        echo '</div></div>';
        AdminLayout::closeContent();

        if ($wrap) {
            echo '</div>';
        }
        return;
    }

    echo '<div class="mb-2">';
    echo '<h2 class="h5 mb-0">' . htmlspecialchars((string) $selectedComponent['name'], ENT_QUOTES, 'UTF-8') . '</h2>';
    echo '</div>';

    if ($tab !== 'templates') {
        echo '<ul class="nav nav-tabs mb-3">';
        foreach (['general' => 'Общее', 'fields' => 'Поля'] as $key => $label) {
            $active = $tab === $key ? ' active' : '';
            $link = buildAdminUrl([
                'action' => 'components',
                'component_id' => (int) $selectedComponent['id'],
                'tab' => $key,
            ]);
            echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
        echo '</ul>';
    }

    if ($tab === 'general') {
        echo '<form method="post" action="/admin.php?action=component_update">';
        echo csrf_token_field();
        echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
        echo '<input type="hidden" name="return_tab" value="general">';
        echo '<input type="hidden" name="keyword" value="' . htmlspecialchars((string) $selectedComponent['keyword'], ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="mb-3"><label class="form-label">Ключ</label><input class="form-control" value="' . htmlspecialchars((string) $selectedComponent['keyword'], ENT_QUOTES, 'UTF-8') . '" disabled></div>';
        echo '<div class="mb-3"><label class="form-label">Название</label><input class="form-control" name="name" value="' . htmlspecialchars((string) $selectedComponent['name'], ENT_QUOTES, 'UTF-8') . '" required></div>';

        foreach ($fields as $index => $field) {
            echo '<input type="hidden" name="fields[' . $index . '][name]" value="' . htmlspecialchars((string) $field['name'], ENT_QUOTES, 'UTF-8') . '">';
            echo '<input type="hidden" name="fields[' . $index . '][label]" value="' . htmlspecialchars((string) $field['label'], ENT_QUOTES, 'UTF-8') . '">';
            echo '<input type="hidden" name="fields[' . $index . '][type]" value="' . htmlspecialchars((string) $field['type'], ENT_QUOTES, 'UTF-8') . '">';
            echo '<input type="hidden" name="fields[' . $index . '][required]" value="' . (!empty($field['required']) ? '1' : '0') . '">';
            if (!empty($field['options']) && is_array($field['options'])) {
                $optIndex = 0;
                foreach ($field['options'] as $optKey => $optLabel) {
                    echo '<input type="hidden" name="fields[' . $index . '][options][' . $optIndex . '][key]" value="' . htmlspecialchars((string) $optKey, ENT_QUOTES, 'UTF-8') . '">';
                    echo '<input type="hidden" name="fields[' . $index . '][options][' . $optIndex . '][label]" value="' . htmlspecialchars((string) $optLabel, ENT_QUOTES, 'UTF-8') . '">';
                    $optIndex++;
                }
            }
        }

        echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
        echo '</form>';

        echo '<form class="mt-3" method="post" action="/admin.php?action=component_delete" data-confirm="Удалить компонент?">';
        echo csrf_token_field();
        echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
        echo '<button class="btn btn-outline-danger" type="submit">Удалить компонент</button>';
        echo '</form>';
    }

    if ($tab === 'fields') {
        echo '<form method="post" action="/admin.php?action=component_update">';
        echo csrf_token_field();
        echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
        echo '<input type="hidden" name="return_tab" value="fields">';
        echo '<input type="hidden" name="keyword" value="' . htmlspecialchars((string) $selectedComponent['keyword'], ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="name" value="' . htmlspecialchars((string) $selectedComponent['name'], ENT_QUOTES, 'UTF-8') . '">';

        echo '<div class="table-responsive">';
        echo '<table class="table table-sm align-middle">';
        echo '<thead><tr><th>Имя</th><th>Подпись</th><th>Тип</th><th>Обязательное</th><th>Опции</th><th>Удалить</th></tr></thead><tbody>';

        foreach ($fields as $index => $field) {
            echo '<tr>';
            echo '<td><input class="form-control form-control-sm" name="fields[' . $index . '][name]" value="' . htmlspecialchars((string) $field['name'], ENT_QUOTES, 'UTF-8') . '"></td>';
            echo '<td><input class="form-control form-control-sm" name="fields[' . $index . '][label]" value="' . htmlspecialchars((string) $field['label'], ENT_QUOTES, 'UTF-8') . '"></td>';
            echo '<td><select class="form-select form-select-sm" name="fields[' . $index . '][type]">';
            foreach (['text', 'textarea', 'number', 'date', 'checkbox', 'select', 'file'] as $type) {
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
        foreach (['text', 'textarea', 'number', 'date', 'checkbox', 'select', 'file'] as $type) {
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
    }

    if ($tab === 'templates') {
        if ($templateError !== '') {
            echo '<script>window.showGlobalSnackbar(' . json_encode((string) $templateError, JSON_UNESCAPED_UNICODE) . ', "error");</script>';
        }

        if ($addView === '1') {
            echo '<form method="post" action="/admin.php?action=component_view_add" class="mb-4">';
            echo csrf_token_field();
            echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
            echo '<div class="mb-3"><label class="form-label">Название шаблона</label><input class="form-control" name="view" required></div>';
            echo '<button class="btn btn-primary" type="submit">Создать шаблон</button>';
            echo '</form>';
        } else {
            echo '<div class="fw-semibold mb-1">Шаблон: ' . htmlspecialchars($selectedView, ENT_QUOTES, 'UTF-8') . '</div>';
            echo '<div class="mb-2 text-muted small">';
            echo 'templates/component/' . htmlspecialchars($componentKey, ENT_QUOTES, 'UTF-8') . '/' . htmlspecialchars($selectedView, ENT_QUOTES, 'UTF-8') . '/';
            echo '</div>';

            echo '<form method="post" action="/admin.php?action=component_editor_save">';
            echo csrf_token_field();
            echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
            echo '<input type="hidden" name="view" value="' . htmlspecialchars($selectedView, ENT_QUOTES, 'UTF-8') . '">';

            echo '<div class="mb-3 js-code-editor-wrapper">';
            echo '<label class="form-label">Объект в списке (list.php)</label>';
            echo '<textarea class="form-control font-monospace code-editor" id="code_list" name="list_content" rows="10">' . renderTextareaValue($templateContents['list']) . '</textarea>';
            echo '<div class="mt-2 d-flex gap-2">';
            echo '<button class="btn btn-link p-0 link-dotted js-code-editor-expand" type="button">Развернуть</button>';
            echo '<button class="btn btn-link p-0 link-dotted js-code-editor-collapse d-none" type="button">Свернуть</button>';
            echo '</div>';
            echo '</div>';

            echo '<div class="mb-3 js-code-editor-wrapper">';
            echo '<label class="form-label">Вывод одного объекта (single.php)</label>';
            echo '<textarea class="form-control font-monospace code-editor" id="code_single" name="single_content" rows="10">' . renderTextareaValue($templateContents['single']) . '</textarea>';
            echo '<div class="mt-2 d-flex gap-2">';
            echo '<button class="btn btn-link p-0 link-dotted js-code-editor-expand" type="button">Развернуть</button>';
            echo '<button class="btn btn-link p-0 link-dotted js-code-editor-collapse d-none" type="button">Свернуть</button>';
            echo '</div>';
            echo '</div>';

            echo '<div class="mb-3 js-code-editor-wrapper">';
            echo '<label class="form-label">Системные настройки (system.php)</label>';
            echo '<textarea class="form-control font-monospace code-editor" id="code_system" name="system_content" rows="10">' . renderTextareaValue($templateContents['system']) . '</textarea>';
            echo '<div class="mt-2 d-flex gap-2">';
            echo '<button class="btn btn-link p-0 link-dotted js-code-editor-expand" type="button">Развернуть</button>';
            echo '<button class="btn btn-link p-0 link-dotted js-code-editor-collapse d-none" type="button">Свернуть</button>';
            echo '</div>';
            echo '</div>';

            echo '<div class="d-flex gap-2">';
            echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
            echo '</form>';

            echo '<form method="post" action="/admin.php?action=component_view_delete" data-confirm="Удалить шаблон и файлы шаблонов?">';
            echo csrf_token_field();
            echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
            echo '<input type="hidden" name="view" value="' . htmlspecialchars($selectedView, ENT_QUOTES, 'UTF-8') . '">';
            echo '<button class="btn btn-outline-danger" type="submit">Удалить</button>';
            echo '</form>';
            echo '</div>';

            echo '<script>';
            echo 'document.addEventListener("DOMContentLoaded", function () {';
            echo '  if (window.initCodeEditor) {';
            echo '    window.initCodeEditor(document.getElementById("code_list"), "application/x-httpd-php");';
            echo '    window.initCodeEditor(document.getElementById("code_single"), "application/x-httpd-php");';
            echo '    window.initCodeEditor(document.getElementById("code_system"), "application/x-httpd-php");';
            echo '  }';
            echo '});';
            echo '</script>';
        }
    }

    echo '</div></div>';
    AdminLayout::closeContent();

}

$ctx = [
    'components' => $components,
    'componentId' => $componentId,
    'tab' => $tab,
    'selectedComponent' => $selectedComponent,
    'fields' => $fields,
    'isNewComponent' => $isNewComponent,
    'errorMessage' => $errorMessage,
    'saved' => $saved,
    'viewsByComponent' => $viewsByComponent,
    'selectedView' => $selectedView,
    'templateContents' => $templateContents,
    'componentKey' => $componentKey,
    'templateError' => $templateError,
    'addView' => $addView,
];

AdminLayout::renderHeader('Компоненты');

renderComponentsBlock($ctx);

AdminLayout::renderFooter();
