<?php

$parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
$title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
$englishName = isset($_POST['english_name']) ? trim((string) $_POST['english_name']) : '';
$sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
$layout = isset($_POST['layout']) ? trim((string) $_POST['layout']) : '';

if ($parentId <= 0) {
    redirectTo(buildAdminUrl(['error' => 'Родитель не найден']));
}

$parent = $sectionRepo->findById($parentId);
if ($parent === null) {
    redirectTo(buildAdminUrl(['error' => 'Родитель не найден']));
}

if ($title === '' || $englishName === '') {
    redirectTo(buildAdminUrl(['action' => 'section_new', 'parent_id' => $parentId, 'error' => 'Название и english_name обязательны']));
}

if (!englishNameIsValid($englishName)) {
    redirectTo(buildAdminUrl(['action' => 'section_new', 'parent_id' => $parentId, 'error' => 'English name должен быть URL-безопасным']));
}

$siteId = (int) $parent['site_id'];
if ($sectionRepo->existsSiblingEnglishName($siteId, $parentId, $englishName)) {
    redirectTo(buildAdminUrl(['action' => 'section_new', 'parent_id' => $parentId, 'error' => 'English name должен быть уникальным в пределах родительского раздела']));
}

$extra = [];
if (in_array($layout, ['default', 'home'], true)) {
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
redirectTo(buildAdminUrl(['section_id' => $sectionId, 'notice' => 'Раздел создан']));
