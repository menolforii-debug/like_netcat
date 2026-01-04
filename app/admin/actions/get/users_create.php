<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$hasRole = DB::hasColumn('users', 'role');
$roles = [
    'admin' => 'Администратор',
    'editor' => 'Редактор',
    'guest' => 'Гость',
];

AdminLayout::renderHeader('Новый пользователь');
renderAlert($notice, 'success');
renderAlert($errorMessage, 'error');

echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h1 class="h4 mb-0">Новый пользователь</h1>';
echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'users_list']), ENT_QUOTES, 'UTF-8') . '">Назад</a>';
echo '</div>';

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<form method="post" action="/admin.php?action=user_create">';
echo csrfTokenField();
echo '<div class="mb-3">';
echo '<label class="form-label">Логин</label>';
echo '<input class="form-control" name="login" required>';
echo '</div>';
echo '<div class="mb-3">';
echo '<label class="form-label">Пароль</label>';
echo '<input class="form-control" type="password" name="password" required>';
echo '</div>';
if ($hasRole) {
    echo '<div class="mb-3">';
    echo '<label class="form-label">Роль</label>';
    echo '<select class="form-select" name="role">';
    foreach ($roles as $value => $label) {
        $selected = $value === 'editor' ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select>';
    echo '</div>';
}
echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
echo '</form>';
echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
