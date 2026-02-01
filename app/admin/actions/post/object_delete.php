<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
if ($id > 0) {
    $object = $objectRepo->findById($id);
    if ($object === null) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Объект не найден']);
        }
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
    }

    $infoblock = $infoblockRepo->findById((int) $object['infoblock_id']);
    if ($infoblock === null) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Инфоблок не найден']);
        }
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
    }

    if (!Permission::canAction($user, $infoblock, 'delete')) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
        }
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
    }

    $component = $componentRepo->findById((int) $object['component_id']);
    if ($component === null) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
        }
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
    }

    $fields = parseComponentFields($component);
    $data = json_decode((string) $object['data_json'], true);
    if (!is_array($data)) {
        $data = [];
    }
    foreach ($fields as $field) {
        if (($field['type'] ?? '') !== 'file') {
            continue;
        }
        $name = (string) $field['name'];
        if (!isset($data[$name]) || !is_string($data[$name]) || $data[$name] === '') {
            continue;
        }
        deleteUploadedFile($data[$name]);
    }

    $objectRepo->softDelete($id);
    if ($user) {
        AdminLog::log($user['id'], 'object_delete', 'object', $id, []);
    }

    if (isAjaxRequest()) {
        jsonResponse([
            'ok' => true,
            'message' => 'Объект удален',
            'refresh' => ['#contentPane'],
        ]);
    }
}
redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
