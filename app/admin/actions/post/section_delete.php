<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id > 0) {
    $section = $sectionRepo->findById($id);
    if ($section === null) {
        redirectTo(buildAdminUrl(['error' => 'Узел не найден']));
    }

    try {
        if ($section['parent_id'] === null && in_array($section['english_name'], ['index', '404'], true)) {
            redirectTo(buildAdminUrl(['section_id' => $id, 'error' => 'Нельзя удалить системный раздел']));
        }
        $sectionRepo->delete($id);
        $entityType = $section['parent_id'] === null ? 'site' : 'section';
        $actionName = $section['parent_id'] === null ? 'site_delete' : 'section_delete';
        if ($user) {
            AdminLog::log($user['id'], $actionName, $entityType, $id, [
                'title' => $section['title'],
                'parent_id' => $section['parent_id'],
            ]);
        }
        redirectTo(buildAdminUrl(['notice' => 'Узел удален']));
    } catch (Throwable $e) {
        redirectTo(buildAdminUrl(['section_id' => $selectedId, 'error' => $e->getMessage()]));
    }
}

redirectTo(buildAdminUrl(['error' => 'Узел не найден']));
