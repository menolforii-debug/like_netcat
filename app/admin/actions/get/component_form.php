<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$id = isset($_GET['component_id']) ? (int) $_GET['component_id'] : 0;
$component = $id > 0 ? $componentRepo->findById($id) : null;
if ($id > 0 && $component === null) {
    echo '<div class="text-danger">Компонент не найден.</div>';
    exit;
}

$fieldsJson = $component['fields_json'] ?? '{"fields": []}';
$decoded = json_decode((string) $fieldsJson, true);
if (is_array($decoded)) {
    $fieldsJson = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

echo '<span data-modal-title="' . ($component ? 'Редактировать компонент' : 'Новый компонент') . '"></span>';
echo '<form method="post" action="/admin.php?action=' . ($component ? 'component_update' : 'component_create') . '" data-ajax="true">';
echo csrfTokenField();
if ($component) {
    echo '<input type="hidden" name="component_id" value="' . (int) $component['id'] . '">';
}
echo '<div class="mb-3"><label class="form-label">Ключ</label><input class="form-control" name="keyword" value="' . htmlspecialchars((string) ($component['keyword'] ?? ''), ENT_QUOTES, 'UTF-8') . '"' . ($component ? ' disabled' : ' required') . '></div>';
if ($component) {
    echo '<input type="hidden" name="keyword" value="' . htmlspecialchars((string) ($component['keyword'] ?? ''), ENT_QUOTES, 'UTF-8') . '">';
}
echo '<div class="mb-3"><label class="form-label">Название</label><input class="form-control" name="name" value="' . htmlspecialchars((string) ($component['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
echo '<div class="mb-3"><label class="form-label">Поля (JSON)</label><textarea class="form-control font-monospace" name="fields_json" rows="8">' . htmlspecialchars((string) $fieldsJson, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
echo '<div class="d-flex justify-content-end gap-2">';
echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
echo '</div>';
echo '</form>';
