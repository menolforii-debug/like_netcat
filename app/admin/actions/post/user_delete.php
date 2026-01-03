<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
if ($userId <= 0) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Пользователь не найден']));
}

$targetUser = $userRepo->findById($userId);
if ($targetUser === null) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Пользователь не найден']));
}

if (DB::hasColumn('users', 'role') && ($targetUser['role'] ?? null) === 'admin' && $userRepo->countAdmins() <= 1) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Нельзя удалить последнего администратора']));
}

$userRepo->delete($userId);

if ($user) {
    AdminLog::log($user['id'], 'user_delete', 'user', $userId, [
        'login' => $targetUser['login'] ?? null,
    ]);
}

redirectTo(buildAdminUrl(['action' => 'users', 'notice' => 'Пользователь удален']));
