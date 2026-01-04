<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

AdminLayout::renderHeader('Новый компонент');
renderAlert($notice, 'success');
renderAlert($errorMessage, 'error');

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h1 class="h4 mb-0">Новый компонент</h1>';
echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'components']), ENT_QUOTES, 'UTF-8') . '">Назад</a>';
echo '</div>';
echo '<form method="post" action="/admin.php?action=component_create">';
echo csrfTokenField();
echo '<div class="mb-3"><label class="form-label">Ключ</label><input class="form-control" name="keyword" required></div>';
echo '<div class="mb-3"><label class="form-label">Название</label><input class="form-control" name="name" required></div>';
echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
echo '</form>';
echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
