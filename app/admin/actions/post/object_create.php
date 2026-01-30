<?php

$infoblockId = isset($_POST['infoblock_id']) ? (int) $_POST['infoblock_id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$isEnabled = !empty($_POST['is_enabled']);

$infoblock = null;
$infoblocks = $infoblockRepo->listForSection($sectionId);
foreach ($infoblocks as $row) {
    if ((int) $row['id'] === $infoblockId) {
        $infoblock = $row;
        break;
    }
}

if ($infoblock === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Инфоблок не найден']);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Инфоблок не найден']));
}

if (!Permission::canAction($user, $infoblock, 'create')) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Недостаточно прав']));
}

if ($isEnabled && !Permission::canAction($user, $infoblock, 'publish')) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав для публикации']);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Недостаточно прав для публикации']));
}

$component = $componentRepo->findById((int) $infoblock['component_id']);
if ($component === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Компонент не найден']));
}

$fields = parseComponentFields($component);
$data = extractFormData($fields);
foreach ($fields as $field) {
    if (($field['type'] ?? '') !== 'file') {
        continue;
    }

    $name = (string) $field['name'];
    if (!isset($_FILES[$name])) {
        continue;
    }

    $error = null;
    // Сохраняем файлы в public_html, поднимаемся из app/admin/actions/post в корень проекта.
    $targetDir = dirname(__DIR__, 4) . '/public_html/files/component/' . (int) $infoblock['id'];
    $publicPrefix = '/files/component/' . (int) $infoblock['id'];
    $storedPath = saveUploadedFile($_FILES[$name], $targetDir, $publicPrefix, $error);
    if ($error !== null) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => $error]);
        }
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => $error]));
    }
    if ($storedPath !== null) {
        $data[$name] = $storedPath;
    }
}
try {
    $data = (new FieldValidator())->validate($component, $data);
} catch (InvalidArgumentException $e) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => $e->getMessage()]));
}

$status = $isEnabled ? 'published' : 'draft';
$objectId = $objectRepo->create([
    'site_id' => $infoblock['site_id'],
    'section_id' => $infoblock['section_id'],
    'infoblock_id' => $infoblock['id'],
    'component_id' => $infoblock['component_id'],
    'data' => $data,
    'status' => $status,
]);

$actionTemplatePath = componentActionTemplatePath((string) $component['keyword']);
if (is_file($actionTemplatePath)) {
    $message = $objectId;
    $object = $objectRepo->findById($objectId);
    try {
        require $actionTemplatePath;
    } catch (Throwable $e) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Ошибка выполнения шаблона действий: ' . $e->getMessage()]);
        }
        redirectTo(buildAdminUrl([
            'section_id' => $sectionId,
            'tab' => 'content',
            'error' => 'Ошибка выполнения шаблона действий: ' . $e->getMessage(),
        ]));
    }
}

if ($user) {
    AdminLog::log($user['id'], 'object_create', 'object', $objectId, [
        'infoblock_id' => $infoblockId,
        'data' => $data,
        'status' => $status,
    ]);
}

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'message' => 'Объект создан',
        'refresh' => ['#contentPane'],
    ]);
}

redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
