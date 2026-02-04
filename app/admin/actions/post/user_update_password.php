<?php

if (!Auth::isAdmin()) {
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl([]));
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';

if ($userId <= 0) {
    adminFlashSet('danger', 'Пользователь не найден');
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

if ($password === '') {
    adminFlashSet('danger', 'Введите новый пароль');
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

$targetUser = $userRepo->findById($userId);
if ($targetUser === null) {
    adminFlashSet('danger', 'Пользователь не найден');
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

Auth::updateUserPassword($userId, $password);

if ($user) {
    AdminLog::log($user['id'], 'user_update_password', 'user', $userId, [
        'login' => $targetUser['login'] ?? null,
    ]);
}

adminFlashSet('success', 'Пароль обновлен');

redirectTo(buildAdminUrl(['action' => 'users_list']));
