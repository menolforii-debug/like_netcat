<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$name = isset($_POST['name']) ? (string) $_POST['name'] : '';
if ($id === 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Инфоблок не найден']);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'Инфоблок не найден']));
}

try {
    $infoblockRepo->deleteRecursive($id, $sectionId);
} catch (Throwable $e) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => $e->getMessage()]));
}

if ($user) {
    AdminLog::log($user['id'], 'infoblock_delete', 'infoblock', $id, [
        'section_id' => $sectionId,
        'name' => $name,
    ]);
}

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'message' => 'Инфоблок удален',
        'refresh' => ['#sidebarTree', '#contentPane'],
    ]);
}

redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks']));
