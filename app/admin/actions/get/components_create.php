<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

redirectTo(buildAdminUrl(['action' => 'component_new']));

AdminLayout::renderHeader('Новый компонент');

echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h1 class="h4 mb-0">Новый компонент</h1>';
echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'components_list']), ENT_QUOTES, 'UTF-8') . '">Назад</a>';
echo '</div>';

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<form method="post" action="/admin.php?action=component_create">';
echo csrfTokenField();
echo '<div class="mb-3">';
echo '<label class="form-label">Ключ</label>';
echo '<input class="form-control" name="keyword" required>';
echo '</div>';
echo '<div class="mb-3">';
echo '<label class="form-label">Название</label>';
echo '<input class="form-control" name="name" required>';
echo '</div>';
echo '<div class="mb-3">';
echo '<label class="form-label">Поля (JSON)</label>';
echo '<textarea class="form-control font-monospace" name="fields_json" rows="6" required>{"fields":[]}</textarea>';
echo '</div>';
echo '<div class="mb-3">';
echo '<label class="form-label">Шаблоны компонента (JSON)</label>';
echo '<textarea class="form-control font-monospace" name="views_json" rows="3">["list"]</textarea>';
echo '</div>';
echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
echo '</form>';
echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
