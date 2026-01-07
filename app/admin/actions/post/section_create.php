<?php

$parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
$title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
$englishName = isset($_POST['english_name']) ? trim((string) $_POST['english_name']) : '';
$sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
$layout = isset($_POST['layout']) ? trim((string) $_POST['layout']) : '';

if ($parentId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Родитель не найден']);
    }
    redirectTo(buildAdminUrl(['error' => 'Родитель не найден']));
}

$parent = $sectionRepo->findById($parentId);
if ($parent === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Родитель не найден']);
    }
    redirectTo(buildAdminUrl(['error' => 'Родитель не найден']));
}

if ($title === '' || $englishName === '') {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Название и english_name обязательны']);
    }
    redirectTo(buildAdminUrl(['action' => 'section_new', 'parent_id' => $parentId, 'error' => 'Название и english_name обязательны']));
}

if (!englishNameIsValid($englishName)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'English name должен быть URL-безопасным']);
    }
    redirectTo(buildAdminUrl(['action' => 'section_new', 'parent_id' => $parentId, 'error' => 'English name должен быть URL-безопасным']));
}

$siteId = (int) $parent['site_id'];
if ($sectionRepo->existsSiblingEnglishName($siteId, $parentId, $englishName)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'English name должен быть уникальным в пределах родительского раздела']);
    }
    redirectTo(buildAdminUrl(['action' => 'section_new', 'parent_id' => $parentId, 'error' => 'English name должен быть уникальным в пределах родительского раздела']));
}

$extra = [];
if ($layout !== '' && Layout::layoutExists($layout)) {
    $extra['layout'] = $layout;
}

$sectionId = $sectionRepo->createSection($parentId, $siteId, $englishName, $title, $sort, $extra);
if ($user) {
    AdminLog::log($user['id'], 'section_create', 'section', $sectionId, [
        'title' => $title,
        'parent_id' => $parentId,
        'english_name' => $englishName,
        'sort' => $sort,
        'layout' => $extra['layout'] ?? '',
    ]);
}
if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'notice' => 'Раздел создан',
        'refresh' => ['#sidebarTree', '#contentPane'],
        'focus' => ['section_id' => $sectionId, 'tab' => 'section'],
    ]);
}
redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'section', 'notice' => 'Раздел создан']));
