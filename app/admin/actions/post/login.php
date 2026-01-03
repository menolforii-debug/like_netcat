<?php

$login = isset($_POST['login']) ? trim((string) $_POST['login']) : '';
$pass = isset($_POST['pass']) ? (string) $_POST['pass'] : '';

if (Auth::login($login, $pass)) {
    redirectTo('/admin.php');
}

$error = 'Неверный логин или пароль';

AdminLayout::renderHeader('Вход', false);
echo '<div class="row justify-content-center">';
echo '<div class="col-12 col-md-6 col-lg-4">';
echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<h1 class="h4 mb-3 text-center">Вход в админку</h1>';
renderAlert($error, 'error');
echo '<form method="post" action="/admin.php?action=login">';
echo '<div class="mb-3"><label class="form-label">Логин</label><input class="form-control" type="text" name="login" required></div>';
echo '<div class="mb-3"><label class="form-label">Пароль</label><input class="form-control" type="password" name="pass" required></div>';
echo '<button class="btn btn-primary w-100" type="submit">Войти</button>';
echo '</form>';
echo '</div></div></div></div>';
AdminLayout::renderFooter();
exit;
