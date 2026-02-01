<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    // fallback без сообщений
    redirectTo(buildAdminUrl([]));
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';

if ($userId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Пользователь не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

if ($password === '') {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Введите новый пароль']);
    }
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

$targetUser = $userRepo->findById($userId);
if ($targetUser === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Пользователь не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

Auth::updateUserPassword($userId, $password);

if ($user) {
    AdminLog::log($user['id'], 'user_update_password', 'user', $userId, [
        'login' => $targetUser['login'] ?? null,
    ]);
}

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'message' => 'Пароль обновлен',
        'refresh' => ['#usersContentBlock'],
    ]);
}

redirectTo(buildAdminUrl(['action' => 'users_list']));
