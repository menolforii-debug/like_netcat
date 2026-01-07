<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id > 0) {
    $visualFieldRepo->delete($id);
}

redirectTo(buildAdminUrl(['action' => 'layouts', 'tab' => 'visual', 'notice' => 'Поле удалено']));
