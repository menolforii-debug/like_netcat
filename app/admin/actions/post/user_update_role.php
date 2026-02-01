<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    // fallback без сообщений
    redirectTo(buildAdminUrl([]));
}

if (!DB::hasColumn('users', 'role')) {
    redirectTo(buildAdminUrl(['action' => 'users_list', 'error' => 'Роли пользователей недоступны']));
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$role = isset($_POST['role']) ? trim((string) $_POST['role']) : '';

if ($userId <= 0) {
    redirectTo(buildAdminUrl(['action' => 'users_list', 'error' => 'Пользователь не найден']));
}

$allowedRoles = ['admin', 'editor', 'guest'];
if (!in_array($role, $allowedRoles, true)) {
    redirectTo(buildAdminUrl(['action' => 'users_list', 'error' => 'Недопустимая роль']));
}

$targetUser = $userRepo->findById($userId);
if ($targetUser === null) {
    redirectTo(buildAdminUrl(['action' => 'users_list', 'error' => 'Пользователь не найден']));
}

if (($targetUser['role'] ?? null) === 'admin' && $role !== 'admin' && $userRepo->countAdmins() <= 1) {
    redirectTo(buildAdminUrl(['action' => 'users_list', 'error' => 'Нельзя изменить роль последнего администратора']));
}

Auth::updateUserRole($userId, $role);

if ($user) {
    AdminLog::log($user['id'], 'user_update_role', 'user', $userId, [
        'login' => $targetUser['login'] ?? null,
        'role' => $role,
    ]);
}

redirectTo(buildAdminUrl(['action' => 'users_list']));
