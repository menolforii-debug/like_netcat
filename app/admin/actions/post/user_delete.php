<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Пользователь не найден']));
}
if ($user && (int) $user['id'] === $id) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Нельзя удалить самого себя']));
}

$target = $userRepo->findById($id);
if ($target === null) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Пользователь не найден']));
}

$role = $target['role'] ?? 'admin';
if ($role === 'admin' && $userRepo->countAdmins() <= 1) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Нельзя удалить последнего администратора']));
}

$userRepo->delete($id);
redirectTo(buildAdminUrl(['action' => 'users', 'notice' => 'Пользователь удален']));
