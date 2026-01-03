<?php

$users = $userRepo->listAll();
$canSetRole = DB::hasColumn('users', 'role');

AdminLayout::renderHeader('Пользователи');
echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<h1 class="h4 mb-0">Пользователи</h1>';
echo '</div>';
renderAlert($notice, 'success');
renderAlert($errorMessage, 'error');

echo '<div class="card shadow-sm mb-4">';
echo '<div class="card-body">';
echo '<h2 class="h6">Добавить пользователя</h2>';
echo '<form method="post" action="/admin.php?action=user_create">';
echo csrfTokenField();
echo '<div class="row g-3">';
echo '<div class="col-md-4"><label class="form-label">Логин</label><input class="form-control" type="text" name="login" required></div>';
echo '<div class="col-md-4"><label class="form-label">Пароль</label><input class="form-control" type="password" name="password" required></div>';
echo '<div class="col-md-4"><label class="form-label">Повтор пароля</label><input class="form-control" type="password" name="password_confirm" required></div>';
if ($canSetRole) {
    echo '<div class="col-md-4"><label class="form-label">Роль</label><select class="form-select" name="role"><option value="admin">admin</option></select></div>';
}
echo '</div>';
echo '<div class="mt-3"><button class="btn btn-primary" type="submit">Создать</button></div>';
echo '</form>';
echo '</div></div>';

echo '<div class="card shadow-sm">';
echo '<div class="table-responsive">';
echo '<table class="table table-sm table-striped align-middle mb-0">';
echo '<thead><tr><th>ID</th><th>Логин</th><th>Роль</th><th>Действия</th></tr></thead><tbody>';
foreach ($users as $row) {
    $userId = (int) $row['id'];
    $login = htmlspecialchars((string) $row['login'], ENT_QUOTES, 'UTF-8');
    $role = $canSetRole ? htmlspecialchars((string) ($row['role'] ?? ''), ENT_QUOTES, 'UTF-8') : '—';
    echo '<tr>';
    echo '<td>' . $userId . '</td>';
    echo '<td>' . $login . '</td>';
    echo '<td>' . $role . '</td>';
    echo '<td>';
    if ($canSetRole) {
        echo '<form class="d-inline-block me-2" method="post" action="/admin.php?action=user_update_role">';
        echo csrfTokenField();
        echo '<input type="hidden" name="id" value="' . $userId . '">';
        echo '<select class="form-select form-select-sm d-inline-block w-auto" name="role">';
        echo '<option value="admin"' . (($row['role'] ?? '') === 'admin' ? ' selected' : '') . '>admin</option>';
        echo '</select>';
        echo '<button class="btn btn-sm btn-outline-primary ms-2" type="submit">Сохранить роль</button>';
        echo '</form>';
    }
    echo '<form class="d-inline-block me-2" method="post" action="/admin.php?action=user_update_password">';
    echo csrfTokenField();
    echo '<input type="hidden" name="id" value="' . $userId . '">';
    echo '<input class="form-control form-control-sm d-inline-block w-auto" type="password" name="password" placeholder="Новый пароль" required>';
    echo '<input class="form-control form-control-sm d-inline-block w-auto ms-2" type="password" name="password_confirm" placeholder="Повтор" required>';
    echo '<button class="btn btn-sm btn-outline-secondary ms-2" type="submit">Сменить пароль</button>';
    echo '</form>';
    echo '<form class="d-inline-block" method="post" action="/admin.php?action=user_delete" onsubmit="return confirm(\'Удалить пользователя?\')">';
    echo csrfTokenField();
    echo '<input type="hidden" name="id" value="' . $userId . '">';
    echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>';
    echo '</form>';
    echo '</td>';
    echo '</tr>';
}
echo '</tbody></table></div></div>';

AdminLayout::renderFooter();
