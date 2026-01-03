<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$section = $sectionRepo->findById($id);
if ($section === null || $section['parent_id'] === null) {
    redirectTo(buildAdminUrl(['error' => 'Раздел не найден']));
}

$title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
$englishName = isset($_POST['english_name']) ? trim((string) $_POST['english_name']) : '';
$parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
$sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
$layout = isset($_POST['layout']) ? trim((string) $_POST['layout']) : '';
$isSystemRoot = $section['parent_id'] === null && in_array($section['english_name'], ['index', '404'], true);
if ($isSystemRoot) {
    $englishName = (string) $section['english_name'];
}

if ($title === '' || $englishName === '') {
    redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Название и english_name обязательны']));
}

if (!englishNameIsValid($englishName)) {
    redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'English name должен быть URL-безопасным']));
}

$siteId = (int) $section['site_id'];
if ($parentId <= 0) {
    redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Нужен родительский раздел']));
}

$parent = $sectionRepo->findById($parentId);
if ($parent === null || (int) $parent['site_id'] !== $siteId) {
    redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Родитель должен относиться к тому же сайту']));
}

if ($sectionRepo->existsSiblingEnglishName($siteId, $parentId, $englishName, $id)) {
    redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'English name должен быть уникальным в пределах родительского раздела']));
}

$before = [
    'title' => $section['title'],
    'english_name' => $section['english_name'],
    'parent_id' => $section['parent_id'],
    'sort' => $section['sort'],
];

$extra = decodeExtra($section);
if (in_array($layout, ['default', 'home'], true)) {
    $extra['layout'] = $layout;
} else {
    unset($extra['layout']);
}

$sectionRepo->update($id, [
    'parent_id' => $parentId,
    'site_id' => $siteId,
    'english_name' => $englishName,
    'title' => $title,
    'sort' => $sort,
    'extra' => $extra,
]);

if ($user) {
    AdminLog::log($user['id'], 'section_update', 'section', $id, [
        'before' => $before,
        'after' => [
            'title' => $title,
            'english_name' => $englishName,
            'parent_id' => $parentId,
            'sort' => $sort,
            'layout' => $extra['layout'] ?? '',
        ],
    ]);
}

$noticeMessage = $isSystemRoot ? 'Системный раздел обновлен (english_name фиксирован)' : 'Раздел обновлен';
redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'notice' => $noticeMessage]));
