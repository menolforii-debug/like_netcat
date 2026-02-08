<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl([]));
}

$hasRole = DB::hasColumn('users', 'role');
$roles = [
    'admin' => 'Администратор',
    'editor' => 'Редактор',
    'guest' => 'Гость',
];
$users = $userRepo->listAll();
$adminCount = $userRepo->countAdmins();

AdminLayout::renderHeader('Пользователи');

echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h1 class="h4 mb-0">Пользователи</h1>';
echo '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'users_create']), ENT_QUOTES, 'UTF-8') . '">Добавить</a>';
echo '</div>';

echo '<div id="content" class="card shadow-sm">';
UsersListView::renderCard($users, $roles, $hasRole, $adminCount);
echo '</div>';

AdminLayout::renderFooter();
