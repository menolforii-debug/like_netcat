<?php

$login = isset($_POST['login']) ? trim((string) $_POST['login']) : '';
$pass = isset($_POST['pass']) ? (string) $_POST['pass'] : '';

if (Auth::login($login, $pass)) {
    redirectTo('/admin.php');
}

redirectTo(buildAdminUrl(['action' => 'login', 'error' => 'Неверный логин или пароль']));
