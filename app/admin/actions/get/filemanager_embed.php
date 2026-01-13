<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$managerPath = dirname(__DIR__, 4) . '/app/vendor/tinyfilemanager/tinyfilemanager.php';
if (!is_file($managerPath)) {
    http_response_code(500);
    echo 'File manager not found';
    exit;
}

define('FM_EMBED', true);

require $managerPath;
