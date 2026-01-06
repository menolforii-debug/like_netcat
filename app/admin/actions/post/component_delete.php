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

$infoblock = DB::fetchOne(
    'SELECT 1 FROM infoblocks WHERE component_id = :component_id LIMIT 1',
    ['component_id' => $componentId]
);
if ($infoblock !== null) {
    redirectTo(buildAdminUrl([
        'action' => 'components',
        'component_id' => $componentId,
        'error' => 'Нельзя удалить компонент: он используется в инфоблоках.',
    ]));
}

$componentKey = (string) ($component['keyword'] ?? '');
if ($componentKey !== '' && !componentKeyIsValid($componentKey)) {
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'error' => 'Некорректный ключ компонента.']));
}

try {
    $pdo = DB::pdo();
    $pdo->beginTransaction();

    $componentRepo->deleteWithViews($componentId);

    $root = dirname(__DIR__, 3);
    $allowedRoots = [
        $root . '/templates',
        $root . '/var/backups/templates',
    ];

    if ($componentKey !== '') {
        rmTree($root . '/templates/' . $componentKey, $allowedRoots);
        rmTree($root . '/var/backups/templates/' . $componentKey, $allowedRoots);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo = DB::pdo();
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'error' => $e->getMessage()]));
}

if ($user) {
    AdminLog::log($user['id'], 'component_delete', 'component', $componentId, [
        'keyword' => $component['keyword'] ?? null,
        'name' => $component['name'] ?? null,
    ]);
}

redirectTo(buildAdminUrl(['action' => 'components', 'notice' => 'Компонент удален']));
