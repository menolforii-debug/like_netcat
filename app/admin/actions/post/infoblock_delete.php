<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$name = isset($_POST['name']) ? (string) $_POST['name'] : '';
if ($id === 0) {
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'Инфоблок не найден']));
}

$infoblockRepo->delete($id);

if ($user) {
    AdminLog::log($user['id'], 'infoblock_delete', 'infoblock', $id, [
        'section_id' => $sectionId,
        'name' => $name,
    ]);
}

redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'notice' => 'Инфоблок удален']));
