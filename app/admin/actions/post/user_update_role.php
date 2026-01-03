<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$role = isset($_POST['role']) ? trim((string) $_POST['role']) : '';
$target = $id > 0 ? $userRepo->findById($id) : null;
if ($target === null) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Пользователь не найден']));
}
if ($role === '' || $role !== 'admin') {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Недопустимая роль']));
}

$userRepo->updateRole($id, $role);
redirectTo(buildAdminUrl(['action' => 'users', 'notice' => 'Роль обновлена']));
