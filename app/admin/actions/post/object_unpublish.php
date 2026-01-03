<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
if ($id > 0) {
    $objectRepo->unpublish($id);
    if ($user) {
        AdminLog::log($user['id'], 'object_unpublish', 'object', $id, []);
    }
}
redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Объект снят с публикации']));
