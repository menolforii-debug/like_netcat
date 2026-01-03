<?php

$error = isset($error) ? (string) $error : '';
$bootstrapNotice = '';

if (usersCount() === 0) {
    $password = bin2hex(random_bytes(6));
    Auth::createUser('admin', $password, Auth::ROLE_ADMIN);
    $_SESSION['bootstrap_admin_pass'] = $password;
}

if (!empty($_SESSION['bootstrap_admin_pass'])) {
    $password = (string) $_SESSION['bootstrap_admin_pass'];
    $bootstrapNotice = 'Создан администратор. Логин: admin, пароль: ' . $password;
    unset($_SESSION['bootstrap_admin_pass']);
}

AdminLayout::renderHeader('Вход', false);
echo '<div class="row justify-content-center">';
echo '<div class="col-12 col-md-6 col-lg-4">';
echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<h1 class="h4 mb-3 text-center">Вход в админку</h1>';
renderAlert($error, 'error');
renderAlert($bootstrapNotice, 'success');
echo '<form method="post" action="/admin.php?action=login">';
echo '<div class="mb-3"><label class="form-label">Логин</label><input class="form-control" type="text" name="login" required></div>';
echo '<div class="mb-3"><label class="form-label">Пароль</label><input class="form-control" type="password" name="pass" required></div>';
echo '<button class="btn btn-primary w-100" type="submit">Войти</button>';
echo '</form>';
echo '</div></div></div></div>';
AdminLayout::renderFooter();
exit;
