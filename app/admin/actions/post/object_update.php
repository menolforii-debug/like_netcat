<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$saveAs = isset($_POST['save_as']) ? (string) $_POST['save_as'] : '';

$object = $objectRepo->findById($id);
if ($object === null) {
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Объект не найден']));
}

$component = $componentRepo->findById((int) $object['component_id']);
if ($component === null) {
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Компонент не найден']));
}

$fields = parseComponentFields($component);
$data = extractFormData($fields);
$errors = validateRequiredFields($fields, $data);
if (!empty($errors)) {
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => implode(' ', $errors)]));
}

$objectRepo->update($id, ['data' => $data]);

if ($saveAs === 'publish') {
    $objectRepo->publish($id);
} elseif ($saveAs === 'draft') {
    $objectRepo->unpublish($id);
}

if ($user) {
    AdminLog::log($user['id'], 'object_update', 'object', $id, [
        'data' => $data,
    ]);
}

redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Объект обновлен']));
