<?php

if (!Auth::isAdmin()) {
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl([]));
}

if (!DB::hasColumn('users', 'role')) {
    adminFlashSet('danger', 'Роли пользователей не поддерживаются');
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$role = isset($_POST['role']) ? trim((string) $_POST['role']) : '';

if ($userId <= 0) {
    adminFlashSet('danger', 'Пользователь не найден');
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

$allowedRoles = ['admin', 'editor', 'guest'];
if (!in_array($role, $allowedRoles, true)) {
    adminFlashSet('danger', 'Недопустимая роль');
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

$targetUser = $userRepo->findById($userId);
if ($targetUser === null) {
    adminFlashSet('danger', 'Пользователь не найден');
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

if (($targetUser['role'] ?? null) === 'admin' && $role !== 'admin' && $userRepo->countAdmins() <= 1) {
    adminFlashSet('danger', 'Нельзя изменить роль последнего администратора');
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

Auth::updateUserRole($userId, $role);

if ($user) {
    AdminLog::log($user['id'], 'user_update_role', 'user', $userId, [
        'login' => $targetUser['login'] ?? null,
        'role' => $role,
    ]);
}

adminFlashSet('success', 'Роль обновлена');

redirectTo(buildAdminUrl(['action' => 'users_list']));
