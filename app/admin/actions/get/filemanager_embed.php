<?php

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo 'Недостаточно прав';
    exit;
}

$managerPath = dirname(__DIR__, 4) . '/app/vendor/tinyfilemanager/tinyfilemanager.php';
if (!is_file($managerPath)) {
    http_response_code(500);
    echo 'File manager not found';
    exit;
}

define('FM_EMBED', true);

require $managerPath;
