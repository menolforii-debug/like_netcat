<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$components = $componentRepo->listAll();
$componentId = isset($_GET['component_id']) ? (int) $_GET['component_id'] : 0;
$viewName = isset($_GET['view']) ? trim((string) $_GET['view']) : '';
$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'general';
if (!in_array($tab, ['general', 'fields'], true)) {
    $tab = 'general';
}
$errorMessage = isset($_GET['error']) ? trim((string) $_GET['error']) : '';

function renderTextareaValue($value): string
{
    $s = (string) $value;
    $s = preg_replace('~</textarea~i', '&lt;/textarea', $s);
    return $s ?? '';
}

function defaultSystemTemplate(): string
{
    return "<?php\n?>";
}

function defaultQueryJson(): string
{
    return "// Пример настроек запроса (JSON)\n"
        . "// {\n"
        . "//   \"mode\": \"extend\",\n"
        . "//   \"where\": [\"status = :status\"],\n"
        . "//   \"order\": \"created_at DESC\",\n"
        . "//   \"limit\": 20,\n"
        . "//   \"params\": {\n"
        . "//     \"status\": \"published\"\n"
        . "//   },\n"
        . "//   \"ignore_sub\": 0\n"
        . "// }\n";
}

$selectedComponent = null;
foreach ($components as $component) {
    if ((int) $component['id'] === $componentId) {
        $selectedComponent = $component;
        break;
    }
}

/**
 * Views: теперь собираем ДЛЯ ВСЕХ компонентов (чтобы можно было раскрывать любой)
 */
$viewsByComponent = [];
$viewRepo = new ComponentViewRepo();
if (DB::hasTable('component_views')) {
    foreach ($components as $component) {
        $cid = (int) $component['id'];
        $viewsByComponent[$cid] = $viewRepo->listForComponent($cid);
    }
} else {
    foreach ($components as $component) {
        $viewsByComponent[(int) $component['id']] = [];
    }
}

