<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
if ($id > 0) {
    $object = $objectRepo->findById($id);
    if ($object === null) {
        adminFlashSet('danger', 'Объект не найден');
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Объект не найден']));
    }

    $infoblock = $infoblockRepo->findById((int) $object['infoblock_id']);
    if ($infoblock === null) {
        adminFlashSet('danger', 'Инфоблок не найден');
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Инфоблок не найден']));
    }

    if (!Permission::canAction($user, $infoblock, 'delete')) {
        adminFlashSet('danger', 'Недостаточно прав');
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Недостаточно прав']));
    }

    $component = $componentRepo->findById((int) $object['component_id']);
    if ($component === null) {
        adminFlashSet('danger', 'Компонент не найден');
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Компонент не найден']));
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
    adminFlashSet('success', 'Объект удален');
}
redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
