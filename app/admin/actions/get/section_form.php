<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$parentId = isset($_GET['parent_id']) ? (int) $_GET['parent_id'] : 0;

$section = $id > 0 ? $sectionRepo->findById($id) : null;
if ($id > 0 && $section === null) {
    echo '<div class="text-danger">Раздел не найден.</div>';
    exit;
}

$siteId = 0;
if ($section !== null) {
    $siteId = (int) $section['site_id'];
} elseif ($parentId > 0) {
    $parent = $sectionRepo->findById($parentId);
    if ($parent === null) {
        echo '<div class="text-danger">Родительский раздел не найден.</div>';
        exit;
    }
    $siteId = (int) $parent['site_id'];
    $section = [
        'parent_id' => $parentId,
        'title' => '',
        'english_name' => '',
        'sort' => 0,
        'extra_json' => '{}',
    ];
}

if ($siteId <= 0) {
    echo '<div class="text-danger">Не удалось определить сайт.</div>';
    exit;
}

$site = $sectionRepo->findById($siteId);
$options = [];
if ($site !== null) {
    $options[] = $site;
    $options = array_merge($options, collectSections($sectionRepo, $siteId));
}

$extra = $section ? decodeExtra($section) : [];
$layouts = Layout::listLayouts();
$currentLayout = isset($extra['layout']) ? (string) $extra['layout'] : '';
if ($currentLayout !== '' && !in_array($currentLayout, $layouts, true)) {
    $currentLayout = '';
}

$isSystemRoot = $section && in_array($section['english_name'] ?? '', ['index', '404'], true);
$components = [];
if ($id <= 0) {
    $components = $componentRepo->listAll();
}
$showInMenuInherit = !array_key_exists('show_in_menu_inherit', $extra) || !empty($extra['show_in_menu_inherit']);
$showInMenu = !array_key_exists('show_in_menu', $extra) || !empty($extra['show_in_menu']);
$menuTitle = isset($extra['menu_title']) ? (string) $extra['menu_title'] : '';
$h1 = isset($extra['h1']) ? (string) $extra['h1'] : '';

echo '<span data-modal-title="' . ($id > 0 ? 'Редактировать раздел' : 'Новый раздел') . '"></span>';
echo '<form method="post" action="/admin.php?action=' . ($id > 0 ? 'section_update' : 'section_create') . '" data-ajax="true">';
echo csrf_token_field();
if ($id > 0) {
    echo '<input type="hidden" name="id" value="' . (int) $section['id'] . '">';
}
echo '<div class="mb-3"><label class="form-label">Название</label><input class="form-control" type="text" name="title" value="' . htmlspecialchars((string) ($section['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
$englishNameAttributes = $isSystemRoot ? ' disabled' : ' required';
$englishNameHint = $isSystemRoot ? '<div class="form-text">Системный раздел: English name фиксирован.</div>' : '';
echo '<div class="mb-3"><label class="form-label">English name (латиница)</label><input class="form-control" type="text" name="english_name" value="' . htmlspecialchars((string) ($section['english_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '"' . $englishNameAttributes . '>' . $englishNameHint . '</div>';
if ($isSystemRoot) {
    echo '<input type="hidden" name="parent_id" value="' . (int) ($section['parent_id'] ?? 0) . '">';
    echo '<div class="mb-3"><label class="form-label">Родительский раздел</label><div class="form-text">Системный раздел нельзя перемещать.</div></div>';
} else {
    echo '<div class="mb-3"><label class="form-label">Родительский раздел</label><select class="form-select" name="parent_id" required>';
    echo '<option value="">Выберите родителя</option>';
    foreach ($options as $option) {
        if ($id > 0 && (int) $option['id'] === (int) $section['id']) {
            continue;
        }
        if ((int) $option['site_id'] !== $siteId) {
            continue;
        }
        $selectedAttr = (int) ($section['parent_id'] ?? 0) === (int) $option['id'] ? ' selected' : '';
        echo '<option value="' . (int) $option['id'] . '"' . $selectedAttr . '>' . htmlspecialchars((string) $option['title'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select></div>';
}
echo '<div class="mb-3"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="' . htmlspecialchars((string) ($section['sort'] ?? 0), ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<div class="mb-3"><label class="form-label">Макет дизайна</label><select class="form-select" name="layout">';
echo '<option value="">Наследовать макет сайта</option>';
foreach ($layouts as $layout) {
    $selectedAttr = $currentLayout === $layout ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select></div>';
echo '<div class="mb-3"><label class="form-label">H1</label><input class="form-control" type="text" name="h1" value="' . htmlspecialchars($h1, ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<div class="mb-3"><label class="form-label">Альтернативное название для меню</label><input class="form-control" type="text" name="menu_title" value="' . htmlspecialchars($menuTitle, ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<div class="mb-3"><label class="form-label">Показывать в меню</label>';
echo '<input type="hidden" name="show_in_menu_inherit" value="0">';
echo '<div class="form-check">';
echo '<input class="form-check-input" type="checkbox" name="show_in_menu_inherit" value="1"' . ($showInMenuInherit ? ' checked' : '') . '>';
echo '<label class="form-check-label">Наследовать</label>';
echo '</div>';
echo '<input type="hidden" name="show_in_menu" value="0">';
echo '<div class="form-check mt-2">';
echo '<input class="form-check-input" type="checkbox" name="show_in_menu" value="1"' . ($showInMenu ? ' checked' : '') . '>';
echo '<label class="form-check-label">Показывать в меню</label>';
echo '</div>';
echo '</div>';
if ($id <= 0) {
    echo '<div class="mb-3"><label class="form-label">Компонент</label><select class="form-select" name="component_id">';
    echo '<option value="">Без компонента</option>';
    foreach ($components as $component) {
        echo '<option value="' . (int) $component['id'] . '">' . htmlspecialchars((string) $component['name'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select></div>';
}
echo '<div class="d-flex justify-content-end gap-2">';
echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
echo '</div>';
echo '</form>';
