<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    // fallback без сообщений
    redirectTo(buildAdminUrl([]));
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id > 0) {
    $visualFieldRepo->delete($id);
}

redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual']));
