<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
if ($componentId <= 0) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$usage = DB::fetchOne('SELECT COUNT(*) AS cnt FROM infoblocks WHERE component_id = :id', ['id' => $componentId]);
if ($usage && (int) $usage['cnt'] > 0) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент используется в инфоблоках']));
}

$componentRepo->delete($componentId);

if ($user) {
    AdminLog::log($user['id'], 'component_delete', 'component', $componentId, [
        'keyword' => $component['keyword'] ?? null,
        'name' => $component['name'] ?? null,
    ]);
}

redirectTo(buildAdminUrl(['action' => 'components', 'notice' => 'Компонент удален']));
