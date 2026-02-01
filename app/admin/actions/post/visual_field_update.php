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
    $visualFieldRepo->update($id, [
        'name' => isset($_POST['name']) ? trim((string) $_POST['name']) : '',
        'label' => isset($_POST['label']) ? trim((string) $_POST['label']) : '',
        'type' => isset($_POST['type']) ? trim((string) $_POST['type']) : 'text',
        'sort' => isset($_POST['sort']) ? (int) $_POST['sort'] : 0,
    ]);
} catch (Throwable $e) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }
    redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual']));
}

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'message' => 'Поле обновлено',
        'refresh' => ['#visualFieldsBlock'],
    ]);
}

redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual']));
