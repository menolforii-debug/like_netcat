<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    // fallback без сообщений
    redirectTo(buildAdminUrl([]));
}

if (!DB::hasColumn('users', 'role')) {
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$role = isset($_POST['role']) ? trim((string) $_POST['role']) : '';

if ($userId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Пользователь не найден']);
    }
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

$allowedRoles = ['admin', 'editor', 'guest'];
if (!in_array($role, $allowedRoles, true)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недопустимая роль']);
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

if (($targetUser['role'] ?? null) === 'admin' && $role !== 'admin' && $userRepo->countAdmins() <= 1) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Нельзя изменить роль последнего администратора']);
    }
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

Auth::updateUserRole($userId, $role);

if ($user) {
    AdminLog::log($user['id'], 'user_update_role', 'user', $userId, [
        'login' => $targetUser['login'] ?? null,
        'role' => $role,
    ]);
}

if (isAjaxRequest()) {
    adminOk('Роль обновлена', [], false);
}

redirectTo(buildAdminUrl(['action' => 'users_list']));
