<?php

$title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
$siteDomain = isset($_POST['site_domain']) ? trim((string) $_POST['site_domain']) : '';
$siteMirrorsRaw = isset($_POST['site_mirrors']) ? (string) $_POST['site_mirrors'] : '';
$siteEnabled = isset($_POST['site_enabled']) ? true : false;
$offlineHtml = isset($_POST['site_offline_html']) ? (string) $_POST['site_offline_html'] : '';
$layout = isset($_POST['layout']) ? trim((string) $_POST['layout']) : '';

if ($title === '') {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Название сайта обязательно']);
    }
    redirectTo(buildAdminUrl(['action' => 'site_new']));
}

$existingSites = $sectionRepo->listSitesOnly();
if (!empty($existingSites)) {
    $message = 'В системе уже есть сайт. Поддерживается только один сайт.';
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $message]);
    }
    redirectTo(buildAdminUrl(['action' => 'site_new']));
}

$normalizedDomain = Utils::normalizeHost($siteDomain);
$normalizedMirrors = parseMirrorLines($siteMirrorsRaw);
$candidates = array_values(array_unique(array_filter(array_merge([$normalizedDomain], $normalizedMirrors))));

foreach ($candidates as $candidate) {
    $found = $sectionRepo->findSiteByHost($candidate);
    if ($found !== null) {
        $message = 'Домен ' . $candidate . ' уже используется сайтом id=' . (int) $found['id'] . ' / title=' . (string) $found['title'];
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => $message]);
        }
        redirectTo(buildAdminUrl(['action' => 'site_new']));
    }
}

$extra = [
    'site_domain' => $normalizedDomain,
    'site_mirrors' => $normalizedMirrors,
    'site_enabled' => $siteEnabled,
    'site_offline_html' => $offlineHtml,
];
if ($layout !== '' && Layout::layoutExists($layout)) {
    $extra['layout'] = $layout;
}

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
if (isAjaxRequest()) {
    adminOk('Сайт создан', ['section_id' => $siteId], true);
}
redirectTo(buildAdminUrl(['section_id' => $siteId]));
