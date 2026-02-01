<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    // fallback без сообщений
    redirectTo(buildAdminUrl([]));
}

$login = isset($_POST['login']) ? trim((string) $_POST['login']) : '';
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';
$role = isset($_POST['role']) ? trim((string) $_POST['role']) : null;

if ($login === '' || $password === '') {
    redirectTo(buildAdminUrl(['action' => 'users_list', 'error' => 'Заполните логин и пароль']));
}

if ($userRepo->findByLogin($login)) {
    redirectTo(buildAdminUrl(['action' => 'users_list', 'error' => 'Пользователь с таким логином уже существует']));
}

$allowedRoles = ['admin', 'editor', 'guest'];
if ($role !== null && !in_array($role, $allowedRoles, true)) {
    $role = 'editor';
}

$userId = Auth::createUser($login, $password, $role);

if ($user) {
    AdminLog::log($user['id'], 'user_create', 'user', $userId, [
        'login' => $login,
        'role' => $role,
    ]);
}

redirectTo(buildAdminUrl(['action' => 'users_list']));
