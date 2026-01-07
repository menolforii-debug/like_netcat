<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$section = $sectionRepo->findById($id);
if ($section === null || $section['parent_id'] === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Раздел не найден']);
    }
    redirectTo(buildAdminUrl(['error' => 'Раздел не найден']));
}

$title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
$englishName = isset($_POST['english_name']) ? trim((string) $_POST['english_name']) : '';
$parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
$sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
$layout = isset($_POST['layout']) ? trim((string) $_POST['layout']) : '';
$visualSettingsInput = isset($_POST['visual_settings']) && is_array($_POST['visual_settings']) ? $_POST['visual_settings'] : [];
$visualInherit = isset($_POST['visual_inherit']) && is_array($_POST['visual_inherit']) ? $_POST['visual_inherit'] : [];
$hasVisualInput = array_key_exists('visual_settings', $_POST) || array_key_exists('visual_inherit', $_POST);
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

$extra = decodeExtra($section);
if ($layout !== '' && Layout::layoutExists($layout)) {
    $extra['layout'] = $layout;
} else {
    unset($extra['layout']);
}

if ($hasVisualInput) {
    $visualSettings = [];
    $visualFields = $visualFieldRepo->listAll();
    foreach ($visualFields as $field) {
        $name = (string) $field['name'];
        if (isset($visualInherit[$name])) {
            continue;
        }
        if (!array_key_exists($name, $visualSettingsInput)) {
            continue;
        }

        $value = $visualSettingsInput[$name];
        $type = (string) ($field['type'] ?? 'text');
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
        ],
    ]);
}

$noticeMessage = $isSystemRoot ? 'Системный раздел обновлен (english_name фиксирован)' : 'Раздел обновлен';
if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'notice' => $noticeMessage,
        'refresh' => ['#sidebarTree', '#contentPane'],
        'focus' => ['section_id' => $id],
    ]);
}
redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'notice' => $noticeMessage]));
