<?php

$title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
$siteDomain = isset($_POST['site_domain']) ? trim((string) $_POST['site_domain']) : '';
$siteMirrorsRaw = isset($_POST['site_mirrors']) ? (string) $_POST['site_mirrors'] : '';
$siteEnabled = isset($_POST['site_enabled']) ? true : false;
$offlineHtml = isset($_POST['site_offline_html']) ? (string) $_POST['site_offline_html'] : '';

if ($title === '') {
    redirectTo(buildAdminUrl(['action' => 'site_new', 'error' => 'Название сайта обязательно']));
}

$extra = [
    'site_domain' => $siteDomain,
    'site_mirrors' => parseMirrorLines($siteMirrorsRaw),
    'site_enabled' => $siteEnabled,
    'site_offline_html' => $offlineHtml,
];

$siteId = $sectionRepo->createSite($title, $extra);
// Системные корневые разделы для нового сайта (создаем без дублей).
$rootIndex = $sectionRepo->findRootByEnglishName($siteId, 'index');
if ($rootIndex === null) {
    $sectionRepo->createSection($siteId, $siteId, 'index', 'Главная');
}

$rootNotFound = $sectionRepo->findRootByEnglishName($siteId, '404');
if ($rootNotFound === null) {
    $sectionRepo->createSection($siteId, $siteId, '404', '404');
}
if ($user) {
    AdminLog::log($user['id'], 'site_create', 'site', $siteId, [
        'title' => $title,
        'extra' => $extra,
    ]);
}
redirectTo(buildAdminUrl(['section_id' => $siteId, 'notice' => 'Сайт создан']));
