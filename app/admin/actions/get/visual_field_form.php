<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$field = $id > 0 ? $visualFieldRepo->findById($id) : null;
if ($id > 0 && $field === null) {
    echo '<div class="text-danger">Поле не найдено.</div>';
    exit;
}

$title = $field ? 'Редактировать поле' : 'Новое поле';
echo '<span data-modal-title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"></span>';
echo '<form method="post" action="/admin.php?action=' . ($field ? 'visual_field_update' : 'visual_field_create') . '">';
echo csrfTokenField();
if ($field) {
    echo '<input type="hidden" name="id" value="' . (int) $field['id'] . '">';
}

echo '<div class="row g-3">';
echo '<div class="col-md-6"><label class="form-label">Ключ</label><input class="form-control" name="name" value="' . htmlspecialchars((string) ($field['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '"' . ($field ? ' readonly' : ' required') . '></div>';
echo '<div class="col-md-6"><label class="form-label">Название</label><input class="form-control" name="label" value="' . htmlspecialchars((string) ($field['label'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
echo '<div class="col-md-6"><label class="form-label">Тип</label>';
echo '<select class="form-select" name="type">';
foreach (['text' => 'Текст', 'textarea' => 'Текст (многострочный)', 'number' => 'Число', 'checkbox' => 'Флаг', 'select' => 'Список', 'color' => 'Цвет', 'file' => 'Файл'] as $typeKey => $typeLabel) {
    $selected = ($field['type'] ?? 'text') === $typeKey ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select></div>';
echo '<div class="col-md-3"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="' . (int) ($field['sort'] ?? 0) . '"></div>';
echo '</div>';

echo '<div class="d-flex justify-content-end gap-2 mt-3">';
echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
echo '</div>';
echo '</form>';
