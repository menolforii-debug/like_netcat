<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$section = $sectionRepo->findById($id);
if ($section === null || $section['parent_id'] === null) {
    redirectTo(buildAdminUrl(['error' => 'Раздел не найден']));
}

$before = decodeExtra($section);

$extra = $before;
$extra['seo_title'] = isset($_POST['seo_title']) ? trim((string) $_POST['seo_title']) : '';
$extra['seo_description'] = isset($_POST['seo_description']) ? trim((string) $_POST['seo_description']) : '';
$extra['seo_keywords'] = isset($_POST['seo_keywords']) ? trim((string) $_POST['seo_keywords']) : '';

$sectionRepo->update($id, [
    'parent_id' => $section['parent_id'],
    'site_id' => $section['site_id'],
    'english_name' => $section['english_name'],
    'title' => $section['title'],
    'sort' => $section['sort'] ?? 0,
    'extra' => $extra,
]);

if ($user) {
    AdminLog::log($user['id'], 'seo_update', 'section', $id, [
        'before' => $before,
        'after' => $extra,
    ]);
}

redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'seo', 'notice' => 'SEO обновлено']));
