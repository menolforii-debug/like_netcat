<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$site = $sectionRepo->findById($id);
if ($site === null || $site['parent_id'] !== null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Сайт не найден']);
    }
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
$layout = isset($_POST['layout']) ? trim((string) $_POST['layout']) : '';
$layoutFieldsKey = isset($_POST['layout_fields_key']) ? trim((string) $_POST['layout_fields_key']) : '';
$layoutFieldsInput = isset($_POST['layout_fields']) && is_array($_POST['layout_fields']) ? $_POST['layout_fields'] : [];

$normalizedDomain = normalizeHost($siteDomain);
$normalizedMirrors = parseMirrorLines($siteMirrorsRaw);
$candidates = array_values(array_unique(array_filter(array_merge([$normalizedDomain], $normalizedMirrors))));

foreach ($candidates as $candidate) {
    $found = $sectionRepo->findSiteByHost($candidate);
    if ($found !== null && (int) $found['id'] !== (int) $id) {
        $message = 'Домен ' . $candidate . ' уже используется сайтом id=' . (int) $found['id'] . ' / title=' . (string) $found['title'];
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => $message]);
        }
        redirectTo(buildAdminUrl(['section_id' => $id, 'error' => $message]));
    }
}

$extra = decodeExtra($site);
$extra['site_domain'] = $normalizedDomain;
$extra['site_mirrors'] = $normalizedMirrors;
$extra['site_enabled'] = $siteEnabled;
$extra['site_offline_html'] = $offlineHtml;
if ($layout !== '' && Layout::layoutExists($layout)) {
    $extra['layout'] = $layout;
} else {
    unset($extra['layout']);
}
if ($layoutFieldsKey !== '' && Layout::layoutExists($layoutFieldsKey)) {
    $fields = readLayoutFields($layoutFieldsKey);
    $values = filterLayoutFieldValues($fields, $layoutFieldsInput);
    if (!empty($values)) {
        $extra['layout_fields'][$layoutFieldsKey] = $values;
    } elseif (!empty($extra['layout_fields']) && is_array($extra['layout_fields'])) {
        unset($extra['layout_fields'][$layoutFieldsKey]);
        if (empty($extra['layout_fields'])) {
            unset($extra['layout_fields']);
        }
    }
}

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

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'notice' => 'Сайт обновлен',
        'refresh' => ['#sidebarTree', '#contentPane'],
        'focus' => ['section_id' => $id],
    ]);
}
redirectTo(buildAdminUrl(['section_id' => $id, 'notice' => 'Сайт обновлен']));
