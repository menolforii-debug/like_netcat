<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$components = $componentRepo->listAll();

AdminLayout::renderHeader('Компоненты');
renderAlert($notice, 'success');
renderAlert($errorMessage, 'error');

echo '<div class="row g-4">';

echo '<div class="col-lg-4">';
echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<h1 class="h5 mb-3">Добавить компонент</h1>';
echo '<form method="post" action="/admin.php?action=component_create">';
echo csrfTokenField();
echo '<div class="mb-3">';
echo '<label class="form-label">Ключ</label>';
echo '<input class="form-control" name="keyword" required>';
echo '</div>';
echo '<div class="mb-3">';
echo '<label class="form-label">Название</label>';
echo '<input class="form-control" name="name" required>';
echo '</div>';
echo '<div class="mb-3">';
echo '<label class="form-label">Поля (JSON)</label>';
echo '<textarea class="form-control font-monospace" name="fields_json" rows="6" required>{"fields":[]}</textarea>';
echo '</div>';
echo '<div class="mb-3">';
echo '<label class="form-label">Виды отображения (JSON)</label>';
echo '<textarea class="form-control font-monospace" name="views_json" rows="3">["list"]</textarea>';
echo '</div>';
echo '<button class="btn btn-primary" type="submit">Создать</button>';
echo '</form>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '<div class="col-lg-8">';
echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<h2 class="h6 mb-3">Список компонентов</h2>';

if (empty($components)) {
    echo '<div class="alert alert-light border">Компоненты пока не созданы.</div>';
} else {
    foreach ($components as $component) {
        $fieldsValue = (string) $component['fields_json'];
        $fieldsDecoded = json_decode($fieldsValue, true);
        if (is_array($fieldsDecoded)) {
            $fieldsValue = json_encode($fieldsDecoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $viewsValue = (string) $component['views_json'];
        $viewsDecoded = json_decode($viewsValue, true);
        if (is_array($viewsDecoded)) {
            $viewsValue = json_encode($viewsDecoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        echo '<div class="border rounded p-3 mb-3">';
        echo '<form method="post" action="/admin.php?action=component_update" class="mb-2">';
        echo csrfTokenField();
        echo '<input type="hidden" name="component_id" value="' . (int) $component['id'] . '">';
        echo '<div class="row g-3">';
        echo '<div class="col-md-4">';
        echo '<label class="form-label">Ключ</label>';
        echo '<input class="form-control" name="keyword" value="' . htmlspecialchars((string) $component['keyword'], ENT_QUOTES, 'UTF-8') . '" required>';
        echo '</div>';
        echo '<div class="col-md-8">';
        echo '<label class="form-label">Название</label>';
        echo '<input class="form-control" name="name" value="' . htmlspecialchars((string) $component['name'], ENT_QUOTES, 'UTF-8') . '" required>';
        echo '</div>';
        echo '<div class="col-12">';
        echo '<label class="form-label">Поля (JSON)</label>';
        echo '<textarea class="form-control font-monospace" name="fields_json" rows="6" required>' . htmlspecialchars($fieldsValue, ENT_QUOTES, 'UTF-8') . '</textarea>';
        echo '</div>';
        echo '<div class="col-12">';
        echo '<label class="form-label">Виды отображения (JSON)</label>';
        echo '<textarea class="form-control font-monospace" name="views_json" rows="3">' . htmlspecialchars($viewsValue, ENT_QUOTES, 'UTF-8') . '</textarea>';
        echo '</div>';
        echo '</div>';
        echo '<div class="mt-3 d-flex gap-2">';
        echo '<button class="btn btn-outline-primary btn-sm" type="submit">Сохранить</button>';
        echo '</div>';
        echo '</form>';

        echo '<form method="post" action="/admin.php?action=component_delete" onsubmit="return confirm(\'Удалить компонент?\')">';
        echo csrfTokenField();
        echo '<input type="hidden" name="component_id" value="' . (int) $component['id'] . '">';
        echo '<button class="btn btn-outline-danger btn-sm" type="submit">Удалить</button>';
        echo '</form>';
        echo '</div>';
    }
}

echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>';

AdminLayout::renderFooter();
