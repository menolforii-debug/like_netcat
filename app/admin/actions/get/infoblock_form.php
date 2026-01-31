<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$sectionId = isset($_GET['section_id']) ? (int) $_GET['section_id'] : 0;

$infoblock = $id > 0 ? $infoblockRepo->findById($id) : null;
if ($id > 0 && $infoblock === null) {
    echo '<div class="text-danger">Инфоблок не найден.</div>';
    exit;
}

if ($infoblock !== null) {
    $sectionId = (int) $infoblock['section_id'];
}

$section = $sectionRepo->findById($sectionId);
if ($section === null) {
    echo '<div class="text-danger">Раздел не найден.</div>';
    exit;
}

$components = $componentRepo->listAll();
$extra = $infoblock ? Utils::decodeExtra($infoblock) : [];
$viewsByComponent = [];
foreach ($components as $component) {
    $viewsByComponent[(int) $component['id']] = componentViews($component);
}
$selectedComponentId = $infoblock ? (int) $infoblock['component_id'] : ((int) ($components[0]['id'] ?? 0));
$currentView = (string) ($infoblock['view_template'] ?? 'list');
$selectedViews = $viewsByComponent[$selectedComponentId] ?? ['list'];
if (!in_array($currentView, $selectedViews, true)) {
    $selectedViews[] = $currentView;
}

echo '<span data-modal-title="' . ($infoblock ? 'Редактировать инфоблок' : 'Новый инфоблок') . '"></span>';
echo '<form method="post" action="/admin.php?action=' . ($infoblock ? 'infoblock_update' : 'infoblock_create') . '" data-ajax="true">';
echo csrf_token_field();
echo '<input type="hidden" name="section_id" value="' . (int) $sectionId . '">';
if ($infoblock) {
    echo '<input type="hidden" name="id" value="' . (int) $infoblock['id'] . '">';
}

echo '<div class="row g-3">';
echo '<div class="col-md-6"><label class="form-label">Компонент</label><select class="form-select js-infoblock-component" name="component_id">';
foreach ($components as $component) {
    $selectedAttr = $infoblock && (int) $infoblock['component_id'] === (int) $component['id'] ? ' selected' : '';
    $views = $viewsByComponent[(int) $component['id']] ?? ['list'];
    $viewsJson = htmlspecialchars(json_encode(array_values($views), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    echo '<option value="' . (int) $component['id'] . '" data-views="' . $viewsJson . '"' . $selectedAttr . '>' . htmlspecialchars((string) $component['name'], ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select></div>';
echo '<div class="col-md-6"><label class="form-label">Название</label><input class="form-control js-infoblock-name" type="text" name="name" value="' . htmlspecialchars((string) ($infoblock['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
echo '<div class="col-md-6"><label class="form-label">Ключ</label><input class="form-control js-infoblock-key" type="text" name="key" value="' . htmlspecialchars((string) ($infoblock['key'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
echo '<div class="col-md-4"><label class="form-label">Шаблон</label><select class="form-select js-infoblock-view" name="view_template" data-current="' . htmlspecialchars($currentView, ENT_QUOTES, 'UTF-8') . '">';
foreach ($selectedViews as $view) {
    $selectedAttr = $currentView === $view ? ' selected' : '';
    echo '<option value="' . htmlspecialchars((string) $view, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars((string) $view, ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select></div>';
echo '<div class="col-md-4"><label class="form-label">Выводить по N объектов на странице</label><input class="form-control" type="number" name="per_page" min="0" value="' . htmlspecialchars((string) ($infoblock['per_page'] ?? 0), ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<div class="col-md-4"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="' . htmlspecialchars((string) ($infoblock['sort'] ?? 0), ENT_QUOTES, 'UTF-8') . '"></div>';
$checked = !empty($infoblock['is_enabled']) || $infoblock === null ? ' checked' : '';
echo '<div class="col-md-4"><label class="form-label">Включен</label><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_enabled" value="1"' . $checked . '></div></div>';
echo '<div class="col-12"><label class="form-label">HTML до</label>';
echo '<div class="js-code-editor-wrapper">';
echo '<textarea class="form-control font-monospace code-editor" name="before_html" rows="2">' . htmlspecialchars((string) ($extra['before_html'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea>';
echo '<div class="d-flex gap-2 mt-2">';
echo '<button class="btn btn-link p-0 link-dotted js-code-editor-expand" type="button">Развернуть</button>';
echo '<button class="btn btn-link p-0 link-dotted js-code-editor-collapse d-none" type="button">Свернуть</button>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '<div class="col-12"><label class="form-label">HTML после</label>';
echo '<div class="js-code-editor-wrapper">';
echo '<textarea class="form-control font-monospace code-editor" name="after_html" rows="2">' . htmlspecialchars((string) ($extra['after_html'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea>';
echo '<div class="d-flex gap-2 mt-2">';
echo '<button class="btn btn-link p-0 link-dotted js-code-editor-expand" type="button">Развернуть</button>';
echo '<button class="btn btn-link p-0 link-dotted js-code-editor-collapse d-none" type="button">Свернуть</button>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '<div class="d-flex justify-content-end gap-2 mt-3">';
echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
echo '</div>';
echo '</form>';
