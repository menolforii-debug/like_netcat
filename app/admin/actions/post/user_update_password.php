<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';

if ($userId <= 0) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Пользователь не найден']));
}

if ($password === '') {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Введите новый пароль']));
}

$targetUser = $userRepo->findById($userId);
if ($targetUser === null) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Пользователь не найден']));
}

Auth::updateUserPassword($userId, $password);

if ($user) {
    AdminLog::log($user['id'], 'user_update_password', 'user', $userId, [
        'login' => $targetUser['login'] ?? null,
    ]);
}

redirectTo(buildAdminUrl(['action' => 'users', 'notice' => 'Пароль обновлен']));
