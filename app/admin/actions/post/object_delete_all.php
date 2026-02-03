<?php

$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$infoblockId = isset($_POST['infoblock_id']) ? (int) $_POST['infoblock_id'] : 0;

if ($infoblockId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Инфоблок не найден']);
    }
    adminFlashSet('danger', 'Инфоблок не найден');
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Инфоблок не найден']));
}

$infoblock = $infoblockRepo->findById($infoblockId);
if ($infoblock === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Инфоблок не найден']);
    }
    adminFlashSet('danger', 'Инфоблок не найден');
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Инфоблок не найден']));
}

if (!Permission::canAction($user, $infoblock, 'delete')) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Недостаточно прав']));
}

$component = $componentRepo->findById((int) $infoblock['component_id']);
if ($component === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    adminFlashSet('danger', 'Компонент не найден');
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Компонент не найден']));
}

$fields = parseComponentFields($component);
$objects = $objectRepo->listForInfoblock($infoblockId, true);
foreach ($objects as $object) {
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

    if (empty($object['is_deleted'])) {
        $objectRepo->softDelete((int) $object['id']);
    }
}

if ($user) {
    AdminLog::log($user['id'], 'object_delete_all', 'infoblock', $infoblockId, []);
}

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'message' => 'Все объекты удалены',
        'refresh' => ['#contentPane'],
    ]);
}
adminFlashSet('success', 'Все объекты удалены');

redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'content_infoblock_id' => $infoblockId]));
