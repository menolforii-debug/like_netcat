<?php

if (!Auth::isAdmin()) {
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl([]));
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
if ($userId <= 0) {
    adminFlashSet('danger', 'Пользователь не найден');
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

$targetUser = $userRepo->findById($userId);
if ($targetUser === null) {
    adminFlashSet('danger', 'Пользователь не найден');
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

if (DB::hasColumn('users', 'role') && ($targetUser['role'] ?? null) === 'admin' && $userRepo->countAdmins() <= 1) {
    adminFlashSet('danger', 'Нельзя удалить последнего администратора');
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

$userRepo->delete($userId);

if ($user) {
    AdminLog::log($user['id'], 'user_delete', 'user', $userId, [
        'login' => $targetUser['login'] ?? null,
    ]);
}

adminFlashSet('success', 'Пользователь удален');

redirectTo(buildAdminUrl(['action' => 'users_list']));