$views = [];
if ($selectedComponent !== null) {
    $views = $viewsByComponent[(int) $selectedComponent['id']] ?? [];
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

function renderComponentsBlock(array $ctx, bool $wrap): void
{
    $components = $ctx['components'];
    $componentId = $ctx['componentId'];
    $tab = $ctx['tab'];
    $viewName = $ctx['viewName'];
    $selectedComponent = $ctx['selectedComponent'];
    $fields = $ctx['fields'];
    $viewsByComponent = $ctx['viewsByComponent'];
    $viewRepo = $ctx['viewRepo'];

    if ($wrap) {
        echo '<div id="components_block" data-refresh-url="' . htmlspecialchars(buildAdminUrl(['action' => 'components_block', 'component_id' => $componentId, 'tab' => $tab, 'view' => $viewName !== '' ? $viewName : null]), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * LEFT SIDEBAR
     */
    AdminLayout::openSidebar();

    echo '<div class="card shadow-sm border-0">';
    echo '<div class="card-body p-3">';

    echo '<div class="d-flex align-items-center justify-content-between mb-2">';
    echo '<div class="fw-semibold">Компоненты</div>';
    echo '<a class="btn btn-icon-square btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'component_new']), ENT_QUOTES, 'UTF-8') . '" title="Добавить компонент" aria-label="Добавить компонент">+</a>';
    echo '</div>';

    if (empty($components)) {
        echo '<div class="text-muted">Компоненты пока не созданы.</div>';
        echo '</div></div>';
        AdminLayout::closeSidebar();

        AdminLayout::openContent();
        echo '<div class="card shadow-sm"><div class="card-body">';
        echo '<div class="text-muted">Создайте первый компонент.</div>';
        echo '</div></div>';
        AdminLayout::closeContent();

        if ($wrap) {
            echo '</div>';
        }
        return;
    }

    echo '<nav class="nav-deep nav-deep-sm nav-deep-light component-tree">';
    echo '<ul class="nav flex-column component-tree-root">';

    foreach ($components as $component) {
        $id = (int) $component['id'];
        $name = (string) $component['name'];
        $isActive = $id === $componentId;

        $componentViews = $viewsByComponent[$id] ?? [];
        $hasChildren = !empty($componentViews);

        $liClass = 'nav-item component-tree-item';
        if ($isActive) {
            $liClass .= ' is-active is-open';
        }

        $link = buildAdminUrl(['action' => 'components', 'component_id' => $id, 'tab' => $tab]);
        $label = $id . '. ' . $name;

        echo '<li class="' . $liClass . '" data-component-id="' . $id . '">';

        echo '<div class="component-tree-row">';

        echo '<a class="nav-link component-tree-link text-decoration-none text-truncate' . ($isActive ? ' fw-bold' : '') . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">';
        echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        echo '</a>';

        echo '<div class="component-tree-right">';

        if ($hasChildren) {
            echo '<button type="button" class="btn btn-icon-square btn-outline-secondary component-tree-toggle js-component-toggle" data-component-id="' . $id . '" aria-label="Свернуть/развернуть">';
            echo '<span class="component-tree-chevron" aria-hidden="true"></span>';
            echo '</button>';
        } else {
            echo '<span class="btn-icon-square section-tree-toggle-spacer" aria-hidden="true"></span>';
        }

        echo '</div>'; // right
        echo '</div>'; // row

        echo '<div class="component-tree-views">';

        echo '<div class="d-flex align-items-center justify-content-between mb-1">';
        echo '<div class="text-muted small text-uppercase" style="letter-spacing:.04em;">Шаблоны компонента</div>';
        echo '<a class="btn btn-icon-square btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'components', 'component_id' => $id, 'view' => '_new']), ENT_QUOTES, 'UTF-8') . '" title="Добавить шаблон" aria-label="Добавить шаблон">+</a>';
        echo '</div>';

        if (empty($componentViews)) {
            echo '<div class="text-muted small">Шаблонов нет.</div>';
        } else {
            echo '<ul class="nav flex-column component-tree-viewlist">';
            foreach ($componentViews as $viewRow) {
                $vn = (string) $viewRow['name'];
                $isViewActive = $isActive && $viewName !== '' && $viewName === $vn;

                $viewLink = buildAdminUrl(['action' => 'components', 'component_id' => $id, 'view' => $vn]);

                $vClass = 'nav-item component-tree-viewitem';
                if ($isViewActive) {
                    $vClass .= ' is-active';
                }

                echo '<li class="' . $vClass . '">';
                echo '<div class="component-tree-viewrow">';
                echo '<a class="nav-link component-tree-viewlink text-decoration-none text-truncate' . ($isViewActive ? ' fw-bold' : '') . '" href="' . htmlspecialchars($viewLink, ENT_QUOTES, 'UTF-8') . '">';
                echo htmlspecialchars($vn, ENT_QUOTES, 'UTF-8');
                echo '</a>';
                echo '<span class="component-tree-viewarrow" aria-hidden="true">→</span>';
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
        }

        echo '</div>'; // views

        echo '</li>';
    }

    echo '</ul>';
    echo '</nav>';

    echo '</div>';
    echo '</div>';

    AdminLayout::closeSidebar();

    /**
     * RIGHT CONTENT
     */
    AdminLayout::openContent();

    echo '<div class="card shadow-sm">';
    echo '<div class="card-body">';

    if ($selectedComponent === null) {
        if ($errorMessage !== '') {
            echo '<div class="mb-3 text-danger">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        echo '<div class="text-muted"> </div>';
        echo '</div></div>';
        AdminLayout::closeContent();
        if ($wrap) {
            echo '</div>';
        }
        return;
    }

    if ($viewName !== '') {
        $isNewView = $viewName === '_new';
        $viewRow = null;
        if (!$isNewView) {
            $viewRow = $viewRepo->findByName((int) $selectedComponent['id'], $viewName);
        }

        echo '<ul class="nav nav-tabs mb-3">';
        echo '<li class="nav-item"><span class="nav-link active">Редактирование шаблона компонента</span></li>';
        echo '</ul>';

        if ($isNewView) {
            if ($errorMessage !== '') {
                echo '<div class="mb-3 text-danger">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>';
            }
            echo '<form method="post" action="/admin.php?action=component_view_create">';
            echo csrfTokenField();
            echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
            echo '<div class="mb-3"><label class="form-label">Название шаблона</label><input class="form-control" name="view_name" required></div>';
            echo '<div class="mb-3"><label class="form-label">Шаблон списка</label><textarea class="form-control font-monospace code-editor" name="list_tpl" rows="10"></textarea></div>';
            echo '<div class="mb-3"><label class="form-label">Шаблон объекта</label><textarea class="form-control font-monospace code-editor" name="single_tpl" rows="10"></textarea></div>';
            echo '<div class="mb-3"><label class="form-label">Настройки запроса (JSON)</label><textarea class="form-control font-monospace code-editor" name="query_json" rows="10">' . renderTextareaValue(defaultQueryJson()) . '</textarea></div>';
            echo '<div class="mb-3"><label class="form-label">Системные настройки</label><textarea class="form-control font-monospace code-editor" name="system_tpl" rows="10">' . renderTextareaValue(defaultSystemTemplate()) . '</textarea></div>';
            echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
            echo '</form>';
        } elseif ($viewRow === null) {
            echo '<div class="text-muted">Шаблон не найден.</div>';
        } else {
            if ($errorMessage !== '') {
                echo '<div class="mb-3 text-danger">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>';
            }
            echo '<form method="post" action="/admin.php?action=component_view_update">';
            echo csrfTokenField();
            echo '<input type="hidden" name="view_id" value="' . (int) $viewRow['id'] . '">';
            echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
            echo '<div class="mb-3"><label class="form-label">Название шаблона</label><input class="form-control" name="view_name" value="' . htmlspecialchars((string) $viewRow['name'], ENT_QUOTES, 'UTF-8') . '" readonly></div>';
            echo '<div class="mb-3"><label class="form-label">Шаблон списка</label><textarea class="form-control font-monospace code-editor" name="list_tpl" rows="10">' . renderTextareaValue($viewRow['list_tpl'] ?? '') . '</textarea></div>';
            echo '<div class="mb-3"><label class="form-label">Шаблон объекта</label><textarea class="form-control font-monospace code-editor" name="single_tpl" rows="10">' . renderTextareaValue($viewRow['single_tpl'] ?? '') . '</textarea></div>';
            echo '<div class="mb-3"><label class="form-label">Настройки запроса (JSON)</label><textarea class="form-control font-monospace code-editor" name="query_json" rows="10">' . renderTextareaValue($viewRow['query_json'] ?? '') . '</textarea></div>';
            echo '<div class="mb-3"><label class="form-label">Системные настройки</label><textarea class="form-control font-monospace code-editor" name="system_tpl" rows="10">' . renderTextareaValue($viewRow['system_tpl'] ?? '') . '</textarea></div>';
            echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
            echo '</form>';
            echo '<form class="mt-2" method="post" action="/admin.php?action=component_view_delete" onsubmit="return confirm(\'Удалить шаблон?\')">';
            echo csrfTokenField();
            echo '<input type="hidden" name="view_id" value="' . (int) $viewRow['id'] . '">';
            echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
            echo '<button class="btn btn-outline-danger" type="submit">Удалить</button>';
            echo '</form>';
        }

        echo '</div></div>';
        AdminLayout::closeContent();

        if ($wrap) {
            echo '</div>';
        }
        return;
    }

    echo '<ul class="nav nav-tabs mb-3">';
    foreach (['general' => 'Общее', 'fields' => 'Поля'] as $key => $label) {
        $active = $tab === $key ? ' active' : '';
        $link = buildAdminUrl(['action' => 'components', 'component_id' => (int) $selectedComponent['id'], 'tab' => $key]);
        echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
    }
    echo '</ul>';

    if ($tab === 'general') {
        echo '<form method="post" action="/admin.php?action=component_update">';
        echo csrfTokenField();
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

        echo '<form class="mt-3" method="post" action="/admin.php?action=component_delete" data-ajax="true" data-confirm="Удалить компонент?">';
        echo csrfTokenField();
        echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
        echo '<button class="btn btn-outline-danger" type="submit">Удалить компонент</button>';
        echo '</form>';
    }

    if ($tab === 'fields') {
        echo '<form method="post" action="/admin.php?action=component_update">';
        echo csrfTokenField();
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

    echo '</div></div>';
    AdminLayout::closeContent();

    if ($wrap) {
        echo '</div>';
    }
}

$ctx = [
    'components' => $components,
    'componentId' => $componentId,
    'tab' => $tab,
    'viewName' => $viewName,
    'selectedComponent' => $selectedComponent,
    'fields' => $fields,
    'viewsByComponent' => $viewsByComponent,
    'viewRepo' => $viewRepo,
];

$partial = isset($_GET['partial']) ? (string) $_GET['partial'] : '';
if ($partial === 'block' || (isAjaxRequest() && $partial === '1')) {
    renderComponentsBlock($ctx, false);
    return;
}

AdminLayout::renderHeader('Компоненты');

renderComponentsBlock($ctx, true);

AdminLayout::renderFooter();
