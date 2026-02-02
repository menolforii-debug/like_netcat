<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    // fallback без сообщений
    redirectTo(buildAdminUrl([]));
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Поле не найдено']);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual']));
}

try {
    $visualFieldRepo->delete($id);
} catch (Throwable $e) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual']));
}

if (isAjaxRequest()) {
    adminOk('Поле удалено', [], true, [
        'refresh' => ['#visualFieldsBlock'],
    ]);
}

redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual']));
