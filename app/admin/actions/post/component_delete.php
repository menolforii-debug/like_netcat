<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    // fallback без сообщений
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl([]));
}

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
if ($componentId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    adminFlashSet('danger', 'Компонент не найден');
    redirectTo(buildAdminUrl(['action' => 'components']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    adminFlashSet('danger', 'Компонент не найден');
    redirectTo(buildAdminUrl(['action' => 'components']));
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
    adminFlashSet('danger', $message);
    redirectTo(buildAdminUrl([
        'action' => 'components',
        'component_id' => $componentId,
    ]));
}

$componentKey = (string) ($component['keyword'] ?? '');
if ($componentKey !== '' && !componentKeyIsValid($componentKey)) {
    $message = 'Некорректный ключ компонента.';
    error_log($message . ' keyword=' . $componentKey . ' component_id=' . $componentId);
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $message]);
    }
    adminFlashSet('danger', $message);
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId]));
}

try {
    $pdo = DB::pdo();
    $pdo->beginTransaction();

    $componentRepo->deleteWithViews($componentId);

    $root = dirname(__DIR__, 4);
    if ($componentKey !== '') {
        $templatesRoot = $root . '/templates/component';
        $componentTemplates = $templatesRoot . '/' . $componentKey;
        rmTree($componentTemplates, $templatesRoot);
        if (is_dir($componentTemplates)) {
            error_log('Не удалось удалить каталог шаблонов компонента: ' . $componentTemplates);
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
    adminFlashSet('danger', $e->getMessage());
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId]));
}

if ($user) {
    AdminLog::log($user['id'], 'component_delete', 'component', $componentId, [
        'keyword' => $component['keyword'] ?? null,
        'name' => $component['name'] ?? null,
    ]);
}

if (isAjaxRequest()) {
    adminOk('Компонент удален', [], true, [
        'refresh' => ['#components_block'],
    ]);
}
adminFlashSet('success', 'Компонент удален');
redirectTo(buildAdminUrl(['action' => 'components']));
