<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl([]));
}

AdminLayout::renderHeader('Новый сайт');

echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h1 class="h4 mb-0">Новый сайт</h1>';
echo '<a class="btn btn-link p-0 link-dotted" href="' . htmlspecialchars(buildAdminUrl(), ENT_QUOTES, 'UTF-8') . '">Назад</a>';
echo '</div>';

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<form method="post" action="/admin.php?action=site_create" data-ajax="true">';
echo csrf_token_field();
echo '<div class="mb-3"><label class="form-label">Название сайта</label><input class="form-control" type="text" name="title" required></div>';
echo '<div class="mb-3"><label class="form-label">Основной домен</label><input class="form-control" type="text" name="site_domain"></div>';
echo '<div class="mb-3"><label class="form-label">Зеркала домена (по одному в строке)</label><textarea class="form-control" name="site_mirrors" rows="3"></textarea></div>';
echo '<div class="mb-3 form-check">';
echo '<input class="form-check-input" type="checkbox" name="site_enabled" value="1" checked>';
echo '<label class="form-check-label">Сайт включен</label>';
echo '</div>';
echo '<div class="mb-3"><label class="form-label">HTML для отключенного сайта</label><textarea class="form-control" name="site_offline_html" rows="4"></textarea></div>';
echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
echo '</form>';
echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
