<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$name = isset($_POST['name']) ? (string) $_POST['name'] : '';
if ($id === 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Инфоблок не найден']);
    }
    adminFlashSet('danger', 'Инфоблок не найден');
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks']));
}

try {
    $infoblockRepo->deleteRecursive($id, $sectionId);
} catch (Throwable $e) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }
    adminFlashSet('danger', $e->getMessage());
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks']));
}

if ($user) {
    AdminLog::log($user['id'], 'infoblock_delete', 'infoblock', $id, [
        'section_id' => $sectionId,
        'name' => $name,
    ]);
}

if (isAjaxRequest()) {
    adminOk('Инфоблок удален', [], true);
}
adminFlashSet('success', 'Инфоблок удален');

redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks']));
