<?php

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';
$passwordConfirm = isset($_POST['password_confirm']) ? (string) $_POST['password_confirm'] : '';
if ($id <= 0) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Пользователь не найден']));
}
if ($password === '') {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Пароль обязателен']));
}
if ($password !== $passwordConfirm) {
    redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Пароли не совпадают']));
}

$userRepo->updatePassword($id, $password);
redirectTo(buildAdminUrl(['action' => 'users', 'notice' => 'Пароль обновлен']));
