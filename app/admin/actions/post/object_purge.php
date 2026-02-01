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

    if (!Permission::canAction($user, $infoblock, 'purge')) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
        }
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
    }

    $objectRepo->purge($id);
    if ($user) {
        AdminLog::log($user['id'], 'object_purge', 'object', $id, []);
    }

    if (isAjaxRequest()) {
        jsonResponse([
            'ok' => true,
            'message' => 'Объект удален навсегда',
            'refresh' => ['#contentPane'],
        ]);
    }
}

redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
