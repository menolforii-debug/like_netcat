<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$section = $sectionRepo->findById($id);
if ($section === null || $section['parent_id'] === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Раздел не найден']);
    }
    // fallback без сообщений
    redirectTo(buildAdminUrl([]));
}

$title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
$englishName = isset($_POST['english_name']) ? trim((string) $_POST['english_name']) : '';
$parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
$sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
$layout = isset($_POST['layout']) ? trim((string) $_POST['layout']) : '';
$h1 = isset($_POST['h1']) ? trim((string) $_POST['h1']) : '';
$menuTitle = isset($_POST['menu_title']) ? trim((string) $_POST['menu_title']) : '';
$showInMenuInherit = !empty($_POST['show_in_menu_inherit']);
$showInMenu = !empty($_POST['show_in_menu']);
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
$isSystemRoot = in_array($section['english_name'], ['index', '404'], true);
if ($isSystemRoot) {
    $englishName = (string) $section['english_name'];
    $parentId = (int) $section['parent_id'];
}

if ($title === '' || $englishName === '') {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Название и english_name обязательны']);
    }
    redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Название и english_name обязательны']));
}

if (!englishNameIsValid($englishName)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'English name должен быть URL-безопасным']);
    }
    redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'English name должен быть URL-безопасным']));
}

$siteId = (int) $section['site_id'];
if (!$isSystemRoot) {
    if ($parentId <= 0) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Нужен родительский раздел']);
        }
        redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Нужен родительский раздел']));
    }

    $parentIdInvalid = $parentId === $id || $sectionRepo->isDescendant($id, $parentId);
    if ($parentIdInvalid) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Нельзя выбрать текущий раздел или его потомка родителем']);
        }
        redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Нельзя выбрать текущий раздел или его потомка родителем']));
    }

    $parent = $sectionRepo->findById($parentId);
    if ($parent === null || (int) $parent['site_id'] !== $siteId) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Родитель должен относиться к тому же сайту']);
        }
        redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Родитель должен относиться к тому же сайту']));
    }
}

if ($sectionRepo->existsSiblingEnglishName($siteId, $parentId, $englishName, $id)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'English name должен быть уникальным в пределах родительского раздела']);
    }
    redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'English name должен быть уникальным в пределах родительского раздела']));
}

$before = [
    'title' => $section['title'],
    'english_name' => $section['english_name'],
    'parent_id' => $section['parent_id'],
    'sort' => $section['sort'],
];

$extra = Utils::decodeExtra($section);
if ($layout !== '' && LayoutCatalog::layoutExists($layout)) {
    $extra['layout'] = $layout;
} else {
    unset($extra['layout']);
}
if ($h1 !== '') {
    $extra['h1'] = $h1;
} else {
    unset($extra['h1']);
}
if ($menuTitle !== '') {
    $extra['menu_title'] = $menuTitle;
} else {
    unset($extra['menu_title']);
}
if ($showInMenuInherit) {
    $extra['show_in_menu_inherit'] = true;
    unset($extra['show_in_menu']);
} else {
    $extra['show_in_menu_inherit'] = false;
    $extra['show_in_menu'] = $showInMenu ? true : false;
}

if ($hasVisualInput) {
    $visualSettings = [];
    $visualFields = $visualFieldRepo->listAll();
    $existingVisual = isset($extra['visual_settings']) && is_array($extra['visual_settings']) ? $extra['visual_settings'] : [];
    $visualFiles = isset($_FILES['visual_settings']) && is_array($_FILES['visual_settings']) ? $_FILES['visual_settings'] : null;
    $deleteVisual = isset($_POST['visual_settings_delete']) && is_array($_POST['visual_settings_delete'])
        ? $_POST['visual_settings_delete']
        : [];
    $site = $sectionRepo->findById($siteId);
    $siteExtra = $site !== null ? Utils::decodeExtra($site) : [];
    $layoutKey = 'default';
    if (isset($extra['layout']) && LayoutCatalog::layoutExists((string) $extra['layout'])) {
        $layoutKey = (string) $extra['layout'];
    } elseif (isset($siteExtra['layout']) && LayoutCatalog::layoutExists((string) $siteExtra['layout'])) {
        $layoutKey = (string) $siteExtra['layout'];
    }
    foreach ($visualFields as $field) {
        $name = (string) $field['name'];
        if (isset($visualInherit[$name])) {
            continue;
        }
        if (!array_key_exists($name, $visualSettingsInput)) {
            if (($field['type'] ?? '') !== 'file') {
                continue;
            }
        }

        $type = (string) ($field['type'] ?? 'text');
        if ($type === 'file') {
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
                        redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => $error]));
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
            'h1' => $extra['h1'] ?? '',
            'menu_title' => $extra['menu_title'] ?? '',
            'show_in_menu_inherit' => $extra['show_in_menu_inherit'] ?? true,
            'show_in_menu' => $extra['show_in_menu'] ?? true,
        ],
    ]);
}

$returnTab = isset($_POST['return_tab']) ? (string) $_POST['return_tab'] : '';
$returnDesignTab = isset($_POST['return_design_tab']) ? (string) $_POST['return_design_tab'] : '';
if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'message' => $isSystemRoot ? 'Системный раздел обновлен (english_name фиксирован)' : 'Раздел обновлен',
        'refresh' => ['#sidebarTree', '#contentPane'],
        'focus' => ['section_id' => $id],
    ]);
}
$params = ['section_id' => $id, 'tab' => 'section'];
if ($returnTab !== '') {
    $params['tab'] = $returnTab;
}
if ($returnDesignTab !== '') {
    $params['design_tab'] = $returnDesignTab;
}
redirectTo(buildAdminUrl($params));
