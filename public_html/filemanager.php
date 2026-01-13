<?php

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/admin/AdminHelpers.php';

if (!Auth::isAdmin()) {
    redirectTo('/admin.php?action=login');
}

$managerPath = __DIR__ . '/../app/vendor/filegator/index.php';
if (!is_file($managerPath)) {
    http_response_code(500);
    echo 'File manager not found';
    exit;
}

define('FM_EMBED', true);

require $managerPath;
