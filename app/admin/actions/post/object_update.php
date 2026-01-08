<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$saveAs = isset($_POST['save_as']) ? (string) $_POST['save_as'] : '';

$object = $objectRepo->findById($id);
if ($object === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Объект не найден']);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Объект не найден']));
}

$infoblock = $infoblockRepo->findById((int) $object['infoblock_id']);
if ($infoblock === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Инфоблок не найден']);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Инфоблок не найден']));
}

if (!Permission::canAction($user, $infoblock, 'edit')) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Недостаточно прав']));
}

$component = $componentRepo->findById((int) $object['component_id']);
if ($component === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Компонент не найден']));
}

$fields = parseComponentFields($component);
$data = extractFormData($fields);
$existingData = json_decode((string) $object['data_json'], true);
if (!is_array($existingData)) {
    $existingData = [];
}
$deleteFiles = isset($_POST['delete_files']) && is_array($_POST['delete_files']) ? $_POST['delete_files'] : [];
foreach ($fields as $field) {
    if (($field['type'] ?? '') !== 'file') {
        continue;
    }

    $name = (string) $field['name'];
    $deleteRequested = !empty($deleteFiles[$name]) && isset($existingData[$name]);
    if ($deleteRequested) {
        deleteUploadedFile((string) $existingData[$name]);
        unset($existingData[$name]);
    }

    if (isset($_FILES[$name])) {
        $error = null;
        // Сохраняем файлы в public_html, поднимаемся из app/admin/actions/post в корень проекта.
        $targetDir = dirname(__DIR__, 4) . '/public_html/files/component/' . (int) $object['infoblock_id'];
        $publicPrefix = '/files/component/' . (int) $object['infoblock_id'];
        $storedPath = saveUploadedFile($_FILES[$name], $targetDir, $publicPrefix, $error);
        if ($error !== null) {
            if (isAjaxRequest()) {
                jsonResponse(['ok' => false, 'error' => $error]);
            }
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => $error]));
        }
        if ($storedPath !== null) {
            if (isset($existingData[$name]) && $existingData[$name] !== $storedPath) {
                deleteUploadedFile((string) $existingData[$name]);
            }
            $data[$name] = $storedPath;
            continue;
        }
    }

    if (isset($existingData[$name])) {
        $data[$name] = $existingData[$name];
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

$objectRepo->update($id, ['data' => $data]);

if ($saveAs === 'publish') {
    if (!Permission::canAction($user, $infoblock, 'publish')) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Недостаточно прав для публикации']);
        }
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Недостаточно прав для публикации']));
    }
    $objectRepo->publish($id);
} elseif ($saveAs === 'draft') {
    if (!Permission::canAction($user, $infoblock, 'unpublish')) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Недостаточно прав для снятия с публикации']);
        }
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Недостаточно прав для снятия с публикации']));
    }
    $objectRepo->unpublish($id);
}

if ($user) {
    AdminLog::log($user['id'], 'object_update', 'object', $id, [
        'data' => $data,
    ]);
}

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'refresh' => ['#contentPane'],
    ]);
}

redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Объект обновлен']));
