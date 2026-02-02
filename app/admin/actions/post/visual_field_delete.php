<?php

if (!Auth::isAdmin()) {
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl([]));
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    adminFlashSet('danger', 'Поле не найдено');
    redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual']));
}

try {
    $visualFieldRepo->delete($id);
} catch (Throwable $e) {
    adminFlashSet('danger', $e->getMessage());
    redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual']));
}

adminFlashSet('success', 'Поле удалено');
redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual']));
