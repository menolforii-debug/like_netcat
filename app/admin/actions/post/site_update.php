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
$visualSettingsInput = isset($_POST['visual_settings']) && is_array($_POST['visual_settings']) ? $_POST['visual_settings'] : [];
$visualInherit = isset($_POST['visual_inherit']) && is_array($_POST['visual_inherit']) ? $_POST['visual_inherit'] : [];
$hasVisualInput = array_key_exists('visual_settings', $_POST)
    || array_key_exists('visual_inherit', $_POST)
    || array_key_exists('visual_settings_delete', $_POST);
if (!$hasVisualInput && isset($_FILES['visual_settings']) && is_array($_FILES['visual_settings'])) {
    $names = $_FILES['visual_settings']['name'] ?? [];
    if (is_array($names) && array_filter($names)) {
        $hasVisualInput = true;
    }
}

$normalizedDomain = Utils::normalizeHost($siteDomain);
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

if ($hasVisualInput) {
    $visualSettings = [];
    $visualFields = $visualFieldRepo->listAll();
    $existingVisual = isset($extra['visual_settings']) && is_array($extra['visual_settings']) ? $extra['visual_settings'] : [];
    $visualFiles = isset($_FILES['visual_settings']) && is_array($_FILES['visual_settings']) ? $_FILES['visual_settings'] : null;
    $deleteVisual = isset($_POST['visual_settings_delete']) && is_array($_POST['visual_settings_delete'])
        ? $_POST['visual_settings_delete']
        : [];
    $layoutKey = isset($extra['layout']) && Layout::layoutExists((string) $extra['layout']) ? (string) $extra['layout'] : 'default';
    foreach ($visualFields as $field) {
        $name = (string) $field['name'];
        $isInherited = isset($visualInherit[$name]);
        if (!array_key_exists($name, $visualSettingsInput)) {
            if (($field['type'] ?? '') !== 'file') {
                continue;
            }
        }

        $type = (string) ($field['type'] ?? 'text');
        if ($type === 'file') {
            if ($isInherited) {
                continue;
            }
            $deleteRequested = !empty($deleteVisual[$name]) && isset($existingVisual[$name]);
            if ($deleteRequested) {
                deleteUploadedFile((string) $existingVisual[$name]);
                unset($existingVisual[$name]);
            }
            if ($visualFiles !== null) {
                $file = extractNestedUpload($visualFiles, $name);
                if ($file !== null) {
                    $error = null;
                    $fieldId = (int) ($field['id'] ?? 0);
                    // Сохраняем файлы в public_html, поднимаемся из app/admin/actions/post в корень проекта.
                    $targetDir = dirname(__DIR__, 4) . '/public_html/files/layouts/' . $layoutKey . '/' . $fieldId;
                    $publicPrefix = '/files/layouts/' . $layoutKey . '/' . $fieldId;
                    $storedPath = saveUploadedFile($file, $targetDir, $publicPrefix, $error);
                    if ($error !== null) {
                        if (isAjaxRequest()) {
                            jsonResponse(['ok' => false, 'error' => $error]);
                        }
                        redirectTo(buildAdminUrl(['section_id' => $id, 'error' => $error]));
                    }
                    if ($storedPath !== null) {
                        if (isset($existingVisual[$name]) && $existingVisual[$name] !== $storedPath) {
                            deleteUploadedFile((string) $existingVisual[$name]);
                        }
                        $visualSettings[$name] = $storedPath;
                        continue;
                    }
                }
            }

            if (isset($existingVisual[$name])) {
                $visualSettings[$name] = $existingVisual[$name];
            }
            continue;
        }

        $value = $visualSettingsInput[$name];
        if ($type === 'checkbox') {
            if ($isInherited) {
                continue;
            }
            $visualSettings[$name] = !empty($value) ? '1' : '0';
            continue;
        }

        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === '' || $value === null) {
            continue;
        }
        $visualSettings[$name] = $value;
    }

    if (!empty($visualSettings)) {
        $extra['visual_settings'] = $visualSettings;
    } else {
        unset($extra['visual_settings']);
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
$returnSiteTab = isset($_POST['return_site_tab']) ? (string) $_POST['return_site_tab'] : '';
$returnDesignTab = isset($_POST['return_design_tab']) ? (string) $_POST['return_design_tab'] : '';
$params = ['section_id' => $id, 'notice' => 'Сайт обновлен'];
if ($returnSiteTab !== '') {
    $params['site_tab'] = $returnSiteTab;
}
if ($returnDesignTab !== '') {
    $params['design_tab'] = $returnDesignTab;
}
redirectTo(buildAdminUrl($params));
