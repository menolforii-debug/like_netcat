<?php

$title = isset($_POST['title']) ? trim((string) $_POST['title']) : 'Новый сайт';
$siteId = $sectionRepo->createSite($title);

$rootIndex = DB::fetchOne(
    'SELECT 1 FROM sections WHERE site_id = :site_id AND parent_id = :parent_id AND english_name = :english_name LIMIT 1',
    ['site_id' => $siteId, 'parent_id' => $siteId, 'english_name' => 'index']
);
if ($rootIndex === null) {
    $sectionRepo->createSection($siteId, $siteId, 'index', 'Главная');
}

$rootNotFound = DB::fetchOne(
    'SELECT 1 FROM sections WHERE site_id = :site_id AND parent_id = :parent_id AND english_name = :english_name LIMIT 1',
    ['site_id' => $siteId, 'parent_id' => $siteId, 'english_name' => '404']
);
if ($rootNotFound === null) {
    $sectionRepo->createSection($siteId, $siteId, '404', '404');
}

if ($user) {
    AdminLog::log($user['id'], 'site_create', 'site', $siteId, [
        'title' => $title,
    ]);
}

redirectTo(buildAdminUrl(['section_id' => $siteId, 'notice' => 'Сайт создан']));
