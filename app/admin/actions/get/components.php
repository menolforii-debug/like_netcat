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

$selectedComponent = null;
foreach ($components as $component) {
    if ((int) $component['id'] === $componentId) {
        $selectedComponent = $component;
        break;
    }
}

$views = [];
if ($selectedComponent && DB::hasTable('component_views')) {
    $viewRepo = new ComponentViewRepo();
    $views = $viewRepo->listForComponent((int) $selectedComponent['id']);
} else {
    $viewRepo = new ComponentViewRepo();
}
$viewNames = [];
foreach ($views as $viewRow) {
    $viewNames[] = (string) $viewRow['name'];
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

AdminLayout::renderHeader('Компоненты');
renderAlert($notice, 'success');
renderAlert($errorMessage, 'error');

AdminLayout::openSidebar();
echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h2 class="h6 mb-0">Компоненты</h2>';
echo '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'component_new']), ENT_QUOTES, 'UTF-8') . '">Добавить</a>';
echo '</div>';

if (empty($components)) {
    echo '<div class="text-muted">Компоненты пока не созданы.</div>';
} else {
    echo '<div class="list-group list-group-flush">';
    foreach ($components as $component) {
        $isActive = (int) $component['id'] === $componentId;
        $activeClass = $isActive ? ' active' : '';
        $link = buildAdminUrl(['action' => 'components', 'component_id' => (int) $component['id'], 'tab' => $tab]);
        echo '<a class="list-group-item list-group-item-action' . $activeClass . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="d-flex justify-content-between">';
        echo '<span>' . htmlspecialchars((string) $component['name'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<span class="text-muted small">#' . (int) $component['id'] . '</span>';
        echo '</div>';
        echo '</a>';

        if ($isActive) {
            echo '<div class="mt-2 ms-3">';
            echo '<div class="d-flex justify-content-between align-items-center mb-2">';
            echo '<div class="text-muted small">Виды</div>';
            echo '<a class="btn btn-xs btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'components', 'component_id' => (int) $component['id'], 'view' => '_new']), ENT_QUOTES, 'UTF-8') . '"><i class="bi bi-plus"></i></a>';
            echo '</div>';
            if (empty($views)) {
                echo '<div class="text-muted small">Видов нет.</div>';
            } else {
                echo '<div class="list-group list-group-flush">';
                foreach ($views as $viewRow) {
                    $isViewActive = $viewName !== '' && $viewName === (string) $viewRow['name'];
                    $viewLink = buildAdminUrl(['action' => 'components', 'component_id' => (int) $component['id'], 'view' => (string) $viewRow['name']]);
                    $viewActiveClass = $isViewActive ? ' active' : '';
                    echo '<a class="list-group-item list-group-item-action py-1' . $viewActiveClass . '" href="' . htmlspecialchars($viewLink, ENT_QUOTES, 'UTF-8') . '">';
                    echo htmlspecialchars((string) $viewRow['name'], ENT_QUOTES, 'UTF-8');
                    echo '</a>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
    }
    echo '</div>';
}

echo '</div>';
echo '</div>';
AdminLayout::closeSidebar();

AdminLayout::openContent();
echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<h1 class="h4 mb-3">Компоненты</h1>';

if ($selectedComponent === null) {
    echo '<div class="text-muted">Выберите компонент слева.</div>';
    echo '</div>';
    echo '</div>';
    AdminLayout::closeContent();
    AdminLayout::renderFooter();
    return;
}

if ($viewName !== '') {
    $isNewView = $viewName === '_new';
    $viewRow = null;
    if (!$isNewView) {
        $viewRow = $viewRepo->findByName((int) $selectedComponent['id'], $viewName);
    }

    echo '<ul class="nav nav-tabs mb-3">';
    echo '<li class="nav-item"><span class="nav-link active">Редактирование вида</span></li>';
    echo '</ul>';

    if ($isNewView) {
        echo '<form method="post" action="/admin.php?action=component_view_create">';
        echo csrfTokenField();
        echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
        echo '<div class="mb-3"><label class="form-label">Название вида</label><input class="form-control" name="view_name" required></div>';
        echo '<div class="mb-3"><label class="form-label">Шаблон списка</label><textarea class="form-control font-monospace code-editor" name="list_tpl" rows="10"></textarea></div>';
        echo '<div class="mb-3"><label class="form-label">Шаблон объекта</label><textarea class="form-control font-monospace code-editor" name="single_tpl" rows="10"></textarea></div>';
        echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
        echo '</form>';
    } elseif ($viewRow === null) {
        echo '<div class="text-muted">Вид не найден.</div>';
    } else {
        echo '<form method="post" action="/admin.php?action=component_view_update">';
        echo csrfTokenField();
        echo '<input type="hidden" name="view_id" value="' . (int) $viewRow['id'] . '">';
        echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
        echo '<div class="mb-3"><label class="form-label">Название вида</label><input class="form-control" name="view_name" value="' . htmlspecialchars((string) $viewRow['name'], ENT_QUOTES, 'UTF-8') . '" readonly></div>';
        echo '<div class="mb-3"><label class="form-label">Шаблон списка</label><textarea class="form-control font-monospace code-editor" name="list_tpl" rows="10">' . htmlspecialchars((string) $viewRow['list_tpl'], ENT_QUOTES, 'UTF-8') . '</textarea></div>';
        echo '<div class="mb-3"><label class="form-label">Шаблон объекта</label><textarea class="form-control font-monospace code-editor" name="single_tpl" rows="10">' . htmlspecialchars((string) $viewRow['single_tpl'], ENT_QUOTES, 'UTF-8') . '</textarea></div>';
        echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
        echo '</form>';
        echo '<form class="mt-2" method="post" action="/admin.php?action=component_view_delete" onsubmit="return confirm(\'Удалить вид?\')">';
        echo csrfTokenField();
        echo '<input type="hidden" name="view_id" value="' . (int) $viewRow['id'] . '">';
        echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
        echo '<button class="btn btn-outline-danger" type="submit">Удалить</button>';
        echo '</form>';
    }

    echo '</div>';
    echo '</div>';
    AdminLayout::closeContent();
    AdminLayout::renderFooter();
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

    echo '<form class="mt-3" method="post" action="/admin.php?action=component_delete" onsubmit="return confirm(\'Удалить компонент?\')">';
    echo csrfTokenField();
    echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
    echo '<button class="btn btn-outline-danger" type="submit">Удалить компонент</button>';
    echo '</form>';
}

if ($tab === 'fields') {
    echo '<form method="post" action="/admin.php?action=component_update">';
    echo csrfTokenField();
    echo '<input type="hidden" name="component_id" value="' . (int) $selectedComponent['id'] . '">';
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
}

echo '</div>';
echo '</div>';
AdminLayout::closeContent();

AdminLayout::renderFooter();
