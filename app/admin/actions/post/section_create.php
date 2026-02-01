<?php

$parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
$title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
$englishName = isset($_POST['english_name']) ? trim((string) $_POST['english_name']) : '';
$sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
$layout = isset($_POST['layout']) ? trim((string) $_POST['layout']) : '';
$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$h1 = isset($_POST['h1']) ? trim((string) $_POST['h1']) : '';
$menuTitle = isset($_POST['menu_title']) ? trim((string) $_POST['menu_title']) : '';
$showInMenuInherit = !array_key_exists('show_in_menu_inherit', $_POST) ? true : !empty($_POST['show_in_menu_inherit']);
$showInMenu = !array_key_exists('show_in_menu', $_POST) ? true : !empty($_POST['show_in_menu']);

if ($parentId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Родитель не найден']);
    }
    // fallback без сообщений
    redirectTo(buildAdminUrl([]));
}

$parent = $sectionRepo->findById($parentId);
if ($parent === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Родитель не найден']);
    }
    // fallback без сообщений
    redirectTo(buildAdminUrl([]));
}

if ($title === '' || $englishName === '') {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Название и english_name обязательны']);
    }
    redirectTo(buildAdminUrl(['action' => 'section_new', 'parent_id' => $parentId]));
}

if (!englishNameIsValid($englishName)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'English name должен быть URL-безопасным']);
    }
    redirectTo(buildAdminUrl(['action' => 'section_new', 'parent_id' => $parentId]));
}

$siteId = (int) $parent['site_id'];
if ($sectionRepo->existsSiblingEnglishName($siteId, $parentId, $englishName)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'English name должен быть уникальным в пределах родительского раздела']);
    }
    redirectTo(buildAdminUrl(['action' => 'section_new', 'parent_id' => $parentId]));
}

$extra = [];
if ($layout !== '' && LayoutCatalog::layoutExists($layout)) {
    $extra['layout'] = $layout;
}
if ($h1 !== '') {
    $extra['h1'] = $h1;
}
if ($menuTitle !== '') {
    $extra['menu_title'] = $menuTitle;
}
$extra['show_in_menu_inherit'] = $showInMenuInherit ? true : false;
if ($extra['show_in_menu_inherit']) {
    unset($extra['show_in_menu']);
} else {
    $extra['show_in_menu_inherit'] = false;
    $extra['show_in_menu'] = $showInMenu ? true : false;
}

$sectionId = $sectionRepo->createSection($parentId, $siteId, $englishName, $title, $sort, $extra);
if ($componentId > 0) {
    $component = $componentRepo->findById($componentId);
    if ($component === null) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
        }
        redirectTo(buildAdminUrl(['action' => 'section_new', 'parent_id' => $parentId]));
    }
    $views = componentViews($component);
    $infoblockRepo->create([
        'site_id' => $siteId,
        'section_id' => $sectionId,
        'component_id' => $componentId,
        'key' => '',
        'name' => (string) ($component['name'] ?? 'Контент'),
        'view_template' => $views[0] ?? 'list',
        'per_page' => 0,
        'extra' => [],
        'sort' => 0,
        'is_enabled' => 1,
    ]);
}
if ($user) {
    AdminLog::log($user['id'], 'section_create', 'section', $sectionId, [
        'title' => $title,
        'parent_id' => $parentId,
        'english_name' => $englishName,
        'sort' => $sort,
        'layout' => $extra['layout'] ?? '',
        'h1' => $extra['h1'] ?? '',
        'menu_title' => $extra['menu_title'] ?? '',
        'show_in_menu_inherit' => $extra['show_in_menu_inherit'] ?? true,
        'show_in_menu' => $extra['show_in_menu'] ?? true,
        'component_id' => $componentId > 0 ? $componentId : null,
    ]);
}
if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'message' => 'Раздел создан',
        'refresh' => ['#sidebarTree', '#contentPane'],
        'focus' => ['section_id' => $sectionId, 'tab' => 'section'],
    ]);
}
redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'section']));
