<?php

$parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
if ($parentId > 0) {
    $parent = $sectionRepo->findById($parentId);
    if ($parent === null) {
        redirectTo(buildAdminUrl(['error' => 'Родитель не найден']));
    }

    $title = 'Новый раздел';
    $englishName = 'section-' . date('YmdHis');
    $sectionId = $sectionRepo->createSection($parentId, (int) $parent['site_id'], $englishName, $title);
    if ($user) {
        AdminLog::log($user['id'], 'section_create', 'section', $sectionId, [
            'title' => $title,
            'parent_id' => $parentId,
        ]);
    }
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'notice' => 'Раздел создан']));
}

redirectTo(buildAdminUrl(['error' => 'Родитель не найден']));
