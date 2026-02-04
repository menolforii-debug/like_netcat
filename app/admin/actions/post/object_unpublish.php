<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
if ($id > 0) {
    $object = $objectRepo->findById($id);
    if ($object === null) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Объект не найден']);
        }
        adminFlashSet('danger', 'Объект не найден');
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
    }

    $infoblock = $infoblockRepo->findById((int) $object['infoblock_id']);
    if ($infoblock === null) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Инфоблок не найден']);
        }
        adminFlashSet('danger', 'Инфоблок не найден');
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
    }

    if (!Permission::canAction($user, $infoblock, 'unpublish')) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
        }
        adminFlashSet('danger', 'Недостаточно прав');
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
    }

    $objectRepo->unpublish($id);
    if ($user) {
        AdminLog::log($user['id'], 'object_unpublish', 'object', $id, []);
    }

    if (isAjaxRequest()) {
        adminOk('Объект снят с публикации', [], true, [
            'refresh' => ['#content'],
        ]);
    }
    adminFlashSet('success', 'Объект снят с публикации');
}

redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
