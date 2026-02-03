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

    if (!Permission::canAction($user, $infoblock, 'publish')) {
        adminFlashSet('danger', 'Недостаточно прав');
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Недостаточно прав']));
    }

    $objectRepo->publish($id);
    if ($user) {
        AdminLog::log($user['id'], 'object_publish', 'object', $id, []);
    }
    adminFlashSet('success', 'Объект опубликован');
}
redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
