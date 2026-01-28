<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$sectionId = isset($_GET['section_id']) ? (int) $_GET['section_id'] : 0;
$section = $sectionId > 0 ? $sectionRepo->findById($sectionId) : null;
if ($section === null) {
    redirectTo(buildAdminUrl(['error' => 'Раздел не найден']));
}

$components = $componentRepo->listAll();
$viewsByComponent = [];
foreach ($components as $component) {
    $viewsByComponent[(int) $component['id']] = componentViews($component);
}
$infoblocks = $infoblockRepo->listForSection($sectionId);
$maxSort = 0;
foreach ($infoblocks as $infoblock) {
    if ((int) $infoblock['sort'] > $maxSort) {
        $maxSort = (int) $infoblock['sort'];
    }
}
$defaultSort = $maxSort + 10;

AdminLayout::renderHeader('Новый инфоблок');

echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h1 class="h4 mb-0">Новый инфоблок</h1>';
echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks']), ENT_QUOTES, 'UTF-8') . '">Назад</a>';
echo '</div>';

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<form method="post" action="/admin.php?action=infoblock_create">';
echo csrf_token_field();
echo '<input type="hidden" name="section_id" value="' . (int) $sectionId . '">';
echo '<div class="row g-3">';
echo '<div class="col-md-4"><label class="form-label">Компонент</label><select class="form-select js-infoblock-component" name="component_id">';
$currentView = 'list';
$selectedComponentId = (int) ($components[0]['id'] ?? 0);
$selectedViews = $viewsByComponent[$selectedComponentId] ?? ['list'];
if (!in_array($currentView, $selectedViews, true)) {
    $selectedViews[] = $currentView;
}
foreach ($components as $component) {
    $views = $viewsByComponent[(int) $component['id']] ?? ['list'];
    $viewsJson = htmlspecialchars(json_encode(array_values($views), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    echo '<option value="' . (int) $component['id'] . '" data-views="' . $viewsJson . '">' . htmlspecialchars((string) $component['name'], ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select></div>';
echo '<div class="col-md-4"><label class="form-label">Название</label><input class="form-control js-infoblock-name" type="text" name="name" required></div>';
echo '<div class="col-md-4"><label class="form-label">Ключ</label><input class="form-control js-infoblock-key" type="text" name="key" required></div>';
echo '<div class="col-md-4"><label class="form-label">Шаблон</label><select class="form-select js-infoblock-view" name="view_template" data-current="' . htmlspecialchars($currentView, ENT_QUOTES, 'UTF-8') . '">';
foreach ($selectedViews as $view) {
    $selectedAttr = $currentView === $view ? ' selected' : '';
    echo '<option value="' . htmlspecialchars((string) $view, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars((string) $view, ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select></div>';
echo '<div class="col-md-4"><label class="form-label">Выводить по N объектов на странице</label><input class="form-control" type="number" name="per_page" min="0" value="0"></div>';
echo '<div class="col-md-4"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="' . (int) $defaultSort . '"></div>';
echo '<div class="col-md-4"><label class="form-label">Включен</label>';
echo '<div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_enabled" value="1" checked></div>';
echo '</div>';
echo '<div class="col-12"><label class="form-label">HTML до</label>';
echo '<div class="js-code-editor-wrapper">';
echo '<textarea class="form-control font-monospace code-editor" name="before_html" rows="3"></textarea>';
echo '<div class="d-flex gap-2 mt-2">';
echo '<button class="btn btn-sm btn-outline-secondary js-code-editor-expand" type="button">Развернуть</button>';
echo '<button class="btn btn-sm btn-outline-secondary js-code-editor-collapse d-none" type="button">Свернуть</button>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '<div class="col-12"><label class="form-label">HTML после</label>';
echo '<div class="js-code-editor-wrapper">';
echo '<textarea class="form-control font-monospace code-editor" name="after_html" rows="3"></textarea>';
echo '<div class="d-flex gap-2 mt-2">';
echo '<button class="btn btn-sm btn-outline-secondary js-code-editor-expand" type="button">Развернуть</button>';
echo '<button class="btn btn-sm btn-outline-secondary js-code-editor-collapse d-none" type="button">Свернуть</button>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '<div class="col-md-6"><label class="form-label">Картинка до</label><input class="form-control" type="text" name="before_image"></div>';
echo '<div class="col-md-6"><label class="form-label">Картинка после</label><input class="form-control" type="text" name="after_image"></div>';
echo '</div>';
echo '<div class="mt-3"><button class="btn btn-primary" type="submit">Сохранить</button></div>';
echo '</form>';
echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
