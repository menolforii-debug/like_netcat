<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$site = $sectionRepo->findById($id);
if ($site === null || $site['parent_id'] !== null) {
    redirectTo(buildAdminUrl(['error' => 'Сайт не найден']));
}

$before = [
    'title' => $site['title'],
    'extra' => decodeExtra($site),
];

$title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
$siteDomain = isset($_POST['site_domain']) ? trim((string) $_POST['site_domain']) : '';
$siteMirrorsRaw = isset($_POST['site_mirrors']) ? (string) $_POST['site_mirrors'] : '';
$siteEnabled = isset($_POST['site_enabled']) ? true : false;
$offlineHtml = isset($_POST['site_offline_html']) ? (string) $_POST['site_offline_html'] : '';

$extra = decodeExtra($site);
$extra['site_domain'] = $siteDomain;
$extra['site_mirrors'] = parseMirrorLines($siteMirrorsRaw);
$extra['site_enabled'] = $siteEnabled;
$extra['site_offline_html'] = $offlineHtml;

$sectionRepo->update($id, [
    'parent_id' => null,
    'site_id' => $site['site_id'],
    'english_name' => null,
    'title' => $title !== '' ? $title : $site['title'],
    'sort' => $site['sort'] ?? 0,
    'extra' => $extra,
]);

if ($user) {
    AdminLog::log($user['id'], 'site_update', 'site', $id, [
        'before' => $before,
        'after' => [
            'title' => $title !== '' ? $title : $site['title'],
            'extra' => $extra,
        ],
    ]);
}

redirectTo(buildAdminUrl(['section_id' => $id, 'notice' => 'Сайт обновлен']));
