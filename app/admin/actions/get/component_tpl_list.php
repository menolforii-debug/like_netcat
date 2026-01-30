<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$components = $componentRepo->listAll();

AdminLayout::renderHeader('Шаблоны компонентов');

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<h1 class="h5 mb-0">Шаблоны компонентов</h1>';
echo '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'components']), ENT_QUOTES, 'UTF-8') . '">К компонентам</a>';
echo '</div>';

if (empty($components)) {
    echo '<div class="text-muted">Компоненты пока не созданы.</div>';
} else {
    echo '<div class="list-group">';
    foreach ($components as $component) {
        $link = buildAdminUrl(['action' => 'component_tpl_form', 'component_id' => (int) $component['id']]);
        echo '<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">';
        echo '<span>' . htmlspecialchars((string) $component['name'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<span class="text-muted small">' . htmlspecialchars((string) $component['keyword'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</a>';
    }
    echo '</div>';
}

echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
