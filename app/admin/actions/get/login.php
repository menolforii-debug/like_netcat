<?php

$error = isset($_GET['error']) ? (string) $_GET['error'] : '';
$generatedPassword = null;
if (DB::hasTable('users') && usersCount() === 0) {
    $generatedPassword = bin2hex(random_bytes(8));
    Auth::createUser('admin', $generatedPassword);
    $_SESSION['initial_admin_password'] = $generatedPassword;
} elseif (isset($_SESSION['initial_admin_password'])) {
    $generatedPassword = (string) $_SESSION['initial_admin_password'];
}

AdminLayout::renderHeader('Вход', false);
echo '<div class="row justify-content-center">';
echo '<div class="col-12 col-md-6 col-lg-4">';
echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<h1 class="h4 mb-3 text-center">Вход в админку</h1>';
if ($generatedPassword !== null) {
    renderAlert('Создан пользователь "admin". Сохраните пароль: ' . $generatedPassword, 'warning');
    unset($_SESSION['initial_admin_password']);
}
renderAlert($error, 'error');
echo '<form method="post" action="/admin.php?action=login">';
echo csrfTokenField();
echo '<div class="mb-3"><label class="form-label">Логин</label><input class="form-control" type="text" name="login" required></div>';
echo '<div class="mb-3"><label class="form-label">Пароль</label><input class="form-control" type="password" name="pass" required></div>';
echo '<button class="btn btn-primary w-100" type="submit">Войти</button>';
echo '</form>';
echo '</div></div></div></div>';
AdminLayout::renderFooter();
