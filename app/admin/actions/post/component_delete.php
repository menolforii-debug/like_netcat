<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
if ($componentId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$infoblock = DB::fetchOne(
    'SELECT 1 FROM infoblocks WHERE component_id = :component_id LIMIT 1',
    ['component_id' => $componentId]
);
if ($infoblock !== null) {
    $message = 'Нельзя удалить компонент: он используется в инфоблоках.';
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $message]);
    }
    redirectTo(buildAdminUrl([
        'action' => 'components',
        'component_id' => $componentId,
        'error' => $message,
    ]));
}

$componentKey = (string) ($component['keyword'] ?? '');
if ($componentKey !== '' && !componentKeyIsValid($componentKey)) {
    $message = 'Некорректный ключ компонента.';
    error_log($message . ' keyword=' . $componentKey . ' component_id=' . $componentId);
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $message]);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'error' => $message]));
}

try {
    $pdo = DB::pdo();
    $pdo->beginTransaction();

    $componentRepo->deleteWithViews($componentId);

    $root = dirname(__DIR__, 3);
    if ($componentKey !== '') {
        rmTree($root . '/templates/' . $componentKey, $root . '/templates');
        if (is_dir($root . '/templates/' . $componentKey)) {
            error_log('Не удалось удалить каталог шаблонов компонента: ' . $root . '/templates/' . $componentKey);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo = DB::pdo();
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Ошибка удаления компонента: ' . $e->getMessage());
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'error' => $e->getMessage()]));
}

if ($user) {
    AdminLog::log($user['id'], 'component_delete', 'component', $componentId, [
        'keyword' => $component['keyword'] ?? null,
        'name' => $component['name'] ?? null,
    ]);
}

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'notice' => 'Компонент удален',
        'refresh' => ['#components_block'],
    ]);
}
redirectTo(buildAdminUrl(['action' => 'components', 'notice' => 'Компонент удален']));
