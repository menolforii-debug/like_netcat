<?php

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/admin/AdminHelpers.php';

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo 'Недостаточно прав';
    exit;
}

$managerPath = __DIR__ . '/../app/vendor/filegator/index.php';
if (!is_file($managerPath)) {
    http_response_code(500);
    echo 'Filegator not found';
    exit;
}

require $managerPath;
