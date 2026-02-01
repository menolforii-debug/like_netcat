<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    // fallback без сообщений
    redirectTo(buildAdminUrl([]));
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
if ($userId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Пользователь не найден']);
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

if (DB::hasColumn('users', 'role') && ($targetUser['role'] ?? null) === 'admin' && $userRepo->countAdmins() <= 1) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Нельзя удалить последнего администратора']);
    }
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

$userRepo->delete($userId);

if ($user) {
    AdminLog::log($user['id'], 'user_delete', 'user', $userId, [
        'login' => $targetUser['login'] ?? null,
    ]);
}

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'message' => 'Пользователь удален',
        'refresh' => ['#usersContentBlock'],
    ]);
}

redirectTo(buildAdminUrl(['action' => 'users_list']));
