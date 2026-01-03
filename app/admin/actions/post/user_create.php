<?php

$login = isset($_POST['login']) ? trim((string) $_POST['login']) : '';
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';
$passwordConfirm = isset($_POST['password_confirm']) ? (string) $_POST['password_confirm'] : '';
$role = isset($_POST['role']) ? trim((string) $_POST['role']) : 'admin';

if ($login === '' || $password === '') {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Логин и пароль обязательны']));
}
if ($password !== $passwordConfirm) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Пароли не совпадают']));
}
if ($userRepo->findByLogin($login) !== null) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Логин уже занят']));
}

$userRepo->create($login, $password, $role);
redirectTo(buildAdminUrl(['action' => 'users', 'notice' => 'Пользователь создан']));
