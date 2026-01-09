<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id > 0) {
    $section = $sectionRepo->findById($id);
    if ($section === null) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Узел не найден']);
        }
        redirectTo(buildAdminUrl(['error' => 'Узел не найден']));
    }

    try {
        if ($section['parent_id'] === null && in_array($section['english_name'], ['index', '404'], true)) {
            if (isAjaxRequest()) {
                jsonResponse(['ok' => false, 'error' => 'Нельзя удалить системный раздел']);
            }
            redirectTo(buildAdminUrl(['section_id' => $id, 'error' => 'Нельзя удалить системный раздел']));
        }
        if ($section['parent_id'] === null) {
            $sectionRepo->deleteSiteRecursive((int) $section['id']);
        } else {
            $sectionRepo->deleteSectionRecursive((int) $section['id'], (int) $section['site_id']);
        }
        $entityType = $section['parent_id'] === null ? 'site' : 'section';
        $actionName = $section['parent_id'] === null ? 'site_delete' : 'section_delete';
        if ($user) {
            AdminLog::log($user['id'], $actionName, $entityType, $id, [
                'title' => $section['title'],
                'parent_id' => $section['parent_id'],
            ]);
        }
        if (isAjaxRequest()) {
            jsonResponse([
                'ok' => true,
                'message' => 'Узел удален',
                'refresh' => ['#sidebarTree', '#contentPane'],
            ]);
        }
        redirectTo(buildAdminUrl());
    } catch (Throwable $e) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
        }
        redirectTo(buildAdminUrl(['section_id' => $selectedId, 'error' => $e->getMessage()]));
    }
}

if (isAjaxRequest()) {
    jsonResponse(['ok' => false, 'error' => 'Узел не найден']);
}
redirectTo(buildAdminUrl(['error' => 'Узел не найден']));
