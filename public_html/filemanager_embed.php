<?php

require __DIR__ . '/../app/admin/bootstrap.php';

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
