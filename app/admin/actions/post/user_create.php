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
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Заполните логин и пароль']);
    }
    redirectTo(buildAdminUrl(['action' => 'users_list']));
}

if ($userRepo->findByLogin($login)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Пользователь с таким логином уже существует']);
    }
    redirectTo(buildAdminUrl(['action' => 'users_list']));
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

if (isAjaxRequest()) {
    adminOk('Пользователь создан', [], false, [
        'redirect' => buildAdminUrl(['action' => 'users_list']),
    ]);
}

redirectTo(buildAdminUrl(['action' => 'users_list']));
