<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$root = dirname(__DIR__, 4);
$managerPath = $root . '/app/vendor/tinyfilemanager/tinyfilemanager.php';
if (!is_file($managerPath)) {
    AdminLayout::renderHeader('Файловый менеджер');
    echo '<div class="container py-4">';
    echo '<div class="text-danger fw-semibold">Файловый менеджер не найден.</div>';
    echo '</div>';
    AdminLayout::renderFooter();
    exit;
}

define('FM_EMBED', true);

require $managerPath;
exit;
