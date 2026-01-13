<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$managerPath = dirname(__DIR__, 4) . '/app/vendor/tinyfilemanager/tinyfilemanager.php';
if (!is_file($managerPath)) {
    http_response_code(500);
    AdminLayout::renderHeader('Ошибка');
    echo '<div class="container py-4">';
    echo '<div class="text-danger fw-semibold">File manager not found</div>';
    echo '</div>';
    AdminLayout::renderFooter();
    exit;
}

define('FM_EMBED', true);

require $managerPath;
